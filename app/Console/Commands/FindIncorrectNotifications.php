<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionNotification;
use Illuminate\Console\Command;

class FindIncorrectNotifications extends Command
{
    protected $signature = 'notifications:find-incorrect {--sync : Sincronizar desde Stripe antes de verificar}';
    protected $description = 'Encuentra notificaciones que se enviaron incorrectamente a clientes con facturas pagadas';

    public function handle(): int
    {
        if ($this->option('sync'))
        {
            $this->info('🔄 Sincronizando facturas desde Stripe...');
            $this->call('invoices:sync');
            $this->newLine();
        }

        $this->info('🔍 Buscando notificaciones incorrectas...');
        $this->newLine();

        // Buscar notificaciones de warning enviadas en los últimos 30 días
        $recentNotifications = SubscriptionNotification::whereIn('notification_type', ['warning_5_days', 'warning_2_days'])
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->subDays(30))
            ->with('subscription')
            ->get();

        $incorrectCount = 0;
        $incorrectNotifications = [];

        foreach ($recentNotifications as $notification)
        {
            $subscription = $notification->subscription;

            if (!$subscription)
            {
                continue;
            }

            // Contar facturas impagas ACTUALES
            $unpaidInvoicesCount = Invoice::where('stripe_subscription_id', $subscription->stripe_id)
                ->where('status', 'open')
                ->where('paid', false)
                ->whereNotNull('invoice_created_at')
                ->count();

            // Si NO tiene facturas impagas, la notificación fue incorrecta
            if ($unpaidInvoicesCount === 0)
            {
                $incorrectCount++;
                $incorrectNotifications[] = [
                    'notification_id' => $notification->id,
                    'subscription' => $subscription,
                    'notification' => $notification,
                    'unpaid_count' => $unpaidInvoicesCount,
                ];

                $this->warn("⚠️  {$subscription->customer_name} ({$subscription->customer_email})");
                $this->line("   Notificación: {$notification->getTypeLabel()} - Enviada: {$notification->sent_at->format('Y-m-d H:i')}");
                $this->line("   Facturas impagas actuales: <fg=yellow>{$unpaidInvoicesCount}</>");
                $this->line("   Estado suscripción: {$subscription->status}");

                // Mostrar facturas
                $allInvoices = Invoice::where('stripe_subscription_id', $subscription->stripe_id)
                    ->orderByDesc('invoice_created_at')
                    ->limit(3)
                    ->get();

                if ($allInvoices->isNotEmpty())
                {
                    $this->line("   Últimas facturas:");
                    foreach ($allInvoices as $inv)
                    {
                        $statusColor = $inv->paid ? 'green' : 'red';
                        $statusText = $inv->paid ? 'PAGADA' : 'IMPAGA';
                        $this->line("     • {$inv->number} - <fg={$statusColor}>{$statusText}</> - {$inv->invoice_created_at->format('Y-m-d')}");
                    }
                }

                $this->newLine();
            }
        }

        if ($incorrectCount === 0)
        {
            $this->info('✅ No se encontraron notificaciones incorrectas');
        }
        else
        {
            $this->newLine();
            $this->error("❌ Se encontraron {$incorrectCount} notificaciones incorrectas");
            $this->newLine();

            $this->info('📋 POSIBLES CAUSAS:');
            $this->line('  1. Las facturas se pagaron DESPUÉS de enviar la notificación');
            $this->line('  2. No se sincronizaron las facturas desde Stripe (ejecutar: invoices:sync)');
            $this->line('  3. Los webhooks de Stripe no están funcionando correctamente');
            $this->newLine();

            $this->info('💡 RECOMENDACIONES:');
            $this->line('  • Ejecutar: php artisan notifications:find-incorrect --sync');
            $this->line('  • Verificar configuración de webhooks en Stripe Dashboard');
            $this->line('  • Agregar sincronización automática en el scheduler');
        }

        return self::SUCCESS;
    }
}
