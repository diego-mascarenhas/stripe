<?php

namespace App\Filament\Widgets;

use App\Models\ExchangeRate;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::whereIn('status', ['active', 'trialing'])->count();
        $pastDueSubscriptions = Subscription::where('status', 'past_due')->count();

        // Sum by billing currency
        $billedInEur = Subscription::where('price_currency', 'eur')->sum('amount_total');
        $countEur = Subscription::where('price_currency', 'eur')->count();

        $billedInArs = Subscription::where('price_currency', 'ars')->sum('amount_total');
        $countArs = Subscription::where('price_currency', 'ars')->count();

        $billedInUsd = Subscription::where('price_currency', 'usd')->sum('amount_total');
        $countUsd = Subscription::where('price_currency', 'usd')->count();

        // Total converted to EUR
        $totalEur = Subscription::whereNotNull('amount_eur')->sum('amount_eur');

        // Get latest exchange rates
        $arsRate = ExchangeRate::where('base_currency', 'USD')
            ->where('target_currency', 'ARS')
            ->orderByDesc('fetched_at')
            ->first();

        $eurRate = ExchangeRate::where('base_currency', 'USD')
            ->where('target_currency', 'EUR')
            ->orderByDesc('fetched_at')
            ->first();

        $eurToArsRate = null;
        $rateDescription = 'Sin datos de cambio';
        $rateDate = null;

        if ($arsRate && $eurRate && $eurRate->rate > 0) {
            $eurToArsRate = $arsRate->rate / $eurRate->rate;
            $rateDate = $arsRate->fetched_at->format('d/m/Y H:i');
            $rateDescription = "1 EUR = ".number_format($eurToArsRate, 2, ',', '.')." ARS";
        }

        return [
            Stat::make('Total suscripciones', number_format($totalSubscriptions, 0, ',', '.'))
                ->description(number_format($activeSubscriptions, 0, ',', '.').' activas')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Tipo de cambio EUR/ARS', $rateDescription)
                ->description($rateDate ? "Actualizado: {$rateDate}" : 'Ejecutar sincronización de tasas')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('gray'),

            Stat::make('Servicios en EUR', number_format($billedInEur, 2, ',', '.').' €')
                ->description($countEur.' suscripciones facturadas en euros')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('primary'),

            Stat::make('Servicios en ARS', number_format($billedInArs, 2, ',', '.').' $')
                ->description($countArs.' suscripciones facturadas en pesos')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Servicios en USD', number_format($billedInUsd, 2, ',', '.').' $')
                ->description($countUsd.' suscripciones facturadas en dólares')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Total equivalente en EUR', number_format($totalEur, 2, ',', '.').' €')
                ->description('Suma total de todas las suscripciones')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('success'),

            Stat::make('Vencidas', number_format($pastDueSubscriptions, 0, ',', '.'))
                ->description('Suscripciones con pagos pendientes')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
