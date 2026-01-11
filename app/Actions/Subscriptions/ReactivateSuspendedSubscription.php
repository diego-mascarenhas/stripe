<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionNotification;
use App\Services\WHM\WHMServerManager;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class ReactivateSuspendedSubscription
{
    public function __construct(
        private readonly WHMServerManager $whmManager,
        private readonly StripeClient $stripe,
    ) {
    }

    /**
     * Reactiva una suscripción suspendida
     * 
     * - Reactiva la cuenta WHM
     * - Reanuda la suscripción en Stripe
     * - Crea notificación de reactivación
     */
    public function handle(Subscription $subscription): bool
    {
        Log::info('Attempting to reactivate subscription', [
            'subscription_id' => $subscription->id,
            'customer_name' => $subscription->customer_name,
            'stripe_id' => $subscription->stripe_id,
            'current_status' => $subscription->status,
        ]);

        try {
            // 1. Reactivar cuenta WHM (si tiene configuración)
            $server = data_get($subscription->data, 'server');
            $user = data_get($subscription->data, 'user');
            $whmReactivated = false;

            if (filled($server) && filled($user)) {
                try {
                    $this->whmManager->unsuspendAccount($server, $user);
                    $whmReactivated = true;
                    
                    Log::info('WHM account reactivated', [
                        'server' => $server,
                        'user' => $user,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to reactivate WHM account', [
                        'server' => $server,
                        'user' => $user,
                        'error' => $e->getMessage(),
                    ]);
                    // Continuar con el proceso aunque falle WHM
                }
            }

            // 2. Reanudar suscripción en Stripe (si está pausada)
            $stripeResumed = false;
            if ($subscription->status === 'paused' && !str_starts_with($subscription->stripe_id, 'manual-')) {
                try {
                    $this->stripe->subscriptions->update(
                        $subscription->stripe_id,
                        ['pause_collection' => null] // Esto reanuda la suscripción
                    );
                    
                    $stripeResumed = true;
                    
                    Log::info('Stripe subscription resumed', [
                        'stripe_id' => $subscription->stripe_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to resume Stripe subscription', [
                        'stripe_id' => $subscription->stripe_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 3. Actualizar estado de la suscripción en BD
            $subscription->update([
                'status' => 'active',
            ]);

            // 4. Crear notificación de reactivación
            SubscriptionNotification::create([
                'subscription_id' => $subscription->id,
                'notification_type' => 'reactivated',
                'status' => 'pending',
                'scheduled_at' => now(),
                'recipient_email' => $subscription->customer_email,
                'recipient_name' => $subscription->customer_name,
                'body' => '',
            ]);

            Log::info('Subscription reactivated successfully', [
                'subscription_id' => $subscription->id,
                'customer_name' => $subscription->customer_name,
                'whm_reactivated' => $whmReactivated,
                'stripe_resumed' => $stripeResumed,
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('Failed to reactivate subscription', [
                'subscription_id' => $subscription->id,
                'customer_name' => $subscription->customer_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}


