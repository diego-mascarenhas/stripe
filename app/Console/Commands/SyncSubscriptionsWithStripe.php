<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\SyncSubscriptionWithStripe;
use App\Models\Subscription;
use Illuminate\Console\Command;

class SyncSubscriptionsWithStripe extends Command
{
    protected $signature = 'subscriptions:sync {--id= : Sync specific subscription by ID} {--force : Force sync even if recently synced}';

    protected $description = 'Sync subscriptions data (WHM, DNS) with Stripe metadata';

    public function handle(): int
    {
        $this->info('Starting subscription sync with Stripe...');
        $this->newLine();

        $query = Subscription::query()
            ->where('type', 'sell')
            ->where('status', 'active');

        // Sync specific subscription if ID provided
        if ($subscriptionId = $this->option('id')) {
            $query->where('id', $subscriptionId);
        } else {
            // Only sync subscriptions with server/domain data
            $query->where(function ($q) {
                $q->whereNotNull('data->server')
                  ->orWhereNotNull('data->domain');
            });
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('No subscriptions found to sync.');
            return self::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} subscription(s) to sync");
        $this->newLine();

        $bar = $this->output->createProgressBar($subscriptions->count());
        $bar->start();

        $stats = [
            'success' => 0,
            'partial' => 0,
            'failed' => 0,
            'total_synced' => [],
            'total_errors' => [],
        ];

        foreach ($subscriptions as $subscription) {
            try {
                $syncAction = app(SyncSubscriptionWithStripe::class);
                $result = $syncAction->handle($subscription);

                if ($result['success']) {
                    $stats['success']++;
                    $stats['total_synced'] = array_merge($stats['total_synced'], $result['synced']);
                } elseif (!empty($result['synced'])) {
                    $stats['partial']++;
                    $stats['total_synced'] = array_merge($stats['total_synced'], $result['synced']);
                    $stats['total_errors'] = array_merge($stats['total_errors'], $result['errors']);
                } else {
                    $stats['failed']++;
                    $stats['total_errors'] = array_merge($stats['total_errors'], $result['errors']);
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['total_errors'][] = "Subscription {$subscription->id}: " . $e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results
        $this->info('📊 Sync Results:');
        $this->line("   ✅ Success: {$stats['success']}");
        $this->line("   ⚠️  Partial: {$stats['partial']}");
        $this->line("   ❌ Failed: {$stats['failed']}");
        $this->newLine();

        if (!empty($stats['total_synced'])) {
            $syncedFields = array_count_values($stats['total_synced']);
            $this->info('📝 Synced Fields:');
            foreach ($syncedFields as $field => $count) {
                $this->line("   - {$field}: {$count}");
            }
            $this->newLine();
        }

        if (!empty($stats['total_errors'])) {
            $this->warn('⚠️  Errors encountered:');
            foreach (array_slice($stats['total_errors'], 0, 10) as $error) {
                $this->line("   - {$error}");
            }
            if (count($stats['total_errors']) > 10) {
                $remaining = count($stats['total_errors']) - 10;
                $this->line("   ... and {$remaining} more errors");
            }
        }

        return self::SUCCESS;
    }
}

