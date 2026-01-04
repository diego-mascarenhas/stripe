<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionSuspendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '❌ Tu servicio ha sido suspendido - Reactívalo ahora',
        );
    }

    public function content(): Content
    {
        // Obtener la última factura pendiente de Stripe
        $paymentUrl = $this->getPaymentUrl();

        return new Content(
            markdown: 'emails.subscriptions.suspended',
            with: [
                'subscription' => $this->subscription,
                'serviceName' => $this->subscription->plan_name,
                'server' => data_get($this->subscription->data, 'server'),
                'domain' => data_get($this->subscription->data, 'domain'),
                'paymentUrl' => $paymentUrl,
            ],
        );
    }

    /**
     * Get the payment URL from Stripe
     */
    private function getPaymentUrl(): string
    {
        // Si es una suscripción manual, usar login
        if (str_starts_with($this->subscription->stripe_id, 'manual-')) {
            \Log::info('Manual subscription, using login URL', [
                'subscription_id' => $this->subscription->id,
            ]);
            return 'https://revisionalpha.com/login';
        }

        try {
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            
            \Log::info('Fetching Stripe invoices for suspended subscription', [
                'subscription_id' => $this->subscription->id,
                'stripe_subscription_id' => $this->subscription->stripe_id,
            ]);

            // Obtener todas las facturas pendientes de pago (open o past_due)
            $invoices = $stripe->invoices->all([
                'subscription' => $this->subscription->stripe_id,
                'limit' => 10,
            ]);

            \Log::info('Stripe invoices found', [
                'count' => count($invoices->data),
                'subscription_id' => $this->subscription->id,
            ]);

            // Buscar la primera factura pendiente (open, past_due, or unpaid)
            foreach ($invoices->data as $invoice) {
                if (in_array($invoice->status, ['open', 'past_due', 'uncollectible'])) {
                    $url = $invoice->hosted_invoice_url;
                    
                    \Log::info('Found unpaid invoice for suspended subscription', [
                        'subscription_id' => $this->subscription->id,
                        'invoice_id' => $invoice->id,
                        'invoice_status' => $invoice->status,
                        'hosted_invoice_url' => $url,
                    ]);
                    
                    return $url ?? 'https://revisionalpha.com/login';
                }
            }

            \Log::warning('No unpaid invoices found for suspended subscription', [
                'subscription_id' => $this->subscription->id,
                'invoices_checked' => count($invoices->data),
            ]);

            return 'https://revisionalpha.com/login';
        } catch (\Throwable $e) {
            \Log::error('Could not get Stripe payment URL', [
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'https://revisionalpha.com/login';
        }
    }

    public function attachments(): array
    {
        return [];
    }
}
