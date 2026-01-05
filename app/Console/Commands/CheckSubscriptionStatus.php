<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Invoice;
use Illuminate\Console\Command;

class CheckSubscriptionStatus extends Command
{
    protected $signature = 'subscription:check {id}';
    protected $description = 'Check subscription status and notification logic. ID can be subscription ID or Stripe customer ID';

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
            $this->error("Subscription not found with identifier: {$identifier}");
            $this->line("You can use: Subscription ID, Stripe Customer ID (cus_xxx), or Stripe Subscription ID (sub_xxx)");
            return self::FAILURE;
        }

        $this->info("📋 Subscription Details:");
        $this->line("  Customer: {$subscription->customer_name}");
        $this->line("  Status: {$subscription->status}");
        $this->line("  Stripe ID: {$subscription->stripe_id}");
        $this->line("  Auto-suspend: " . (data_get($subscription->data, 'auto_suspend', false) ? 'YES' : 'NO'));
        $this->newLine();

        // Get unpaid invoices
        $unpaidInvoices = Invoice::where('stripe_subscription_id', $subscription->stripe_id)
            ->where('status', 'open')
            ->where('paid', false)
            ->whereNotNull('invoice_created_at')
            ->orderBy('invoice_created_at', 'asc')
            ->get();

        $this->info("💰 Unpaid Invoices: {$unpaidInvoices->count()}");

        if ($unpaidInvoices->isEmpty()) {
            $this->warn("  No unpaid invoices found");
            return self::SUCCESS;
        }

        foreach ($unpaidInvoices as $invoice) {
            $daysSinceCreated = $invoice->invoice_created_at->diffInDays(now(), false);
            $this->line("  - {$invoice->number}");
            $this->line("    Created: {$invoice->invoice_created_at->format('Y-m-d H:i')}");
            $this->line("    Days since created: {$daysSinceCreated}");
            $this->line("    Amount: {$invoice->amount_due} {$invoice->currency}");
        }

        $this->newLine();

        // Check notification logic
        if ($unpaidInvoices->count() >= 2) {
            $oldestInvoice = $unpaidInvoices->first();
            $daysSinceOldest = $oldestInvoice->invoice_created_at->diffInDays(now(), false);

            $this->info("⚠️  Notification Logic (2+ unpaid invoices):");
            $this->line("  Oldest invoice: {$oldestInvoice->number}");
            $this->line("  Days since oldest: {$daysSinceOldest}");
            $this->newLine();

            if ($daysSinceOldest >= 40 && $daysSinceOldest < 43) {
                $this->warn("  → Should send: WARNING 5 DAYS");
            } elseif ($daysSinceOldest >= 43 && $daysSinceOldest < 45) {
                $this->warn("  → Should send: WARNING 2 DAYS");
            } elseif ($daysSinceOldest >= 45) {
                $autoSuspend = data_get($subscription->data, 'auto_suspend', false);
                if ($autoSuspend && $subscription->status === 'active') {
                    $this->error("  → Should SUSPEND NOW!");
                } elseif (!$autoSuspend) {
                    $this->warn("  → Would suspend, but auto_suspend is disabled");
                } elseif ($subscription->status !== 'active') {
                    $this->warn("  → Would suspend, but status is: {$subscription->status}");
                }
            } else {
                $this->info("  → No action needed yet (day {$daysSinceOldest}/45)");
            }
        } else {
            $this->info("⚠️  Only {$unpaidInvoices->count()} unpaid invoice(s) - needs 2 to trigger notifications");
        }

        return self::SUCCESS;
    }
}

