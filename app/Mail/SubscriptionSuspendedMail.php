<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionSuspendedMail extends Mailable implements ShouldQueue
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
        return new Content(
            markdown: 'emails.subscriptions.suspended',
            with: [
                'subscription' => $this->subscription,
                'serviceName' => $this->subscription->plan_name,
                'server' => data_get($this->subscription->data, 'server'),
                'domain' => data_get($this->subscription->data, 'domain'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
