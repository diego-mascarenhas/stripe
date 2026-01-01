<?php

namespace App\Console\Commands;

use App\Services\MercadoPago\MercadoPagoService;
use Illuminate\Console\Command;

class TestMercadoPagoCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mercadopago:test-credentials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba las credenciales de MercadoPago para verificar que funcionan correctamente.';

    /**
     * Execute the console command.
     */
    public function handle(MercadoPagoService $service): int
    {
        $this->info('🔍 Verificando credenciales de MercadoPago...');
        $this->newLine();

        // Check if access token is configured
        $accessToken = config('services.mercadopago.access_token');

        if (empty($accessToken)) {
            $this->error('❌ No se encontró MERCADOPAGO_ACCESS_TOKEN en el archivo .env');
            $this->newLine();
            $this->comment('Agrega la siguiente línea a tu archivo .env:');
            $this->line('MERCADOPAGO_ACCESS_TOKEN=tu_access_token_aqui');
            $this->newLine();

            return self::FAILURE;
        }

        // Check token format
        $this->comment("Access Token encontrado: ".substr($accessToken, 0, 20).'...');
        $this->newLine();

        if (str_starts_with($accessToken, 'TEST-')) {
            $this->warn('⚠️  Estás usando credenciales de PRUEBA (TEST)');
            $this->comment('   Solo verás pagos de prueba, no pagos reales.');
        }
        elseif (str_starts_with($accessToken, 'APP_USR-'))
        {
            $this->info('✓ Estás usando credenciales de PRODUCCIÓN (APP_USR)');
            $this->comment('  Verás pagos reales de tu cuenta.');
        }
        else
        {
            $this->warn('⚠️  Formato de token no reconocido');
            $this->comment('   Debería comenzar con TEST- o APP_USR-');
        }

        $this->newLine();
        $this->info('🔄 Probando conexión con la API de MercadoPago...');
        $this->newLine();

        try
        {
            // Try to fetch recent payments
            $payments = $service->getPayments(['limit' => 1]);

            if ($payments === null || $payments === []) {
                $this->warn('⚠️  La API respondió pero no devolvió pagos');
                $this->newLine();
                $this->comment('Posibles razones:');
                $this->line('  • No tienes pagos en tu cuenta');
                $this->line('  • Estás usando credenciales de TEST sin pagos de prueba');
                $this->line('  • El token no tiene permisos para leer pagos');
                $this->newLine();

                return self::SUCCESS;
            }

            $this->info('✅ ¡Credenciales válidas!');
            $this->newLine();
            $this->comment('Se encontraron pagos en tu cuenta:');
            $this->line('  • Total de pagos consultados: '.count($payments));

            if (! empty($payments)) {
                $payment = $payments[0];
                $this->line('  • Último pago ID: '.($payment['id'] ?? 'N/A'));
                $this->line('  • Fecha: '.($payment['date_created'] ?? 'N/A'));
                $this->line('  • Monto: '.($payment['transaction_amount'] ?? 'N/A').' '.strtoupper($payment['currency_id'] ?? 'N/A'));
                $this->line('  • Estado: '.($payment['status'] ?? 'N/A'));
            }

            $this->newLine();
            $this->info('🚀 Puedes ejecutar la sincronización con:');
            $this->comment('   php artisan payments:sync-mercadopago');
            $this->newLine();

            return self::SUCCESS;
        }
        catch (\Exception $e)
        {
            $this->error('❌ Error al conectar con MercadoPago');
            $this->newLine();
            $this->error('Mensaje: '.$e->getMessage());
            $this->newLine();
            $this->comment('Posibles soluciones:');
            $this->line('  • Verifica que el Access Token sea correcto');
            $this->line('  • Asegúrate de que no tenga espacios extra');
            $this->line('  • Confirma que el token no haya expirado');
            $this->line('  • Revisa que tu aplicación tenga permisos para leer pagos');
            $this->newLine();

            return self::FAILURE;
        }
    }
}

