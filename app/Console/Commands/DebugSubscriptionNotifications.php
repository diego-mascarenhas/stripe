<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\SubscriptionNotification;
use Illuminate\Console\Command;

class DebugSubscriptionNotifications extends Command
{
    protected $signature = 'subscriptions:debug-notifications {search?}';
    protected $description = 'Debug why a subscription received notifications';

    public function handle(): int
    {
        $search = $this->argument('search');

        if (!$search)
        {
            $this->error('Por favor proporciona un criterio de búsqueda (customer_id, email o nombre)');
            return self::FAILURE;
        }

        // Buscar por customer_id, email o nombre
        $subscription = Subscription::where('customer_id', $search)
            ->orWhere('customer_email', 'like', "%{$search}%")
            ->orWhere('customer_name', 'like', "%{$search}%")
            ->first();

        if (!$subscription)
        {
            $this->error("No se encontró suscripción con: {$search}");
            return self::FAILURE;
        }

        $this->info("═══════════════════════════════════════════════════════");
        $this->info("ANÁLISIS DE NOTIFICACIONES - {$subscription->customer_name}");
        $this->info("═══════════════════════════════════════════════════════");
        $this->newLine();

        // Datos de la suscripción
        $this->line("<fg=cyan>📋 DATOS DE LA SUSCRIPCIÓN</>");
        $this->line("ID: {$subscription->id}");
        $this->line("Cliente: {$subscription->customer_name}");
        $this->line("Email: {$subscription->customer_email}");
        $this->line("Customer ID: {$subscription->customer_id}");
        $this->line("Status: <fg=yellow>{$subscription->status}</>");
        $this->line("Stripe ID: {$subscription->stripe_id}");
        $this->newLine();

        // Facturas impagas
        $unpaidInvoices = Invoice::where('stripe_subscription_id', $subscription->stripe_id)
            ->where('status', 'open')
            ->where('paid', false)
            ->whereNotNull('invoice_created_at')
            ->orderBy('invoice_created_at', 'asc')
            ->get();

        $this->line("<fg=cyan>💰 FACTURAS IMPAGAS</> (" . $unpaidInvoices->count() . ")");
        if ($unpaidInvoices->isEmpty())
        {
            $this->line("<fg=green>  ✓ No tiene facturas impagas</>");
        }
        else
        {
            foreach ($unpaidInvoices as $invoice)
            {
                $daysOld = $invoice->invoice_created_at->diffInDays(now());
                $this->line("  • {$invoice->number}");
                $this->line("    Creada: {$invoice->invoice_created_at->format('Y-m-d H:i:s')}");
                $this->line("    Días desde creación: <fg=yellow>{$daysOld} días</>");
                $this->line("    Monto: {$invoice->amount_total} {$invoice->currency}");
            }
        }
        $this->newLine();

        // Lógica de notificación y suspensión
        if ($unpaidInvoices->isNotEmpty())
        {
            $oldestInvoice = $unpaidInvoices->first();
            $daysOld = $oldestInvoice->invoice_created_at->diffInDays(now());
            $autoSuspend = data_get($subscription->data, 'auto_suspend', false);

            $this->line("<fg=cyan>📅 EVALUACIÓN BASADA EN FACTURA MÁS ANTIGUA</>");
            $this->line("Factura: {$oldestInvoice->number}");
            $this->line("Días desde creación: <fg=yellow>{$daysOld} días</>");
            $this->newLine();

            // Ventanas de notificación
            $this->line("<fg=cyan>🔔 NOTIFICACIONES:</>");

            if ($daysOld >= 40 && $daysOld < 43)
            {
                $this->line("  • <fg=red>ACTIVA: Aviso 5 días</> (40-42 días) ← <fg=yellow>ESTÁ AQUÍ</>");
            }
            else
            {
                $status = $daysOld < 40 ? "Faltan " . (40 - $daysOld) . " días" : "Pasó hace " . ($daysOld - 42) . " días";
                $this->line("  • Aviso 5 días (40-42 días): {$status}");
            }

            if ($daysOld >= 43 && $daysOld < 45)
            {
                $this->line("  • <fg=red>ACTIVA: Aviso 2 días</> (43-44 días) ← <fg=yellow>ESTÁ AQUÍ</>");
            }
            else
            {
                $status = $daysOld < 43 ? "Faltan " . (43 - $daysOld) . " días" : "Pasó hace " . ($daysOld - 44) . " días";
                $this->line("  • Aviso 2 días (43-44 días): {$status}");
            }

            $this->newLine();

            // Suspensión
            $this->line("<fg=cyan>⚙️  SUSPENSIÓN AUTOMÁTICA:</>");
            $this->line("Auto-suspend habilitado: " . ($autoSuspend ? '<fg=green>SÍ</>' : '<fg=red>NO</>'));

            if ($daysOld >= 45)
            {
                $this->line("  • <fg=red>ACTIVA: Suspensión automática</> (45+ días) ← <fg=yellow>ESTÁ AQUÍ</>");
                if ($autoSuspend && $subscription->status === 'active')
                {
                    $this->line("  • <fg=red>⚠️  Este servicio DEBERÍA estar suspendido</>");
                }
                elseif (!$autoSuspend)
                {
                    $this->line("  • <fg=yellow>ℹ️  No se suspende (auto_suspend = false)</>");
                }
                elseif ($subscription->status !== 'active')
                {
                    $this->line("  • <fg=green>✓ Ya está suspendido/pausado (status: {$subscription->status})</>");
                }
            }
            else
            {
                $daysRemaining = 45 - $daysOld;
                $this->line("  • Suspensión automática (45+ días): Faltan {$daysRemaining} días");
            }
        }
        else
        {
            $this->line("<fg=green>✓ No tiene facturas impagas</>");
            $this->line("  No aplican notificaciones ni suspensiones");
        }
        $this->newLine();

        // Notificaciones enviadas
        $notifications = SubscriptionNotification::where('subscription_id', $subscription->id)
            ->latest()
            ->get();

        $this->line("<fg=cyan>📧 NOTIFICACIONES ENVIADAS</> (" . $notifications->count() . ")");
        if ($notifications->isEmpty())
        {
            $this->line("  No hay notificaciones");
        }
        else
        {
            foreach ($notifications as $notif)
            {
                $icon = $notif->status === 'sent' ? '✓' : '✗';
                $color = $notif->status === 'sent' ? 'green' : 'red';
                $this->line("  <fg={$color}>{$icon}</> {$notif->getTypeLabel()}");
                $this->line("    Status: {$notif->status}");
                $this->line("    Programada: {$notif->scheduled_at?->format('Y-m-d H:i:s')}");
                if ($notif->sent_at)
                {
                    $this->line("    Enviada: {$notif->sent_at->format('Y-m-d H:i:s')}");
                }
                if ($notif->opened_at)
                {
                    $this->line("    Abierta: {$notif->opened_at->format('Y-m-d H:i:s')} ({$notif->open_count} veces)");
                }
            }
        }
        $this->newLine();

        $this->info("═══════════════════════════════════════════════════════");

        return self::SUCCESS;
    }
}
