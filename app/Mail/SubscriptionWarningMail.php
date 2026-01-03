<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionWarningMail extends Mailable implements ShouldQueue
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
        return new Content(
            markdown: 'emails.subscriptions.warning',
            with: [
                'subscription' => $this->subscription,
                'daysRemaining' => $this->daysRemaining,
                'serviceName' => $this->subscription->plan_name,
                'amount' => number_format($this->subscription->amount_total, 2) . ' ' . strtoupper($this->subscription->price_currency),
                'dueDate' => $this->subscription->current_period_end?->format('d/m/Y'),
            ],
        );
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
