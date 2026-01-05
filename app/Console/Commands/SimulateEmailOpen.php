<?php

namespace App\Console\Commands;

use App\Models\SubscriptionNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateEmailOpen extends Command
{
    protected $signature = 'notifications:simulate-open {notification_id?}';

    protected $description = 'Simula la apertura de un email (para testing en desarrollo)';

    public function handle(): int
    {
        $notificationId = $this->argument('notification_id');

        if ($notificationId)
        {
            return $this->simulateSingleOpen($notificationId);
        }

        return $this->interactiveMode();
    }

    private function interactiveMode(): int
    {
        $this->info('📧 Simulador de Apertura de Emails');
        $this->newLine();

        // Mostrar últimas notificaciones enviadas
        $notifications = SubscriptionNotification::where('status', 'sent')
            ->latest('sent_at')
            ->take(10)
            ->get();

        if ($notifications->isEmpty())
        {
            $this->error('❌ No hay notificaciones enviadas para simular');
            return self::FAILURE;
        }

        $this->table(
            ['ID', 'Tipo', 'Para', 'Enviado', 'Abierto', 'Aperturas'],
            $notifications->map(fn ($n) => [
                $n->id,
                $n->getTypeLabel(),
                $n->recipient_email,
                $n->sent_at?->format('d/m/Y H:i'),
                $n->opened_at ? '✅ ' . $n->opened_at->format('d/m/Y H:i') : '❌ No',
                $n->open_count,
            ])
        );

        $notificationId = $this->ask('¿Qué notificación deseas marcar como abierta? (ID)');

        if (!$notificationId || !is_numeric($notificationId))
        {
            $this->error('❌ ID inválido');
            return self::FAILURE;
        }

        return $this->simulateSingleOpen($notificationId);
    }

    private function simulateSingleOpen(int $notificationId): int
    {
        $notification = SubscriptionNotification::find($notificationId);

        if (!$notification)
        {
            $this->error("❌ Notificación #{$notificationId} no encontrada");
            return self::FAILURE;
        }

        if ($notification->status !== 'sent')
        {
            $this->error('❌ Esta notificación no está en estado "enviado"');
            return self::FAILURE;
        }

        $this->info("🔍 Notificación #{$notification->id}");
        $this->line("   📧 Para: {$notification->recipient_email}");
        $this->line("   🔔 Tipo: {$notification->getTypeLabel()}");
        $this->line("   📅 Enviado: {$notification->sent_at->format('d/m/Y H:i')}");
        $this->newLine();

        if ($notification->opened_at)
        {
            $this->warn("⚠️  Este email ya fue abierto {$notification->open_count} vez/veces");
            $this->line("   Primera apertura: {$notification->opened_at->format('d/m/Y H:i:s')}");
            
            if (!$this->confirm('¿Deseas registrar otra apertura?'))
            {
                return self::SUCCESS;
            }
        }

        // Simular apertura (igual que la ruta de tracking)
        if (!$notification->opened_at)
        {
            DB::table('subscription_notifications')
                ->where('id', $notification->id)
                ->update([
                    'opened_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        DB::table('subscription_notifications')
            ->where('id', $notification->id)
            ->increment('open_count');

        $this->newLine();
        $this->info('✅ Apertura registrada exitosamente');
        
        // Mostrar estado actualizado
        $notification->refresh();
        $this->line("   👀 Abierto: {$notification->opened_at->format('d/m/Y H:i:s')}");
        $this->line("   🔢 Total de aperturas: {$notification->open_count}");
        $this->newLine();
        
        $this->comment("💡 Tip: Verifica en Filament: https://stripe.test/admin/subscription-notifications/{$notification->id}");

        return self::SUCCESS;
    }
}

