<?php

namespace App\Http\Controllers;

use App\Actions\Subscriptions\ReactivateSuspendedSubscription;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhooks
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (blank($webhookSecret)) {
            Log::error('Stripe webhook: Secret not configured');
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook: Invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info('Stripe webhook: Event received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        // Handle specific events
        try {
            $method = 'handle' . str_replace('_', '', ucwords($event->type, '_'));

            if (method_exists($this, $method)) {
                $this->$method($event->data->object->toArray());
            } else {
                Log::info('Stripe webhook: Unhandled event type', ['type' => $event->type]);
            }
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: Processing error', [
                'type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Processing error'], 500);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle invoice.payment_succeeded event
     */
    protected function handleInvoicePaymentSucceeded(array $invoice)
    {
        $stripeInvoiceId = $invoice['id'];
        $stripeSubscriptionId = $invoice['subscription'] ?? null;

        Log::info('Stripe webhook: Invoice payment succeeded', [
            'invoice_id' => $stripeInvoiceId,
            'subscription_id' => $stripeSubscriptionId,
            'amount_paid' => ($invoice['amount_paid'] ?? 0) / 100,
        ]);

        // Actualizar la factura en nuestra base de datos
        $invoiceModel = Invoice::where('stripe_id', $stripeInvoiceId)->first();

        if ($invoiceModel) {
            $invoiceModel->update([
                'status' => 'paid',
                'paid' => true,
                'amount_paid' => ($invoice['amount_paid'] ?? 0) / 100,
                'amount_remaining' => 0,
            ]);

            Log::info('Stripe webhook: Invoice updated in database', [
                'invoice_number' => $invoiceModel->number,
            ]);
        }

        // Verificar si la suscripción debe reactivarse
        if ($stripeSubscriptionId) {
            $subscription = Subscription::where('stripe_id', $stripeSubscriptionId)->first();

            if ($subscription) {
                // Contar facturas impagas restantes
                $unpaidCount = Invoice::where('stripe_subscription_id', $stripeSubscriptionId)
                    ->where('status', 'open')
                    ->where('paid', false)
                    ->count();

                Log::info('Stripe webhook: Checking subscription reactivation', [
                    'subscription_id' => $subscription->id,
                    'customer_name' => $subscription->customer_name,
                    'unpaid_invoices_count' => $unpaidCount,
                    'current_status' => $subscription->status,
                ]);

                // Si tiene menos de 2 facturas impagas, reactivar
                if ($unpaidCount < 2 && in_array($subscription->status, ['paused', 'past_due'])) {
                    app(ReactivateSuspendedSubscription::class)->handle($subscription);
                }
            }
        }
    }

    /**
     * Handle invoice.payment_failed event
     */
    protected function handleInvoicePaymentFailed(array $invoice)
    {
        $stripeInvoiceId = $invoice['id'];

        Log::warning('Stripe webhook: Invoice payment failed', [
            'invoice_id' => $stripeInvoiceId,
            'attempt_count' => $invoice['attempt_count'] ?? 0,
        ]);

        // Actualizar la factura
        $invoiceModel = Invoice::where('stripe_id', $stripeInvoiceId)->first();

        if ($invoiceModel) {
            $invoiceModel->update([
                'status' => 'open',
                'paid' => false,
            ]);
        }
    }

    /**
     * Handle customer.subscription.updated event
     */
    protected function handleCustomerSubscriptionUpdated(array $subscription)
    {
        $stripeSubscriptionId = $subscription['id'];
        $status = $subscription['status'];
        $pauseCollection = $subscription['pause_collection'] ?? null;

        Log::info('Stripe webhook: Subscription updated', [
            'subscription_id' => $stripeSubscriptionId,
            'status' => $status,
            'pause_collection' => $pauseCollection ? 'paused' : 'active',
        ]);

        // Actualizar la suscripción en nuestra base de datos
        $subscriptionModel = Subscription::where('stripe_id', $stripeSubscriptionId)->first();

        if ($subscriptionModel) {
            // Determinar el estado real basado en pause_collection
            $actualStatus = $status;

            // Si tiene pause_collection configurado, está pausada
            if ($pauseCollection !== null) {
                $actualStatus = 'paused';

                Log::info('Stripe webhook: Subscription is PAUSED', [
                    'subscription_id' => $subscriptionModel->id,
                    'pause_behavior' => $pauseCollection['behavior'] ?? null,
                ]);
            } else {
                // Si NO tiene pause_collection, puede estar reactivándose
                if ($subscriptionModel->status === 'paused' && $status === 'active') {
                    Log::info('Stripe webhook: Subscription RESUMED from pause', [
                        'subscription_id' => $subscriptionModel->id,
                    ]);

                    // Verificar si debe reactivarse (< 2 facturas impagas)
                    $unpaidCount = \App\Models\Invoice::where('stripe_subscription_id', $stripeSubscriptionId)
                        ->where('status', 'open')
                        ->where('paid', false)
                        ->count();

                    if ($unpaidCount < 2) {
                        app(\App\Actions\Subscriptions\ReactivateSuspendedSubscription::class)
                            ->handle($subscriptionModel);
                    }
                }
            }

            $subscriptionModel->update([
                'status' => $actualStatus,
            ]);

            Log::info('Stripe webhook: Subscription status updated in database', [
                'subscription_id' => $subscriptionModel->id,
                'new_status' => $actualStatus,
            ]);
        }
    }

    /**
     * Handle customer.subscription.deleted event
     */
    protected function handleCustomerSubscriptionDeleted(array $subscription)
    {
        $stripeSubscriptionId = $subscription['id'];

        Log::info('Stripe webhook: Subscription deleted', [
            'subscription_id' => $stripeSubscriptionId,
        ]);

        // Actualizar la suscripción en nuestra base de datos
        $subscriptionModel = Subscription::where('stripe_id', $stripeSubscriptionId)->first();

        if ($subscriptionModel) {
            $subscriptionModel->update([
                'status' => 'canceled',
            ]);

            Log::info('Stripe webhook: Subscription marked as canceled', [
                'subscription_id' => $subscriptionModel->id,
            ]);
        }
    }
}

