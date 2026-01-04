<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CreateTestInvoices extends Command
{
    protected $signature = 'test:create-invoices {subscription_id}';
    protected $description = 'Crea facturas de prueba para testing';

    public function handle(): int
    {
        $subscriptionId = $this->argument('subscription_id');
        $subscription = Subscription::find($subscriptionId);

        if (!$subscription) {
            $this->error('Suscripción no encontrada.');
            return 1;
        }

        $this->info("📋 Creando facturas de prueba para: {$subscription->customer_name}");
        $this->newLine();

        $choice = $this->choice(
            '¿Qué escenario deseas probar?',
            [
                '1' => 'Escenario 1: 1 factura impaga (NO avisos)',
                '2' => 'Escenario 2: 2 facturas, día 41 (aviso 5 días)',
                '3' => 'Escenario 3: 2 facturas, día 44 (aviso 2 días)',
                '4' => 'Escenario 4: 2 facturas, día 46 (suspensión)',
                'all' => 'Todos los escenarios',
            ],
            'all'
        );

        $scenarios = $choice === 'all' ? ['1', '2', '3', '4'] : [$choice];

        foreach ($scenarios as $scenario) {
            match ($scenario) {
                '1' => $this->createScenario1($subscription),
                '2' => $this->createScenario2($subscription),
                '3' => $this->createScenario3($subscription),
                '4' => $this->createScenario4($subscription),
                default => null,
            };
        }

        $this->newLine();
        $this->info('✅ Facturas de prueba creadas.');
        $this->line('Ejecuta: php artisan test:notification-logic ' . $subscriptionId);
        $this->line('O ejecuta: php artisan subscriptions:send-notifications');

        return 0;
    }

    private function createScenario1(Subscription $subscription): void
    {
        $this->line('Escenario 1: 1 factura impaga (15 días) → No debe enviar avisos');
        
        Invoice::create([
            'stripe_id' => 'in_test_scenario1_' . now()->timestamp,
            'stripe_subscription_id' => $subscription->stripe_id,
            'customer_id' => $subscription->customer_id,
            'customer_email' => $subscription->customer_email,
            'customer_name' => $subscription->customer_name,
            'number' => 'TEST-001',
            'status' => 'open',
            'paid' => false,
            'currency' => 'usd',
            'amount_due' => 10000,
            'amount_remaining' => 10000,
            'total' => 100.00,
            'invoice_created_at' => now()->subDays(15),
            'invoice_due_date' => now()->subDays(5),
        ]);
    }

    private function createScenario2(Subscription $subscription): void
    {
        $this->line('Escenario 2: 2 facturas impagas (más antigua: 41 días) → Debe enviar aviso 5 días');
        
        // Factura más antigua (41 días)
        Invoice::create([
            'stripe_id' => 'in_test_scenario2a_' . now()->timestamp,
            'stripe_subscription_id' => $subscription->stripe_id,
            'customer_id' => $subscription->customer_id,
            'customer_email' => $subscription->customer_email,
            'customer_name' => $subscription->customer_name,
            'number' => 'TEST-002A',
            'status' => 'open',
            'paid' => false,
            'currency' => 'usd',
            'amount_due' => 10000,
            'amount_remaining' => 10000,
            'total' => 100.00,
            'invoice_created_at' => now()->subDays(41),
            'invoice_due_date' => now()->subDays(31),
        ]);

        // Factura más reciente (11 días)
        Invoice::create([
            'stripe_id' => 'in_test_scenario2b_' . now()->timestamp,
            'stripe_subscription_id' => $subscription->stripe_id,
            'customer_id' => $subscription->customer_id,
            'customer_email' => $subscription->customer_email,
            'customer_name' => $subscription->customer_name,
            'number' => 'TEST-002B',
            'status' => 'open',
            'paid' => false,
            'currency' => 'usd',
            'amount_due' => 10000,
            'amount_remaining' => 10000,
            'total' => 100.00,
            'invoice_created_at' => now()->subDays(11),
            'invoice_due_date' => now()->subDays(1),
        ]);
    }

    private function createScenario3(Subscription $subscription): void
    {
        $this->line('Escenario 3: 2 facturas impagas (más antigua: 44 días) → Debe enviar aviso 2 días');
        
        // Factura más antigua (44 días)
        Invoice::create([
            'stripe_id' => 'in_test_scenario3a_' . now()->timestamp,
            'stripe_subscription_id' => $subscription->stripe_id,
            'customer_id' => $subscription->customer_id,
            'customer_email' => $subscription->customer_email,
            'customer_name' => $subscription->customer_name,
            'number' => 'TEST-003A',
            'status' => 'open',
            'paid' => false,
            'currency' => 'usd',
            'amount_due' => 10000,
            'amount_remaining' => 10000,
            'total' => 100.00,
            'invoice_created_at' => now()->subDays(44),
            'invoice_due_date' => now()->subDays(34),
        ]);

        // Factura más reciente (14 días)
        Invoice::create([
            'stripe_id' => 'in_test_scenario3b_' . now()->timestamp,
            'stripe_subscription_id' => $subscription->stripe_id,
            'customer_id' => $subscription->customer_id,
            'customer_email' => $subscription->customer_email,
            'customer_name' => $subscription->customer_name,
            'number' => 'TEST-003B',
            'status' => 'open',
            'paid' => false,
            'currency' => 'usd',
            'amount_due' => 10000,
            'amount_remaining' => 10000,
            'total' => 100.00,
            'invoice_created_at' => now()->subDays(14),
            'invoice_due_date' => now()->subDays(4),
        ]);
    }

    private function createScenario4(Subscription $subscription): void
    {
        $this->line('Escenario 4: 2 facturas impagas (más antigua: 46 días) → Debe suspenderse');
        
        // Factura más antigua (46 días)
        Invoice::create([
            'stripe_id' => 'in_test_scenario4a_' . now()->timestamp,
            'stripe_subscription_id' => $subscription->stripe_id,
            'customer_id' => $subscription->customer_id,
            'customer_email' => $subscription->customer_email,
            'customer_name' => $subscription->customer_name,
            'number' => 'TEST-004A',
            'status' => 'open',
            'paid' => false,
            'currency' => 'usd',
            'amount_due' => 10000,
            'amount_remaining' => 10000,
            'total' => 100.00,
            'invoice_created_at' => now()->subDays(46),
            'invoice_due_date' => now()->subDays(36),
        ]);

        // Factura más reciente (16 días)
        Invoice::create([
            'stripe_id' => 'in_test_scenario4b_' . now()->timestamp,
            'stripe_subscription_id' => $subscription->stripe_id,
            'customer_id' => $subscription->customer_id,
            'customer_email' => $subscription->customer_email,
            'customer_name' => $subscription->customer_name,
            'number' => 'TEST-004B',
            'status' => 'open',
            'paid' => false,
            'currency' => 'usd',
            'amount_due' => 10000,
            'amount_remaining' => 10000,
            'total' => 100.00,
            'invoice_created_at' => now()->subDays(16),
            'invoice_due_date' => now()->subDays(6),
        ]);
    }
}

