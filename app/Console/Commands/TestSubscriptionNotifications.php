<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionReactivatedMail;
use App\Mail\SubscriptionSuspendedMail;
use App\Mail\SubscriptionWarningMail;
use App\Models\Subscription;
use App\Models\SubscriptionNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSubscriptionNotifications extends Command
{
    protected $signature = 'subscriptions:test-notifications {--subscription= : ID de suscripción específica} {--auto : Enviar todas las notificaciones automáticamente sin preguntar}';
    protected $description = 'Comando de prueba para enviar notificaciones de ejemplo sin modificar fechas reales';

    public function handle(): int
    {
        // FORZAR configuración MySQL para testing
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'stripe',
            'database.connections.mysql.password' => 'Passw0rd!',
        ]);

        // FORZAR configuración de mail para testing
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1025,
            'mail.mailers.smtp.encryption' => null,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
        ]);

        $this->info('🧪 Iniciando prueba de notificaciones...');
        $this->newLine();

        // Primero verificar conexión
        $this->line("🔍 Base de datos: " . config('database.connections.mysql.database'));
        $this->line("📧 Mail driver: " . config('mail.default'));
        $this->line("📧 SMTP host: " . config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port'));
        $this->newLine();

        $subscriptionId = $this->option('subscription');

        if ($subscriptionId) {
            $subscription = Subscription::find($subscriptionId);
            if (!$subscription) {
                $this->error("❌ No se encontró la suscripción con ID {$subscriptionId}");
                $this->line("Total de suscripciones en DB: " . Subscription::count());
                return self::FAILURE;
            }
            $subscriptions = collect([$subscription]);
        } else {
            // Obtener TODAS las suscripciones (sin filtrar por auto_suspend para testing)
            $all = Subscription::all();
            $this->info("📊 Total suscripciones en DB: {$all->count()}");

            if ($all->isEmpty()) {
                $this->error("❌ No hay suscripciones en la base de datos.");
                $this->newLine();
                $this->warn("Verifica que tu .env apunte a la BD correcta:");
                $this->line("  DB_CONNECTION=mysql");
                $this->line("  DB_DATABASE=stripe");
                return self::FAILURE;
            }

            // Mostrar resumen
            $withAutoSuspend = $all->filter(fn($s) => data_get($s->data, 'auto_suspend') === true)->count();
            $this->line("  ✓ Con auto_suspend: {$withAutoSuspend}");
            $this->line("  ✓ Sin auto_suspend: " . ($all->count() - $withAutoSuspend));
            $this->newLine();

            $subscriptions = $all;
        }

        if ($subscriptions->isEmpty()) {
            $this->warn('⚠️  No hay suscripciones disponibles para testing.');
            return self::SUCCESS;
        }

        $this->info("📋 Suscripciones encontradas: {$subscriptions->count()}");
        $this->newLine();

        foreach ($subscriptions as $subscription) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("👤 Cliente: {$subscription->customer_name}");
            $this->line("📧 Email: {$subscription->customer_email}");
            $this->line("📦 Plan: {$subscription->plan_name}");
            $this->line("🔄 Estado: {$subscription->status}");
            $this->line("📅 Vencimiento: {$subscription->current_period_end->format('d/m/Y H:i')}");
            $this->line("🔔 Auto-suspend: " . (data_get($subscription->data, 'auto_suspend') ? '✅ Sí' : '❌ No'));
            $this->newLine();

            // Si se pasa --auto, enviar todas automáticamente
            if ($this->option('auto')) {
                $choice = '5';
                $this->line("🤖 Modo automático: enviando todas las notificaciones...");
            } else {
                $choice = $this->choice(
                    '¿Qué tipo de notificación quieres enviar?',
                    [
                        '1' => '⚠️  Aviso 5 días antes',
                        '2' => '🚨 Aviso 2 días antes',
                        '3' => '❌ Servicio suspendido',
                        '4' => '✅ Servicio reactivado',
                        '5' => 'Todas las anteriores',
                        '0' => 'Saltar esta suscripción',
                    ],
                    '0'
                );
            }

            if ($choice === '0') {
                $this->line('⏭️  Saltando...');
                $this->newLine();
                continue;
            }

            $notifications = $this->getNotificationsToSend($choice);

            foreach ($notifications as $type => $days) {
                $this->sendTestNotification($subscription, $type, $days);
            }

            $this->newLine();
        }

        $this->info('✅ Prueba completada. Revisa MailPit en http://localhost:8025');

        return self::SUCCESS;
    }

    private function getNotificationsToSend(string $choice): array
    {
        return match($choice) {
            '1' => ['warning_5_days' => 5],
            '2' => ['warning_2_days' => 2],
            '3' => ['suspended' => null],
            '4' => ['reactivated' => null],
            '5' => [
                'warning_5_days' => 5,
                'warning_2_days' => 2,
                'suspended' => null,
                'reactivated' => null,
            ],
            default => [],
        };
    }

    private function sendTestNotification(Subscription $subscription, string $type, ?int $days): void
    {
        try {
            // Obtener el mailable
            $mailable = $this->getMailable($subscription, $type, $days);

            if (!$mailable) {
                $this->error("  ❌ Tipo de notificación no válido: {$type}");
                return;
            }

            $this->line("  🔄 Enviando email...");

            // Enviar email con logging explícito
            try {
                Mail::to($subscription->customer_email)->send($mailable);
                $this->line("  📧 Mail::send() ejecutado");
            } catch (\Throwable $mailException) {
                $this->error("  ❌ Error en Mail::send(): " . $mailException->getMessage());
                throw $mailException;
            }

            // Renderizar el email para obtener el body DESPUÉS de enviarlo
            try {
                $body = $mailable->render();

                // Crear registro de notificación
                $notification = SubscriptionNotification::create([
                    'subscription_id' => $subscription->id,
                    'notification_type' => $type,
                    'status' => 'sent',
                    'scheduled_at' => now(),
                    'sent_at' => now(),
                    'recipient_email' => $subscription->customer_email,
                    'recipient_name' => $subscription->customer_name,
                    'body' => $body,
                    'metadata' => ['test' => true],
                ]);

                // Agregar tracking pixel al body
                $trackingPixel = '<img src="' . route('notification.track.pixel', ['notification' => $notification->id]) . '" width="1" height="1" border="0" style="display: block; width: 1px; height: 1px" alt="" />';
                $bodyWithTracking = str_replace('</body>', $trackingPixel . '</body>', $body);
                $notification->update(['body' => $bodyWithTracking]);
            } catch (\Throwable $e) {
                $this->warn("  ⚠️  No se pudo guardar el body: " . $e->getMessage());
                // Crear registro básico sin body
                SubscriptionNotification::create([
                    'subscription_id' => $subscription->id,
                    'notification_type' => $type,
                    'status' => 'sent',
                    'scheduled_at' => now(),
                    'sent_at' => now(),
                    'recipient_email' => $subscription->customer_email,
                    'recipient_name' => $subscription->customer_name,
                    'metadata' => ['test' => true],
                ]);
            }

            $typeLabel = match($type) {
                'warning_5_days' => '⚠️  Aviso 5 días',
                'warning_2_days' => '🚨 Aviso 2 días',
                'suspended' => '❌ Suspendido',
                'reactivated' => '✅ Reactivado',
                default => $type,
            };

            $this->line("  ✓ {$typeLabel} enviado a {$subscription->customer_email}");

        } catch (\Throwable $e) {
            $this->error("  ✗ Error al enviar {$type}: " . $e->getMessage());
            $this->error("  📍 Trace: " . $e->getFile() . ':' . $e->getLine());
        }
    }

    private function getMailable(Subscription $subscription, string $type, ?int $days): ?object
    {
        return match ($type) {
            'warning_5_days', 'warning_2_days' => new SubscriptionWarningMail($subscription, $days ?? 5),
            'suspended' => new SubscriptionSuspendedMail($subscription),
            'reactivated' => new SubscriptionReactivatedMail($subscription),
            default => null,
        };
    }
}
