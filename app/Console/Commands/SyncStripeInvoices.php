<?php

namespace App\Console\Commands;

use App\Actions\Invoices\SyncStripeInvoices as SyncStripeInvoicesAction;
use Illuminate\Console\Command;

class SyncStripeInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza las facturas de Stripe con la base de datos local.';

    /**
     * Execute the console command.
     */
    public function handle(SyncStripeInvoicesAction $sync): int
    {
        $this->info('Iniciando sincronización de facturas desde Stripe...');

        try {
            $count = $sync->handle();

            $this->info("✓ Sincronización completada: {$count} facturas procesadas.");

            // Auto-vincular facturas sin subscription_id
            $this->linkInvoicesToSubscriptions();

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('✗ Error durante la sincronización: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Vincula automáticamente las facturas sin subscription_id a sus suscripciones
     */
    private function linkInvoicesToSubscriptions(): void
    {
        $this->newLine();
        $this->info('🔗 Vinculando facturas a suscripciones...');

        $invoices = \App\Models\Invoice::where(function ($q) {
                $q->whereNull('stripe_subscription_id')
                  ->orWhere('stripe_subscription_id', '');
            })
            ->whereNotNull('customer_id')
            ->where('customer_id', '!=', '')
            ->get();

        if ($invoices->isEmpty()) {
            $this->line('  ✓ Todas las facturas ya tienen subscription_id');
            return;
        }

        $linked = 0;

        foreach ($invoices as $invoice) {
            $subscriptions = \App\Models\Subscription::where(function($q) use ($invoice) {
                $q->where('customer_id', $invoice->customer_id)
                  ->orWhere('customer_email', $invoice->customer_email);
            })->get();

            if ($subscriptions->isEmpty()) {
                continue;
            }

            if ($subscriptions->count() === 1) {
                $invoice->update(['stripe_subscription_id' => $subscriptions->first()->stripe_id]);
                $linked++;
            } else {
                // Múltiples suscripciones, vincular por fecha
                $matchedSub = null;
                
                foreach ($subscriptions as $sub) {
                    if ($invoice->invoice_created_at && 
                        $sub->current_period_start && 
                        $sub->current_period_end) {
                        
                        if ($invoice->invoice_created_at->between($sub->current_period_start, $sub->current_period_end)) {
                            $matchedSub = $sub;
                            break;
                        }
                    }
                }

                if ($matchedSub) {
                    $invoice->update(['stripe_subscription_id' => $matchedSub->stripe_id]);
                    $linked++;
                } else {
                    // Usar la suscripción activa o más reciente
                    $activeSub = $subscriptions->where('status', 'active')->first()
                        ?? $subscriptions->sortByDesc('created_at')->first();
                    
                    if ($activeSub) {
                        $invoice->update(['stripe_subscription_id' => $activeSub->stripe_id]);
                        $linked++;
                    }
                }
            }
        }

        if ($linked > 0) {
            $this->info("  ✓ {$linked} factura(s) vinculada(s) automáticamente");
        }
    }
}

