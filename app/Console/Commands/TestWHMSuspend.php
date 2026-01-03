<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\WHM\WHMServerManager;
use Illuminate\Console\Command;

class TestWHMSuspend extends Command
{
    protected $signature = 'whm:test-suspend {subscription : ID de la suscripción}';
    protected $description = 'Prueba suspender/reactivar una cuenta WHM';

    public function __construct(
        private readonly WHMServerManager $whm
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $subscriptionId = $this->argument('subscription');
        $subscription = Subscription::find($subscriptionId);

        if (!$subscription) {
            $this->error("❌ No se encontró la suscripción #{$subscriptionId}");
            return self::FAILURE;
        }

        $server = data_get($subscription->data, 'server');
        $user = data_get($subscription->data, 'user');

        if (!$server || !$user) {
            $this->error("❌ La suscripción no tiene datos de servidor/usuario configurados");
            $this->line("Server: " . ($server ?? 'N/A'));
            $this->line("User: " . ($user ?? 'N/A'));
            return self::FAILURE;
        }

        $this->info("📋 Información de la suscripción:");
        $this->line("  Cliente: {$subscription->customer_name}");
        $this->line("  Plan: {$subscription->plan_name}");
        $this->line("  Servidor: {$server}");
        $this->line("  Usuario cPanel: {$user}");
        $this->line("  Auto-suspend: " . (data_get($subscription->data, 'auto_suspend') ? '✅ Sí' : '❌ No'));
        $this->newLine();

        $action = $this->choice(
            '¿Qué acción deseas realizar?',
            ['suspend' => '❌ Suspender cuenta', 'unsuspend' => '✅ Reactivar cuenta', 'cancel' => 'Cancelar'],
            'cancel'
        );

        if ($action === 'cancel') {
            $this->info('Operación cancelada.');
            return self::SUCCESS;
        }

        try {
            if ($action === 'suspend') {
                $this->info("⏳ Suspendiendo cuenta {$user} en {$server}...");
                $result = $this->whm->suspendAccount($server, $user, 'Test manual desde comando');
                
                if ($result) {
                    $this->info("✅ Cuenta suspendida exitosamente");
                } else {
                    $this->error("❌ La suspensión falló (revisa los logs)");
                }
            } else {
                $this->info("⏳ Reactivando cuenta {$user} en {$server}...");
                $result = $this->whm->unsuspendAccount($server, $user);
                
                if ($result) {
                    $this->info("✅ Cuenta reactivada exitosamente");
                } else {
                    $this->error("❌ La reactivación falló (revisa los logs)");
                }
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->line("📍 " . $e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        }
    }
}
