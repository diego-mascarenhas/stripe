<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class DebugSubscriptions extends Command
{
    protected $signature = 'subscriptions:debug';
    protected $description = 'Debug subscriptions data';

    public function handle(): int
    {
        $this->info('🔍 Información de conexión:');
        $this->line('Driver: ' . config('database.default'));
        $this->line('Database: ' . config('database.connections.' . config('database.default') . '.database'));
        $this->line('Host: ' . config('database.connections.' . config('database.default') . '.host', 'N/A'));
        $this->newLine();

        $this->info('📋 Listado de suscripciones:');
        $this->line('Total: ' . Subscription::count());
        $this->newLine();

        $subscriptions = Subscription::orderBy('id')->get();

        foreach ($subscriptions as $sub) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("ID: {$sub->id}");
            $this->line("Cliente: {$sub->customer_name}");
            $this->line("Email: {$sub->customer_email}");
            $this->line("Estado: {$sub->status}");
            $this->line("Stripe ID: {$sub->stripe_id}");
            
            if ($sub->data) {
                $this->line("Data keys: " . implode(', ', array_keys($sub->data)));
                $this->line("Auto-suspend value: " . json_encode(data_get($sub->data, 'auto_suspend')));
                $this->line("Auto-suspend type: " . gettype(data_get($sub->data, 'auto_suspend')));
            } else {
                $this->line("Data: null");
            }
            
            $this->newLine();
        }

        return self::SUCCESS;
    }
}

