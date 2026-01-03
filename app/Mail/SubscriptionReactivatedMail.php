<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionReactivatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Tu servicio ha sido reactivado',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscriptions.reactivated',
            with: [
                'subscription' => $this->subscription,
                'serviceName' => $this->subscription->plan_name,
                'nextPayment' => $this->subscription->current_period_end?->format('d/m/Y'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
