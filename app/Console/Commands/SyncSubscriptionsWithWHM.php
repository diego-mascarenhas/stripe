<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\SyncSubscriptionWithWHM;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSubscriptionsWithWHM extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:sync-whm {--subscription= : Specific subscription ID}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize subscriptions with WHM servers (suspend/unsuspend accounts)';

    /**
     * Execute the console command.
     */
    public function handle(SyncSubscriptionWithWHM $action): int
    {
        $this->info('Starting WHM synchronization...');

        $query = Subscription::query()
            ->where('stripe_id', 'not like', 'manual-%')
            ->whereNotNull('data->auto_suspend')
            ->whereNotNull('data->server')
            ->whereNotNull('data->user');

        // Si se especificó una suscripción específica
        if ($subscriptionId = $this->option('subscription')) {
            $query->where('id', $subscriptionId);
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('No subscriptions found to sync.');

            return self::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} subscription(s) to sync.");

        $bar = $this->output->createProgressBar($subscriptions->count());
        $bar->start();

        $synced = 0;
        $errors = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $result = $action->handle($subscription);

                if ($result) {
                    $synced++;
                    $this->newLine();
                    $this->info("✓ Synced: {$subscription->customer_name} ({$subscription->stripe_id})");
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("✗ Failed: {$subscription->customer_name} ({$subscription->stripe_id})");
                $this->error("  Error: {$e->getMessage()}");

                Log::error('Manual WHM sync failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Synchronization complete:");
        $this->table(
            ['Status', 'Count'],
            [
                ['Synced', $synced],
                ['Errors', $errors],
                ['Skipped', $subscriptions->count() - $synced - $errors],
            ]
        );

        return self::SUCCESS;
    }
}

