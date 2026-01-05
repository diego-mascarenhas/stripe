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
        $this->info('🧪 Iniciando prueba de notificaciones...');
        $this->newLine();

        // Mostrar configuración actual
        $this->line("🔍 Base de datos: " . config('database.connections.' . config('database.default') . '.database'));
        $this->line("📧 Mail driver: " . config('mail.default'));
        
        if (config('mail.default') === 'smtp') {
            $this->line("📧 SMTP: " . config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port'));
        }
        
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
                // VALIDACIÓN: "reactivated" solo si está actualmente suspendido (paused)
                if ($type === 'reactivated' && $subscription->status !== 'paused') {
                    $this->warn("  ⚠️  Omitiendo notificación 'reactivated': la suscripción no está suspendida");
                    $this->line("      Estado actual: {$subscription->status} (debe ser 'paused' para enviar reactivación)");
                    continue;
                }

                // VALIDACIÓN: "suspended" solo si NO está ya suspendido
                if ($type === 'suspended' && in_array($subscription->status, ['paused', 'canceled'])) {
                    $this->warn("  ⚠️  Omitiendo notificación 'suspended': la suscripción ya está suspendida/cancelada (status: {$subscription->status})");
                    continue;
                }

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

            // Renderizar el HTML del email ANTES de enviar
            $htmlBody = $mailable->render();

            // Enviar el email
            Mail::to($subscription->customer_email)->send($mailable);
            $this->line("  📧 Email enviado");

            // Crear notificación con el body
            $notification = SubscriptionNotification::create([
                'subscription_id' => $subscription->id,
                'notification_type' => $type,
                'status' => 'pending',
                'scheduled_at' => now(),
                'recipient_email' => $subscription->customer_email,
                'recipient_name' => $subscription->customer_name,
                'metadata' => ['test' => true],
            ]);

            // Marcar como enviado con el body HTML
            $notification->markAsSent($htmlBody);

            $typeLabel = match($type) {
                'warning_5_days' => '⚠️  Aviso 5 días',
                'warning_2_days' => '🚨 Aviso 2 días',
                'suspended' => '❌ Suspendido',
                'reactivated' => '✅ Reactivado',
                default => $type,
            };

            $bodyLength = strlen($htmlBody);
            $this->line("  ✓ {$typeLabel} enviado a {$subscription->customer_email}");
            $this->line("  📝 Body guardado: {$bodyLength} caracteres");

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
