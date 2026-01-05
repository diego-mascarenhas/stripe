<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class ListInvoices extends Command
{
    protected $signature = 'invoices:list {subscription_id?}';
    protected $description = 'Lista facturas';

    public function handle(): int
    {
        $subscriptionId = $this->argument('subscription_id');

        $query = Invoice::select('id', 'number', 'stripe_subscription_id', 'status', 'paid', 'invoice_created_at', 'amount_remaining')
            ->orderBy('invoice_created_at', 'desc');

        if ($subscriptionId) {
            $subscription = \App\Models\Subscription::find($subscriptionId);
            if (!$subscription) {
                $this->error('Suscripción no encontrada.');
                return 1;
            }
            $query->where('stripe_subscription_id', $subscription->stripe_id);
            $this->info("📋 Facturas de: {$subscription->customer_name}");
        } else {
            $query->limit(20);
            $this->info('📋 Últimas 20 facturas:');
        }

        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            $this->warn('No hay facturas.');
            return 0;
        }

        $this->newLine();

        foreach ($invoices as $invoice) {
            $paidLabel = $invoice->paid ? '✅ Pagada' : '❌ Impaga';
            $created = $invoice->invoice_created_at ? $invoice->invoice_created_at->format('Y-m-d') : 'N/A';
            
            $this->line("{$invoice->number} | {$paidLabel} | Status: {$invoice->status} | Creada: {$created} | Resta: \${$invoice->amount_remaining}");
        }

        $this->newLine();
        $this->line("Total: {$invoices->count()} facturas");

        return 0;
    }
}


