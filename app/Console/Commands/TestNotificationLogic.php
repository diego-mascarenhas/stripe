<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Carbon\Carbon;

class TestNotificationLogic extends Command
{
    protected $signature = 'test:notification-logic {subscription_id?}';
    protected $description = 'Prueba la lógica de notificaciones con datos reales';

    public function handle(): int
    {
        $this->info('🧪 Probando lógica de notificaciones...');
        $this->newLine();

        $subscriptionId = $this->argument('subscription_id');

        if ($subscriptionId) {
            $subscriptions = Subscription::where('id', $subscriptionId)->get();
        } else {
            $subscriptions = Subscription::where('status', 'active')
                ->whereNotNull('current_period_end')
                ->get();
        }

        if ($subscriptions->isEmpty()) {
            $this->warn('No hay suscripciones activas para probar.');
            return 0;
        }

        foreach ($subscriptions as $subscription) {
            $this->testSubscription($subscription);
            $this->newLine();
        }

        return 0;
    }

    private function testSubscription(Subscription $subscription): void
    {
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📋 Suscripción: {$subscription->customer_name} (ID: {$subscription->id})");
        $this->line("   Stripe ID: {$subscription->stripe_id}");
        $this->line("   Estado: {$subscription->status}");
        $this->newLine();

        // Contar facturas impagas
        $unpaidInvoices = Invoice::where('stripe_subscription_id', $subscription->stripe_id)
            ->where('status', 'open')
            ->where('paid', false)
            ->whereNotNull('invoice_created_at')
            ->orderBy('invoice_created_at', 'asc')
            ->get();

        $unpaidCount = $unpaidInvoices->count();

        $this->line("📊 <fg=cyan>Facturas impagas: {$unpaidCount}</>");

        if ($unpaidCount === 0) {
            $this->info("   ✅ No hay facturas impagas. No se enviarán avisos.");
            return;
        }

        // Mostrar todas las facturas impagas
        foreach ($unpaidInvoices as $invoice) {
            $daysOld = $invoice->invoice_created_at->diffInDays(now(), false);
            $daysDue = $invoice->invoice_due_date ? $invoice->invoice_due_date->diffInDays(now(), false) : null;
            
            $this->line("   • Factura: <fg=yellow>{$invoice->number}</>");
            $this->line("     - Generada: {$invoice->invoice_created_at->format('Y-m-d')} ({$daysOld} días)");
            if ($invoice->invoice_due_date) {
                $this->line("     - Vence: {$invoice->invoice_due_date->format('Y-m-d')} ({$daysDue} días vencida)");
            }
            $this->line("     - Monto: {$invoice->currency} " . number_format($invoice->amount_remaining, 2));
        }

        $this->newLine();

        // Lógica principal: solo si tiene 2 o más facturas impagas
        if ($unpaidCount < 2) {
            $this->warn("   ⚠️  Solo tiene {$unpaidCount} factura(s) impaga(s).");
            $this->line("   ℹ️  Se requieren 2 facturas impagas para activar avisos.");
            return;
        }

        $this->info("   ✅ Tiene {$unpaidCount} facturas impagas. Procesando...");
        $this->newLine();

        // Obtener la factura más antigua
        $oldestInvoice = $unpaidInvoices->first();
        $daysSinceCreated = $oldestInvoice->invoice_created_at->diffInDays(now(), false);

        $this->line("🎯 <fg=magenta>Factura más antigua: {$oldestInvoice->number}</>");
        $this->line("   - Generada: {$oldestInvoice->invoice_created_at->format('Y-m-d H:i:s')}");
        $this->line("   - Días transcurridos: <fg=cyan>{$daysSinceCreated}</>");
        $this->newLine();

        // Evaluar qué avisos corresponden
        $this->line("🔍 <fg=white;options=bold>Evaluación de avisos:</>");
        
        // Aviso 5 días (día 40-42)
        if ($daysSinceCreated >= 40 && $daysSinceCreated < 43) {
            $this->line("   🚨 <fg=yellow;options=bold>AVISO 5 DÍAS</> → SE DEBE ENVIAR");
            $this->line("      Rango: días 40-42 (Actual: {$daysSinceCreated})");
        } elseif ($daysSinceCreated < 40) {
            $daysUntil40 = 40 - $daysSinceCreated;
            $this->line("   ⏳ Aviso 5 días → Faltan {$daysUntil40} días para activarse");
        } else {
            $this->line("   ✓ Aviso 5 días → Ya pasó (día {$daysSinceCreated})");
        }

        // Aviso 2 días (día 43-44)
        if ($daysSinceCreated >= 43 && $daysSinceCreated < 45) {
            $this->line("   🚨 <fg=red;options=bold>AVISO 2 DÍAS</> → SE DEBE ENVIAR");
            $this->line("      Rango: días 43-44 (Actual: {$daysSinceCreated})");
        } elseif ($daysSinceCreated < 43) {
            $daysUntil43 = 43 - $daysSinceCreated;
            $this->line("   ⏳ Aviso 2 días → Faltan {$daysUntil43} días para activarse");
        } else {
            $this->line("   ✓ Aviso 2 días → Ya pasó (día {$daysSinceCreated})");
        }

        // Suspensión (día 45+)
        $autoSuspend = data_get($subscription->data, 'auto_suspend', false);
        
        if ($daysSinceCreated >= 45) {
            if ($autoSuspend) {
                $this->line("   ⛔ <fg=red;options=bold>SUSPENSIÓN</> → SE DEBE EJECUTAR");
                $this->line("      Auto-suspensión: ACTIVADA");
            } else {
                $this->line("   ⚠️  SUSPENSIÓN → NO SE EJECUTA (auto_suspend: false)");
            }
        } elseif ($daysSinceCreated < 45) {
            $daysUntil45 = 45 - $daysSinceCreated;
            $this->line("   ⏳ Suspensión → Faltan {$daysUntil45} días");
            $this->line("      Auto-suspensión: " . ($autoSuspend ? 'ACTIVADA' : 'DESACTIVADA'));
        }

        $this->newLine();

        // Timeline visual
        $this->line("📅 <fg=white;options=bold>Timeline:</>");
        $this->line("   Día  0: Factura generada");
        $this->line("   Día 10: Factura vence");
        $this->line("   Día 40: ← Aviso 5 días (30 días post-vencimiento)");
        $this->line("   Día 43: ← Aviso 2 días (33 días post-vencimiento)");
        $this->line("   Día 45: ← Suspensión (35 días post-vencimiento)");
        $this->line("   <fg=cyan;options=bold>Día {$daysSinceCreated}: ← ESTÁS AQUÍ</>");
    }
}

