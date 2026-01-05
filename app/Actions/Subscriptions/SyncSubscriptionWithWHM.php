<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Services\WHM\WHMServerManager;
use Illuminate\Support\Facades\Log;

class SyncSubscriptionWithWHM
{
    public function __construct(
        private readonly WHMServerManager $whm
    ) {}

    /**
     * Synchronize subscription status with WHM server
     */
    public function handle(Subscription $subscription): bool
    {
        // Solo procesar si tiene auto_suspend activado
        if (!data_get($subscription->data, 'auto_suspend')) {
            Log::debug('Subscription does not have auto_suspend enabled', [
                'subscription_id' => $subscription->id,
            ]);

            return false;
        }

        // Obtener server y user desde la metadata
        $server = data_get($subscription->data, 'server');
        $user = data_get($subscription->data, 'user');

        if (!$server || !$user) {
            Log::warning('Subscription missing WHM metadata', [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'has_server' => !empty($server),
                'has_user' => !empty($user),
            ]);

            return false;
        }

        try {
            // Si está activa, reactivar la cuenta
            if ($subscription->status === 'active') {
                $this->whm->unsuspendAccount($server, $user);

                Log::info('WHM account unsuspended', [
                    'subscription_id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'server' => $server,
                    'user' => $user,
                ]);

                return true;
            }

            // Si está cancelada, vencida o impaga, suspender
            if (in_array($subscription->status, ['canceled', 'past_due', 'unpaid', 'incomplete_expired'])) {
                $reason = match($subscription->status) {
                    'canceled' => 'Subscription canceled',
                    'past_due' => 'Payment overdue',
                    'unpaid' => 'Payment required',
                    'incomplete_expired' => 'Payment incomplete',
                    default => 'Suspended by system',
                };

                $this->whm->suspendAccount($server, $user, $reason);

                Log::info('WHM account suspended', [
                    'subscription_id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'server' => $server,
                    'user' => $user,
                    'reason' => $reason,
                ]);

                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to sync subscription with WHM', [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'server' => $server,
                'user' => $user,
                'status' => $subscription->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // No lanzar la excepción para no bloquear el flujo
            // Solo loguear el error para revisión
            return false;
        }
    }
}



