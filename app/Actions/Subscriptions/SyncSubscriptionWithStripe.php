<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Services\DNS\DNSLookupService;
use App\Services\Stripe\StripeSubscriptionService;
use App\Services\WHM\WHMServerManager;
use Illuminate\Support\Facades\Log;

class SyncSubscriptionWithStripe
{
    public function __construct(
        private readonly StripeSubscriptionService $stripe,
        private readonly WHMServerManager $whm,
        private readonly DNSLookupService $dns,
    ) {
    }

    /**
     * Sync subscription data from WHM/DNS to Stripe metadata
     */
    public function handle(Subscription $subscription): array
    {
        $syncResult = [
            'success' => false,
            'synced' => [],
            'errors' => [],
        ];

        try {
            $metadata = [];
            $server = data_get($subscription->data, 'server');
            $user = data_get($subscription->data, 'user');
            $domain = data_get($subscription->data, 'domain');

            // 1. Sync WHM data if available
            if ($server && $user) {
                try {
                    // Get WHM account info
                    $accountInfo = $this->whm->getAccountInfo($server, $user);
                    
                    if ($accountInfo) {
                        // Plan
                        if (!empty($accountInfo['plan'])) {
                            $metadata['plan'] = $accountInfo['plan'];
                            $syncResult['synced'][] = 'plan';
                        }

                        // Email
                        if (!empty($accountInfo['email'])) {
                            $metadata['email'] = $accountInfo['email'];
                            $syncResult['synced'][] = 'email';
                        }

                        // Status
                        $whmStatus = $accountInfo['suspended'] === 1 ? 'suspended' : 'active';
                        $metadata['whm_status'] = $whmStatus;
                        $syncResult['synced'][] = 'whm_status';

                        // Disk usage
                        if (!empty($accountInfo['diskused'])) {
                            $metadata['disk_used'] = $accountInfo['diskused'];
                            $syncResult['synced'][] = 'disk_used';
                        }

                        // Disk limit
                        if (!empty($accountInfo['disklimit'])) {
                            $metadata['disk_limit'] = $accountInfo['disklimit'];
                            $syncResult['synced'][] = 'disk_limit';
                        }
                    }
                } catch (\Throwable $e) {
                    $syncResult['errors'][] = 'WHM: ' . $e->getMessage();
                    Log::warning('Failed to sync WHM data', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 2. Sync DNS validation if domain available
            if ($domain) {
                try {
                    $validation = $this->dns->validateServiceConfiguration($domain);

                    $metadata['dns_has_own_ns'] = $validation['has_own_ns'] ? 'true' : 'false';
                    $metadata['dns_domain_points_to_service'] = $validation['domain_points_to_service'] ? 'true' : 'false';
                    $metadata['dns_mail_points_to_service'] = $validation['mail_points_to_service'] ? 'true' : 'false';
                    $metadata['dns_has_spf_include'] = $validation['has_spf_include'] ? 'true' : 'false';
                    $metadata['dns_current_ns'] = implode(',', $validation['current_nameservers']);
                    $metadata['dns_current_ips'] = implode(',', $validation['domain_ips']);
                    $metadata['dns_last_check'] = now()->toIso8601String();

                    $syncResult['synced'][] = 'dns_validation';
                } catch (\Throwable $e) {
                    $syncResult['errors'][] = 'DNS: ' . $e->getMessage();
                    Log::warning('Failed to sync DNS validation', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 3. Update Stripe metadata if we have data to sync
            if (!empty($metadata)) {
                // Preserve existing metadata
                $existingMetadata = $subscription->data ?? [];
                $mergedMetadata = array_merge($existingMetadata, $metadata);

                // Update in Stripe
                $this->stripe->updateMetadata($subscription->stripe_id, $metadata);

                // Update local database
                $subscription->update([
                    'data' => $mergedMetadata,
                ]);

                Log::info('Subscription synced with Stripe', [
                    'subscription_id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'synced_fields' => $syncResult['synced'],
                ]);

                $syncResult['success'] = true;
            }

            return $syncResult;
        } catch (\Throwable $exception) {
            Log::error('Failed to sync subscription with Stripe', [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'error' => $exception->getMessage(),
            ]);

            $syncResult['errors'][] = 'General: ' . $exception->getMessage();
            return $syncResult;
        }
    }
}



