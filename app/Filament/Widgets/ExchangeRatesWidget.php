<?php

namespace App\Filament\Widgets;

use App\Models\ExchangeRate;
use Filament\Widgets\Widget;

class ExchangeRatesWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.exchange-rates-widget';

    // Auto-refresh every 5 minutes (300 seconds)
    protected static ?string $pollingInterval = '300s';

    public function getExchangeRates(): array
    {
        // Get USD -> ARS
        $usdToArs = ExchangeRate::where('base_currency', 'USD')
            ->where('target_currency', 'ARS')
            ->orderByDesc('fetched_at')
            ->first();

        // Get USD -> EUR
        $usdToEur = ExchangeRate::where('base_currency', 'USD')
            ->where('target_currency', 'EUR')
            ->orderByDesc('fetched_at')
            ->first();

        // Calculate EUR -> ARS
        $eurToArsRate = null;
        $eurToArsFormatted = 'N/A';
        $fetchedAt = null;

        if ($usdToArs && $usdToEur && $usdToEur->rate > 0) {
            $eurToArsRate = $usdToArs->rate / $usdToEur->rate;
            $eurToArsFormatted = number_format($eurToArsRate, 2, ',', '.');
            $fetchedAt = $usdToArs->fetched_at;
        }

        return [
            'usd_ars' => [
                'rate' => $usdToArs?->rate,
                'formatted' => $usdToArs ? number_format($usdToArs->rate, 2, ',', '.') : 'N/A',
                'fetched_at' => $usdToArs?->fetched_at,
            ],
            'usd_eur' => [
                'rate' => $usdToEur?->rate,
                'formatted' => $usdToEur ? number_format($usdToEur->rate, 4, ',', '.') : 'N/A',
                'fetched_at' => $usdToEur?->fetched_at,
            ],
            'eur_ars' => [
                'rate' => $eurToArsRate,
                'formatted' => $eurToArsFormatted,
                'fetched_at' => $fetchedAt,
            ],
        ];
    }
}
