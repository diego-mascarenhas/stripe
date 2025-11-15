<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\RefreshSubscriptionNotes;
use App\Services\Currency\CurrencyRateService;
use Illuminate\Console\Command;

class SyncCurrencyRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los tipos de cambio desde Currency Freaks y recalcula las notas.';

    /**
     * Execute the console command.
     */
    public function handle(
        CurrencyRateService $rates,
        RefreshSubscriptionNotes $refreshSubscriptionNotes,
    ): int {
        $synced = $rates->syncLatestRates();
        $this->info("Tipos de cambio sincronizados: {$synced->count()}");

        $updated = $refreshSubscriptionNotes->handle();
        $this->info("Notas de suscripción recalculadas: {$updated}");

        return self::SUCCESS;
    }
}
