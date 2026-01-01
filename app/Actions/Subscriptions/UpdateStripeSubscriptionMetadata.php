<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Services\Stripe\StripeSubscriptionService;
use Illuminate\Support\Facades\Log;

class UpdateStripeSubscriptionMetadata
{
    public function __construct(
        private readonly StripeSubscriptionService $stripe,
    )
    {
    }

    /**
     * Update subscription metadata in Stripe and local database
     */
    public function handle(Subscription $subscription, array $data): bool
    {
        try
        {
            // Prepare metadata for Stripe (filter null values)
            $stripeMetadata = array_filter([
                'server' => $data['server'] ?? null,
                'domain' => $data['domain'] ?? null,
                'user' => $data['user'] ?? null,
                'email' => $data['email'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            // Update in Stripe
            $this->stripe->updateMetadata($subscription->stripe_id, $stripeMetadata);

            // Update in local database
            $subscription->update([
                'data' => $stripeMetadata,
            ]);

            Log::info('Subscription metadata updated', [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'metadata' => $stripeMetadata,
            ]);

            return true;
        }
        catch (\Throwable $exception)
        {
            Log::error('Failed to update subscription metadata', [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
