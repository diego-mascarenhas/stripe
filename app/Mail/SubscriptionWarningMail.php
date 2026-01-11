<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->daysRemaining === 5
            ? '⚠️ Tu servicio vence en 5 días - Acción requerida'
            : '🚨 Tu servicio vence en 2 días - Última oportunidad';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Obtener la última factura pendiente de Stripe
        $paymentUrl = $this->getPaymentUrl();

        // Obtener la fecha de vencimiento de la factura más antigua impaga
        $dueDate = $this->getOldestInvoiceDueDate();

        return new Content(
            markdown: 'emails.subscriptions.warning',
            with: [
                'subscription' => $this->subscription,
                'daysRemaining' => $this->daysRemaining,
                'serviceName' => $this->subscription->plan_name,
                'amount' => number_format($this->subscription->amount_total, 2) . ' ' . strtoupper($this->subscription->price_currency),
                'dueDate' => $dueDate,
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
            
            \Log::info('Fetching Stripe invoices', [
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
                    
                    \Log::info('Found unpaid invoice', [
                        'subscription_id' => $this->subscription->id,
                        'invoice_id' => $invoice->id,
                        'invoice_status' => $invoice->status,
                        'hosted_invoice_url' => $url,
                    ]);
                    
                    return $url ?? 'https://revisionalpha.com/login';
                }
            }

            \Log::warning('No unpaid invoices found', [
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

    /**
     * Get the due date from the oldest unpaid invoice
     */
    private function getOldestInvoiceDueDate(): string
    {
        // Buscar la factura impaga más antigua en la BD local
        $oldestInvoice = \App\Models\Invoice::where('stripe_subscription_id', $this->subscription->stripe_id)
            ->where('status', 'open')
            ->where('paid', false)
            ->whereNotNull('invoice_due_date')
            ->orderBy('invoice_created_at', 'asc')
            ->first();

        if ($oldestInvoice && $oldestInvoice->invoice_due_date) {
            return $oldestInvoice->invoice_due_date->format('d/m/Y');
        }

        // Fallback: usar current_period_end de la suscripción
        return $this->subscription->current_period_end?->format('d/m/Y') ?? 'No disponible';
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
