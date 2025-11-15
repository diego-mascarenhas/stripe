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

        $totalEur = Subscription::whereNotNull('amount_eur')->sum('amount_eur');
        $totalArs = Subscription::whereNotNull('amount_ars')->sum('amount_ars');
        $totalUsd = Subscription::whereNotNull('amount_usd')->sum('amount_usd');

        $billedInUsd = Subscription::where('price_currency', 'usd')->count();

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

            Stat::make('Facturación EUR', number_format($totalEur, 2, ',', '.').' €')
                ->description('Total mensual en euros')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('primary'),

            Stat::make('Facturación ARS', number_format($totalArs, 2, ',', '.').' $')
                ->description('Total mensual en pesos argentinos')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Facturación USD', number_format($totalUsd, 2, ',', '.').' $')
                ->description($billedInUsd.' facturadas en USD')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Vencidas', number_format($pastDueSubscriptions, 0, ',', '.'))
                ->description('Suscripciones con pagos pendientes')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
