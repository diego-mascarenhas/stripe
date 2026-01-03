<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionReactivatedMail;
use App\Mail\SubscriptionSuspendedMail;
use App\Mail\SubscriptionWarningMail;
use App\Models\Subscription;
use App\Models\SubscriptionNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionNotifications extends Command
{
    protected $signature = 'subscriptions:send-notifications';
    protected $description = 'Envía notificaciones automáticas sobre suscripciones próximas a vencer o suspendidas';

    public function handle(): int
    {
        $this->info('Iniciando envío de notificaciones...');

        $this->scheduleWarningNotifications();
        $this->sendPendingNotifications();

        $this->info('✅ Proceso completado');
        return self::SUCCESS;
    }

    /**
     * Programa notificaciones de advertencia para suscripciones próximas a vencer
     */
    private function scheduleWarningNotifications(): void
    {
        $this->info('📅 Programando advertencias...');

        // TODAS las suscripciones activas (sin importar auto_suspend)
        $subscriptions = Subscription::where('status', 'active')
            ->whereNotNull('current_period_end')
            ->get();

        $scheduled = 0;

        foreach ($subscriptions as $subscription) {
            $daysUntilDue = now()->diffInDays($subscription->current_period_end, false);

            // Aviso 5 días antes
            if ($daysUntilDue <= 5 && $daysUntilDue > 2) {
                if (! $this->notificationExists($subscription, 'warning_5_days')) {
                    SubscriptionNotification::create([
                        'subscription_id' => $subscription->id,
                        'notification_type' => 'warning_5_days',
                        'status' => 'pending',
                        'scheduled_at' => now(),
                        'recipient_email' => $subscription->customer_email,
                        'recipient_name' => $subscription->customer_name,
                    ]);
                    $scheduled++;
                }
            }

            // Aviso 2 días antes
            if ($daysUntilDue <= 2 && $daysUntilDue > 0) {
                if (! $this->notificationExists($subscription, 'warning_2_days')) {
                    SubscriptionNotification::create([
                        'subscription_id' => $subscription->id,
                        'notification_type' => 'warning_2_days',
                        'status' => 'pending',
                        'scheduled_at' => now(),
                        'recipient_email' => $subscription->customer_email,
                        'recipient_name' => $subscription->customer_name,
                    ]);
                    $scheduled++;
                }
            }
        }

        $this->info("  → {$scheduled} notificaciones programadas");
    }

    /**
     * Envía notificaciones pendientes
     */
    private function sendPendingNotifications(): void
    {
        $this->info('📧 Enviando notificaciones pendientes...');

        $notifications = SubscriptionNotification::with('subscription')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($notifications as $notification) {
            try {
                $mailable = $this->getMailable($notification);

                if ($mailable) {
                    Mail::to($notification->recipient_email)
                        ->send($mailable);

                    $notification->markAsSent();
                    $sent++;
                    $this->line("  ✓ Enviado: {$notification->getTypeLabel()} a {$notification->recipient_email}");
                }
            } catch (\Throwable $e) {
                $notification->markAsFailed($e->getMessage());
                $failed++;
                $this->error("  ✗ Error: {$notification->recipient_email} - {$e->getMessage()}");
            }
        }

        $this->info("  → {$sent} enviados, {$failed} fallidos");
    }

    /**
     * Obtiene el Mailable correspondiente
     */
    private function getMailable(SubscriptionNotification $notification): ?object
    {
        return match ($notification->notification_type) {
            'warning_5_days' => new SubscriptionWarningMail($notification->subscription, 5),
            'warning_2_days' => new SubscriptionWarningMail($notification->subscription, 2),
            'suspended' => new SubscriptionSuspendedMail($notification->subscription),
            'reactivated' => new SubscriptionReactivatedMail($notification->subscription),
            default => null,
        };
    }

    /**
     * Verifica si ya existe una notificación para esta suscripción
     */
    private function notificationExists(Subscription $subscription, string $type): bool
    {
        return SubscriptionNotification::where('subscription_id', $subscription->id)
            ->where('notification_type', $type)
            ->where('created_at', '>=', $subscription->current_period_start)
            ->exists();
    }
}
