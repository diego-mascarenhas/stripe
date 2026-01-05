<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionReactivatedMail;
use App\Mail\SubscriptionSuspendedMail;
use App\Mail\SubscriptionWarningMail;
use App\Models\Subscription;
use App\Models\SubscriptionNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMissedNotifications extends Command
{
    protected $signature = 'subscriptions:send-missed {--force : Enviar sin confirmar}';
    protected $description = 'Envía notificaciones que no se enviaron en su momento (retroactivas)';

    public function handle(): int
    {
        $this->info('🔍 Buscando notificaciones que deberían haberse enviado...');
        $this->newLine();

        // Buscar suscripciones activas o past_due con 2+ facturas impagas
        $subscriptions = Subscription::whereIn('status', ['active', 'past_due'])
            ->whereNotNull('current_period_end')
            ->get();

        $candidates = [];

        foreach ($subscriptions as $subscription) {
            $unpaidInvoices = \App\Models\Invoice::where('stripe_subscription_id', $subscription->stripe_id)
                ->where('status', 'open')
                ->where('paid', false)
                ->whereNotNull('invoice_created_at')
                ->get();

            $unpaidCount = $unpaidInvoices->count();

            if ($unpaidCount >= 2) {
                $oldestInvoice = $unpaidInvoices->sortBy('invoice_created_at')->first();
                $daysSinceCreated = $oldestInvoice->invoice_created_at->diffInDays(now(), false);

                // Solo procesar si ya pasó el día 40 (debería haber recibido notificaciones)
                if ($daysSinceCreated >= 40) {
                    // Verificar qué notificaciones faltan
                    $missing = [];

                    // Aviso 5 días (día 40-42)
                    if ($daysSinceCreated >= 40) {
                        if (!$this->notificationExists($subscription, 'warning_5_days', $oldestInvoice->invoice_created_at)) {
                            $missing[] = ['type' => 'warning_5_days', 'label' => 'Aviso 5 días'];
                        }
                    }

                    // Aviso 2 días (día 43-44)
                    if ($daysSinceCreated >= 43) {
                        if (!$this->notificationExists($subscription, 'warning_2_days', $oldestInvoice->invoice_created_at)) {
                            $missing[] = ['type' => 'warning_2_days', 'label' => 'Aviso 2 días'];
                        }
                    }

                    // Suspensión (día 45+)
                    if ($daysSinceCreated >= 45) {
                        if (!$this->notificationExists($subscription, 'suspended', $oldestInvoice->invoice_created_at)) {
                            $missing[] = ['type' => 'suspended', 'label' => 'Suspendido'];
                        }
                    }

                    if (!empty($missing)) {
                        $candidates[] = [
                            'subscription' => $subscription,
                            'oldest_invoice' => $oldestInvoice,
                            'days_since' => $daysSinceCreated,
                            'unpaid_count' => $unpaidCount,
                            'missing' => $missing,
                        ];
                    }
                }
            }
        }

        if (empty($candidates)) {
            $this->info('✅ No hay notificaciones pendientes de enviar');
            return self::SUCCESS;
        }

        // Mostrar resumen
        $this->warn(sprintf('⚠️  Encontradas %d suscripción(es) con notificaciones faltantes:', count($candidates)));
        $this->newLine();

        foreach ($candidates as $i => $data) {
            $this->line(sprintf('%d. %s (%s)', $i + 1, $data['subscription']->customer_name, $data['subscription']->customer_email));
            $this->line(sprintf('   💳 %d facturas impagas | 📅 %d días desde la más antigua',
                $data['unpaid_count'], $data['days_since']));
            $this->line('   🔔 Notificaciones faltantes:');
            foreach ($data['missing'] as $notif) {
                $this->line(sprintf('      • %s', $notif['label']));
            }
            $this->newLine();
        }

        // Confirmar envío
        if (!$this->option('force')) {
            if (!$this->confirm('¿Deseas crear y enviar estas notificaciones ahora?')) {
                $this->info('Cancelado.');
                return self::SUCCESS;
            }
        }

        // Crear y enviar notificaciones
        $this->newLine();
        $this->info('📧 Creando y enviando notificaciones...');
        $this->newLine();

        $sent = 0;
        $failed = 0;

        foreach ($candidates as $data) {
            $subscription = $data['subscription'];

            foreach ($data['missing'] as $notif) {
                try {
                    // Crear notificación
                    $notification = SubscriptionNotification::create([
                        'subscription_id' => $subscription->id,
                        'notification_type' => $notif['type'],
                        'status' => 'pending',
                        'scheduled_at' => now(),
                        'recipient_email' => $subscription->customer_email,
                        'recipient_name' => $subscription->customer_name,
                        'metadata' => ['retroactive' => true],
                    ]);

                    // Obtener mailable
                    $mailable = $this->getMailable($subscription, $notif['type']);

                    if ($mailable) {
                        // Renderizar HTML
                        $htmlBody = $mailable->render();

                        // Agregar pixel de tracking
                        $trackingPixel = '<img src="' . $notification->getTrackingUrl() . '" width="1" height="1" border="0" style="display: block; width: 1px; height: 1px;" alt="" />';
                        $htmlBodyWithPixel = str_replace('</body>', $trackingPixel . '</body>', $htmlBody);

                        // Obtener subject
                        $subject = $mailable->envelope()->subject;

                        // Enviar email
                        Mail::send([], [], function ($message) use ($subscription, $htmlBodyWithPixel, $subject) {
                            $message->to($subscription->customer_email, $subscription->customer_name)
                                ->subject($subject)
                                ->html($htmlBodyWithPixel);
                        });

                        // Guardar body y marcar como enviado
                        $notification->update([
                            'body' => $htmlBodyWithPixel,
                            'status' => 'sent',
                            'sent_at' => now(),
                        ]);

                        $sent++;
                        $this->line(sprintf('  ✓ %s a %s', $notif['label'], $subscription->customer_email));
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error(sprintf('  ✗ Error: %s - %s', $subscription->customer_email, $e->getMessage()));
                }
            }
        }

        $this->newLine();
        $this->info(sprintf('✅ Proceso completado: %d enviados, %d fallidos', $sent, $failed));

        return self::SUCCESS;
    }

    private function getMailable(Subscription $subscription, string $type): ?object
    {
        return match ($type) {
            'warning_5_days' => new SubscriptionWarningMail($subscription, 5),
            'warning_2_days' => new SubscriptionWarningMail($subscription, 2),
            'suspended' => new SubscriptionSuspendedMail($subscription),
            'reactivated' => new SubscriptionReactivatedMail($subscription),
            default => null,
        };
    }

    private function notificationExists(Subscription $subscription, string $type, ?\Carbon\Carbon $invoiceCreatedAt = null): bool
    {
        $query = SubscriptionNotification::where('subscription_id', $subscription->id)
            ->where('notification_type', $type);

        if ($invoiceCreatedAt) {
            $query->where('created_at', '>=', $invoiceCreatedAt);
        } else {
            $query->where('created_at', '>=', $subscription->current_period_start);
        }

        return $query->exists();
    }
}

