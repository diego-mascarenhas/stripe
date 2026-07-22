<?php

namespace App\Support\Subscriptions;

use Illuminate\Support\Arr;

/**
 * - status: operational (active = service on, paused = cPanel suspended).
 * - stripe_status: mirrored from Stripe (active, past_due, unpaid, canceled, …).
 */
final class SubscriptionStatusGuard
{
    public static function stripeStatusFromPayload(array $payload): ?string
    {
        return Arr::get($payload, 'status');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function withoutOperationalStatus(array $attributes): array
    {
        unset($attributes['status']);

        return $attributes;
    }
}
