<?php

namespace App\Observers;

use App\Actions\Subscriptions\SyncSubscriptionWithWHM;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SubscriptionObserver
{
    /**
     * Handle the Subscription "updated" event.
     */
    public function updated(Subscription $subscription): void
    {
        // Solo procesar si:
        // 1. Cambió el status
        // 2. Tiene auto_suspend activado en metadata
        // 3. NO es una compra manual (detectada por stripe_id)
        if (
            $subscription->isDirty('status') &&
            ! str_starts_with($subscription->stripe_id, 'manual-') &&
            data_get($subscription->data, 'auto_suspend')
        ) {
            Log::info('Subscription status changed, queuing WHM sync', [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'old_status' => $subscription->getOriginal('status'),
                'new_status' => $subscription->status,
            ]);

            // Ejecutar después de la respuesta para no bloquear
            dispatch(function() use ($subscription) {
                try {
                    app(SyncSubscriptionWithWHM::class)->handle($subscription);
                } catch (\Throwable $e) {
                    // Ya está logueado en el action
                    Log::error('Failed to dispatch WHM sync', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();
        }
    }
}

