<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionNotification;
use App\Mail\SubscriptionSuspendedMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ForceSuspendSubscription extends Command
{
    protected $signature = 'subscription:force-suspend {id} {--skip-email : Skip sending the email notification}';
    protected $description = 'Force suspend a subscription (for testing purposes). ID can be subscription ID or Stripe customer ID';

    public function handle(): int
    {
        $identifier = $this->argument('id');

        // Try to find by ID first (cast to int if numeric)
        if (is_numeric($identifier)) {
            $subscription = Subscription::find((int)$identifier);
        } else {
            $subscription = null;
        }

        // If not found, try by Stripe Customer ID
        if (!$subscription && str_starts_with($identifier, 'cus_')) {
            $subscription = Subscription::where('customer_id', $identifier)->first();
        }

        // If still not found, try by Stripe Subscription ID
        if (!$subscription && str_starts_with($identifier, 'sub_')) {
            $subscription = Subscription::where('stripe_id', $identifier)->first();
        }

        if (!$subscription) {
            $this->error("❌ Subscription not found with identifier: {$identifier}");
            $this->line("   You can use: Subscription ID, Stripe Customer ID (cus_xxx), or Stripe Subscription ID (sub_xxx)");
            return self::FAILURE;
        }

        $this->info("🔍 Subscription found:");
        $this->line("  Customer: {$subscription->customer_name}");
        $this->line("  Email: {$subscription->customer_email}");
        $this->line("  Status: {$subscription->status}");
        $this->line("  Stripe ID: {$subscription->stripe_id}");
        $this->newLine();

        if (!$this->confirm('Do you want to proceed with suspension?', true)) {
            $this->info('Cancelled');
            return self::SUCCESS;
        }

        $this->info('🚀 Starting suspension process...');
        $this->newLine();

        // Contadores
        $whmSuspended = false;
        $stripePaused = false;
        $emailSent = false;

        // 1. Suspender cuenta WHM
        $server = data_get($subscription->data, 'server');
        $user = data_get($subscription->data, 'user');

        if (filled($server) && filled($user)) {
            $this->info('1️⃣ Suspending WHM account...');
            try {
                app(\App\Services\WHM\WHMServerManager::class)
                    ->suspendAccount($server, $user, "Manual suspension for testing - Client no longer wants the service");

                $whmSuspended = true;
                $this->line("   ✅ WHM account suspended");
                $this->line("   Server: {$server}");
                $this->line("   User: {$user}");
            } catch (\Throwable $e) {
                $this->error("   ❌ Failed to suspend WHM account: {$e->getMessage()}");
                Log::error('Failed to suspend WHM account', [
                    'subscription_id' => $subscription->id,
                    'server' => $server,
                    'user' => $user,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $this->warn('1️⃣ No WHM server/user found - skipping');
        }

        $this->newLine();

        // 2. Pausar suscripción en Stripe
        if (!str_starts_with($subscription->stripe_id, 'manual-')) {
            $this->info('2️⃣ Pausing Stripe subscription...');
            try {
                $stripe = app(\Stripe\StripeClient::class);
                $stripeSubscription = $stripe->subscriptions->update(
                    $subscription->stripe_id,
                    [
                        'pause_collection' => [
                            'behavior' => 'mark_uncollectible',
                        ],
                    ]
                );

                $stripePaused = true;
                $this->line("   ✅ Stripe subscription paused");
                $this->line("   Stripe ID: {$subscription->stripe_id}");
                $this->line("   Pause behavior: mark_uncollectible");

                Log::info('Stripe subscription paused', [
                    'subscription_id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                ]);
            } catch (\Throwable $e) {
                $this->error("   ❌ Failed to pause Stripe subscription: {$e->getMessage()}");
                Log::error('Failed to pause Stripe subscription', [
                    'subscription_id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $this->warn('2️⃣ Manual subscription - skipping Stripe pause');
        }

        $this->newLine();

        // 3. Actualizar estado en BD
        $this->info('3️⃣ Updating database status...');
        $oldStatus = $subscription->status;
        $subscription->update(['status' => 'paused']);
        $this->line("   ✅ Status updated: {$oldStatus} → paused");

        $this->newLine();

        // 4. Enviar email de notificación
        if (!$this->option('skip-email')) {
            $this->info('4️⃣ Sending suspension email...');
            try {
                // Crear notificación
                $notification = SubscriptionNotification::create([
                    'subscription_id' => $subscription->id,
                    'notification_type' => 'suspended',
                    'status' => 'pending',
                    'scheduled_at' => now(),
                    'recipient_email' => $subscription->customer_email,
                    'recipient_name' => $subscription->customer_name,
                    'body' => '',
                ]);

                // Enviar email
                $mailable = new SubscriptionSuspendedMail($subscription);
                Mail::to($subscription->customer_email)->send($mailable);

                // Marcar como enviado
                $notification->markAsSent();

                $emailSent = true;
                $this->line("   ✅ Email sent to: {$subscription->customer_email}");
            } catch (\Throwable $e) {
                $this->error("   ❌ Failed to send email: {$e->getMessage()}");
                if (isset($notification)) {
                    $notification->markAsFailed($e->getMessage());
                }
                Log::error('Failed to send suspension email', [
                    'subscription_id' => $subscription->id,
                    'email' => $subscription->customer_email,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $this->warn('4️⃣ Email sending skipped (--skip-email flag)');
        }

        $this->newLine();
        $this->info('📊 Summary:');
        $this->line("   WHM Suspended: " . ($whmSuspended ? '✅ YES' : '❌ NO'));
        $this->line("   Stripe Paused: " . ($stripePaused ? '✅ YES' : '❌ NO'));
        $this->line("   DB Status: ✅ Updated to 'paused'");
        $this->line("   Email Sent: " . ($emailSent ? '✅ YES' : ($this->option('skip-email') ? '⏭️  SKIPPED' : '❌ NO')));

        $this->newLine();
        $this->info('✅ Suspension process completed!');

        return self::SUCCESS;
    }
}

