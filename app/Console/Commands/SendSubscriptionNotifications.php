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

        // 🔄 IMPORTANTE: Sincronizar facturas ANTES de procesar notificaciones
        // para asegurarnos de tener el estado más actualizado desde Stripe
        $this->info('🔄 Sincronizando facturas desde Stripe...');
        $this->call('invoices:sync');
        $this->newLine();

        $this->scheduleWarningNotifications();
        $this->sendPendingNotifications();

        $this->info('✅ Proceso completado');
        return self::SUCCESS;
    }

    /**
     * Programa notificaciones de advertencia y suspensiones automáticas
     * basándose en los días transcurridos de la factura más antigua
     *
     * Timeline:
     * - Día 0: Factura generada
     * - Día 10: Factura vence
     * - Día 40-42: Aviso "Faltan 5 días para suspender"
     * - Día 43-44: Aviso "Faltan 2 días para suspender"
     * - Día 45+: Suspensión automática (si auto_suspend = true)
     *
     * NOTA: La cantidad de facturas impagas NO importa.
     * Solo se evalúa el tiempo transcurrido de la factura más antigua.
     */
    private function scheduleWarningNotifications(): void
    {
        $this->info('📅 Programando advertencias...');

        // Suscripciones con cobro vigente o atrasado en Stripe (el servicio puede seguir activo o pausado)
        $subscriptions = Subscription::query()
            ->where('type', 'sell')
            ->whereIn('stripe_status', ['active', 'past_due', 'unpaid'])
            ->whereNotNull('current_period_end')
            ->get();

        $scheduled = 0;

        foreach ($subscriptions as $subscription) {
            // Contar facturas impagas de esta suscripción
            $unpaidInvoicesCount = \App\Models\Invoice::where('stripe_subscription_id', $subscription->stripe_id)
                ->where('status', 'open')
                ->where('paid', false)
                ->whereNotNull('invoice_created_at')
                ->count();

            // Si no tiene ninguna factura impaga, skip
            if ($unpaidInvoicesCount === 0) {
                continue;
            }

            // Obtener la factura impaga MÁS ANTIGUA
            $oldestUnpaidInvoice = \App\Models\Invoice::where('stripe_subscription_id', $subscription->stripe_id)
                ->where('status', 'open')
                ->where('paid', false)
                ->whereNotNull('invoice_created_at')
                ->orderBy('invoice_created_at', 'asc')
                ->first();

            if (!$oldestUnpaidInvoice) {
                continue;
            }

            // Calcular días desde la generación de la factura más antigua
            $daysSinceInvoiceCreated = $oldestUnpaidInvoice->invoice_created_at->diffInDays(now(), false);

            // ═══════════════════════════════════════════════════════════════
            // NOTIFICACIONES DE WARNING: Basadas en días de la factura más antigua
            // ═══════════════════════════════════════════════════════════════

            // Aviso 1: A los 40 días de generada la factura (30 días post-vencimiento)
            if ($daysSinceInvoiceCreated >= 40 && $daysSinceInvoiceCreated < 43) {
                if (! $this->notificationExists($subscription, 'warning_5_days', $oldestUnpaidInvoice->invoice_created_at)) {
                    SubscriptionNotification::create([
                        'subscription_id' => $subscription->id,
                        'notification_type' => 'warning_5_days',
                        'status' => 'pending',
                        'scheduled_at' => now(),
                        'recipient_email' => $subscription->customer_email,
                        'recipient_name' => $subscription->customer_name,
                        'body' => '', // Se llenará al enviar
                    ]);
                    $scheduled++;
                    $this->line("  → Programado aviso 5 días para {$subscription->customer_name} (factura: {$oldestUnpaidInvoice->number}, {$daysSinceInvoiceCreated} días)");
                }
            }

            // Aviso 2: A los 43 días de generada la factura (33 días post-vencimiento)
            if ($daysSinceInvoiceCreated >= 43 && $daysSinceInvoiceCreated < 45) {
                if (! $this->notificationExists($subscription, 'warning_2_days', $oldestUnpaidInvoice->invoice_created_at)) {
                    SubscriptionNotification::create([
                        'subscription_id' => $subscription->id,
                        'notification_type' => 'warning_2_days',
                        'status' => 'pending',
                        'scheduled_at' => now(),
                        'recipient_email' => $subscription->customer_email,
                        'recipient_name' => $subscription->customer_name,
                        'body' => '', // Se llenará al enviar
                    ]);
                    $scheduled++;
                    $this->line("  → Programado aviso 2 días para {$subscription->customer_name} (factura: {$oldestUnpaidInvoice->number}, {$daysSinceInvoiceCreated} días)");
                }
            }

            // ═══════════════════════════════════════════════════════════════
            // SUSPENSIÓN AUTOMÁTICA: Si tiene factura con 45+ días
            // (independiente de la cantidad de facturas)
            // ═══════════════════════════════════════════════════════════════
            if ($daysSinceInvoiceCreated >= 45) {
                $autoSuspend = data_get($subscription->data, 'auto_suspend', false);

                if ($autoSuspend && $subscription->status === 'active') {
                    $this->suspendSubscription($subscription, $unpaidInvoicesCount);
                    $scheduled++;
                    $this->line("  → Suspendida {$subscription->customer_name} (factura más antigua: {$oldestUnpaidInvoice->number}, {$daysSinceInvoiceCreated} días)");
                }
            }
        }

        $this->info("  → {$scheduled} notificaciones/acciones programadas");
    }

    /**
     * Suspende una suscripción automáticamente
     */
    private function suspendSubscription(Subscription $subscription, int $unpaidInvoicesCount): void
    {
        try {
            $server = data_get($subscription->data, 'server');
            $user = data_get($subscription->data, 'user');
            $whmSuspended = false;
            $stripePaused = false;

            // 1. Suspender cuenta WHM
            if (filled($server) && filled($user)) {
                try {
                    app(\App\Services\WHM\WHMServerManager::class)
                        ->suspendAccount($server, $user, "Suspendido automáticamente: {$unpaidInvoicesCount} facturas impagas (45 días desde la más antigua)");
                    $whmSuspended = true;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to suspend WHM account', [
                        'subscription_id' => $subscription->id,
                        'server' => $server,
                        'user' => $user,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 2. Pausar suscripción en Stripe
            if (!str_starts_with($subscription->stripe_id, 'manual-')) {
                try {
                    $stripe = app(\Stripe\StripeClient::class);
                    $stripe->subscriptions->update(
                        $subscription->stripe_id,
                        [
                            'pause_collection' => [
                                'behavior' => 'mark_uncollectible', // No intenta cobrar mientras está pausado
                            ],
                        ]
                    );
                    $stripePaused = true;

                    \Illuminate\Support\Facades\Log::info('Stripe subscription paused', [
                        'subscription_id' => $subscription->id,
                        'stripe_id' => $subscription->stripe_id,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to pause Stripe subscription', [
                        'subscription_id' => $subscription->id,
                        'stripe_id' => $subscription->stripe_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 3. Actualizar estado en BD
            $subscription->update(['status' => 'paused']);

            // 4. Crear notificación de suspensión
            SubscriptionNotification::create([
                'subscription_id' => $subscription->id,
                'notification_type' => 'suspended',
                'status' => 'pending',
                'scheduled_at' => now(),
                'recipient_email' => $subscription->customer_email,
                'recipient_name' => $subscription->customer_name,
                'body' => '',
            ]);

            $this->line("  → Suspendida para {$subscription->customer_name} (WHM: " . ($whmSuspended ? 'SI' : 'NO') . ", Stripe: " . ($stripePaused ? 'SI' : 'NO') . ")");
        } catch (\Throwable $e) {
            $this->error("  ✗ Error al suspender {$subscription->customer_name}: {$e->getMessage()}");
        }
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
                    // Renderizar el HTML del email
                    $htmlBody = $mailable->render();

                    // Agregar el pixel de tracking ANTES de enviar
                    $trackingPixel = '<img src="' . $notification->getTrackingUrl() . '" width="1" height="1" border="0" style="display: block; width: 1px; height: 1px;" alt="" />';
                    $htmlBodyWithPixel = str_replace('</body>', $trackingPixel . '</body>', $htmlBody);

                    // Obtener el subject del mailable
                    $subject = $mailable->envelope()->subject;

                    // Enviar el email CON el pixel incluido al cliente
                    Mail::send([], [], function ($message) use ($notification, $htmlBodyWithPixel, $subject) {
                        $message->to($notification->recipient_email, $notification->recipient_name)
                            ->subject($subject)
                            ->html($htmlBodyWithPixel);
                    });

                    // 📧 Si es una suspensión, enviar copia al admin SIN tracking
                    if ($notification->notification_type === 'suspended') {
                        $adminEmail = config('mail.from.address');
                        $adminName = config('mail.from.name', 'Admin');

                        if (filled($adminEmail)) {
                            try {
                                // Enviar copia SIN el pixel de tracking (HTML original)
                                Mail::send([], [], function ($message) use ($htmlBody, $subject, $adminEmail, $adminName, $notification) {
                                    $message->to($adminEmail, $adminName)
                                        ->subject("[COPIA] {$subject} - {$notification->recipient_name}")
                                        ->html($htmlBody); // Sin tracking pixel
                                });

                                $this->line("    ↳ Copia enviada a admin: {$adminEmail}");
                            } catch (\Throwable $e) {
                                $this->warn("    ⚠️  No se pudo enviar copia a admin: {$e->getMessage()}");
                            }
                        }
                    }

                    // Guardar el HTML con pixel y marcar como enviado
                    $notification->update([
                        'body' => $htmlBodyWithPixel,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

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
     * Verifica si ya existe una notificación para esta suscripción en este ciclo de facturación
     */
    private function notificationExists(Subscription $subscription, string $type, ?\Carbon\Carbon $invoiceCreatedAt = null): bool
    {
        $query = SubscriptionNotification::where('subscription_id', $subscription->id)
            ->where('notification_type', $type);

        // Si tenemos fecha de factura, verificar que no exista notificación desde esa fecha
        if ($invoiceCreatedAt) {
            $query->where('created_at', '>=', $invoiceCreatedAt);
        } else {
            // Fallback: usar el inicio del período actual
            $query->where('created_at', '>=', $subscription->current_period_start);
        }

        return $query->exists();
    }
}
