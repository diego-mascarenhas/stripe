<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class FindSubscription extends Command
{
    protected $signature = 'subscription:find {search : Customer name, email, domain, or Stripe ID}';
    protected $description = 'Find subscriptions by customer name, email, domain, or Stripe IDs';

    public function handle(): int
    {
        $search = $this->argument('search');
        
        $this->info("🔍 Searching for: {$search}");
        $this->newLine();

        $subscriptions = Subscription::query()
            ->where(function ($query) use ($search) {
                $query->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_id', $search)
                    ->orWhere('stripe_id', $search)
                    ->orWhereRaw("JSON_EXTRACT(data, '$.domain') LIKE ?", ["%{$search}%"]);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('No subscriptions found');
            return self::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} subscription(s):");
        $this->newLine();

        foreach ($subscriptions as $subscription) {
            $domain = data_get($subscription->data, 'domain', 'N/A');
            $autoSuspend = data_get($subscription->data, 'auto_suspend', false) ? '✅' : '❌';
            
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("ID: {$subscription->id}");
            $this->line("Customer: {$subscription->customer_name}");
            $this->line("Email: {$subscription->customer_email}");
            $this->line("Domain: {$domain}");
            $this->line("Status: {$subscription->status}");
            $this->line("Auto-suspend: {$autoSuspend}");
            $this->line("Stripe Customer: {$subscription->customer_id}");
            $this->line("Stripe Subscription: {$subscription->stripe_id}");
            
            // Count unpaid invoices
            $unpaidCount = \App\Models\Invoice::where('stripe_subscription_id', $subscription->stripe_id)
                ->where('status', 'open')
                ->where('paid', false)
                ->count();
            
            if ($unpaidCount > 0) {
                $this->line("⚠️  Unpaid Invoices: {$unpaidCount}");
            }
            
            $this->newLine();
        }

        if ($subscriptions->count() === 1) {
            $sub = $subscriptions->first();
            $this->info("💡 Quick commands for this subscription:");
            $this->line("   php artisan subscription:check {$sub->id}");
            $this->line("   php artisan subscription:force-suspend {$sub->id}");
        }

        return self::SUCCESS;
    }
}

