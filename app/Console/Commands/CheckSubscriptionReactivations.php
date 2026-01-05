<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\ReactivateSuspendedSubscription;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Console\Command;

class CheckSubscriptionReactivations extends Command
{
    protected $signature = 'subscriptions:check-reactivations';
    protected $description = 'Verifica y reactiva suscripciones suspendidas que ya pagaron';

    public function handle(): int
    {
        $this->info('🔄 Verificando suscripciones suspendidas...');

        // Buscar SOLO suscripciones pausadas (suspendidas por nosotros)
        // IMPORTANTE: No incluir 'past_due' porque significa pagos atrasados pero servicio activo
        $suspendedSubscriptions = Subscription::where('status', 'paused')
            ->get();

        if ($suspendedSubscriptions->isEmpty()) {
            $this->info('No hay suscripciones suspendidas para verificar.');
            return 0;
        }

        $this->line("Encontradas {$suspendedSubscriptions->count()} suscripciones suspendidas");
        $this->newLine();

        $reactivated = 0;
        $skipped = 0;

        foreach ($suspendedSubscriptions as $subscription) {
            // Contar facturas impagas
            $unpaidCount = Invoice::where('stripe_subscription_id', $subscription->stripe_id)
                ->where('status', 'open')
                ->where('paid', false)
                ->whereNotNull('invoice_created_at')
                ->count();

            $this->line("  • {$subscription->customer_name}: {$unpaidCount} factura(s) impaga(s)");

            // Si tiene menos de 2 facturas impagas, reactivar
            if ($unpaidCount < 2) {
                try {
                    $success = app(ReactivateSuspendedSubscription::class)->handle($subscription);
                    
                    if ($success) {
                        $this->line("    ✓ Reactivada");
                        $reactivated++;
                    } else {
                        $this->error("    ✗ Error al reactivar");
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $this->error("    ✗ Error: {$e->getMessage()}");
                    $skipped++;
                }
            } else {
                $this->line("    ⏸  Aún tiene {$unpaidCount} facturas impagas (requiere < 2)");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("✅ Proceso completado");
        $this->line("  → {$reactivated} suscripciones reactivadas");
        $this->line("  → {$skipped} omitidas");

        return 0;
    }
}

