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
        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            
            // Obtener las facturas de la suscripción
            $invoices = $stripe->invoices->all([
                'subscription' => $this->subscription->stripe_id,
                'status' => 'open',
                'limit' => 1,
            ]);

            if (!empty($invoices->data)) {
                return $invoices->data[0]->hosted_invoice_url ?? 'https://revisionalpha.com/login';
            }

            return 'https://revisionalpha.com/login';
        } catch (\Throwable $e) {
            \Log::warning('Could not get Stripe payment URL', [
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
            ]);
            return 'https://revisionalpha.com/login';
        }
    }

    public function attachments(): array
    {
        return [];
    }
}
