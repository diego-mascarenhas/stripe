<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class ListSubscriptions extends Command
{
    protected $signature = 'subscriptions:list';
    protected $description = 'Lista todas las suscripciones';

    public function handle(): int
    {
        $subscriptions = Subscription::select('id', 'customer_name', 'status', 'stripe_id')
            ->limit(10)
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('No hay suscripciones en la base de datos.');
            return 0;
        }

        $this->info('📋 Suscripciones:');
        $this->newLine();

        foreach ($subscriptions as $sub) {
            $this->line("ID: {$sub->id} | {$sub->customer_name} | Status: {$sub->status}");
        }

        return 0;
    }
}


