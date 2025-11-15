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
        // Only count active and trialing subscriptions
        $activeStatuses = ['active', 'trialing'];
        
        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::whereIn('status', $activeStatuses)->count();
        $pastDueSubscriptions = Subscription::where('status', 'past_due')->count();
        $canceledSubscriptions = Subscription::where('status', 'canceled')->count();

        // Sum by billing currency (only active/trialing)
        $billedInEur = Subscription::where('price_currency', 'eur')
            ->whereIn('status', $activeStatuses)
            ->sum('amount_total');
        $countEur = Subscription::where('price_currency', 'eur')
            ->whereIn('status', $activeStatuses)
            ->count();

        $billedInArs = Subscription::where('price_currency', 'ars')
            ->whereIn('status', $activeStatuses)
            ->sum('amount_total');
        $countArs = Subscription::where('price_currency', 'ars')
            ->whereIn('status', $activeStatuses)
            ->count();

        $billedInUsd = Subscription::where('price_currency', 'usd')
            ->whereIn('status', $activeStatuses)
            ->sum('amount_total');
        $countUsd = Subscription::where('price_currency', 'usd')
            ->whereIn('status', $activeStatuses)
            ->count();

        // Total converted to EUR (only active/trialing)
        $totalEur = Subscription::whereNotNull('amount_eur')
            ->whereIn('status', $activeStatuses)
            ->sum('amount_eur');

        // Monthly Recurring Revenue (MRR) - normalized to monthly equivalent in EUR
        $mrr = $this->calculateMonthlyRecurringRevenue();

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
            Stat::make('Total equivalente en EUR', number_format($totalEur, 2, ',', '.').' €')
                ->description('Suma total de todas las suscripciones activas')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('success'),

            Stat::make('MRR (Mensual Recurrente)', number_format($mrr, 2, ',', '.').' €')
                ->description('Ingresos mensuales recurrentes normalizados')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Tipo de cambio EUR/ARS', $rateDescription)
                ->description($rateDate ? "Actualizado: {$rateDate}" : 'Ejecutar sincronización de tasas')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('gray'),

            Stat::make('Servicios en EUR', number_format($billedInEur, 2, ',', '.').' €')
                ->description($countEur.' suscripciones activas facturadas en euros')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('primary'),

            Stat::make('Servicios en ARS', number_format($billedInArs, 2, ',', '.').' $')
                ->description($countArs.' suscripciones activas facturadas en pesos')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Servicios en USD', number_format($billedInUsd, 2, ',', '.').' $')
                ->description($countUsd.' suscripciones activas facturadas en dólares')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Total suscripciones', number_format($totalSubscriptions, 0, ',', '.'))
                ->description(number_format($activeSubscriptions, 0, ',', '.').' activas')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Vencidas', number_format($pastDueSubscriptions, 0, ',', '.'))
                ->description('Suscripciones con pagos pendientes')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Canceladas', number_format($canceledSubscriptions, 0, ',', '.'))
                ->description('Suscripciones canceladas')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('gray'),
        ];
    }

    private function calculateMonthlyRecurringRevenue(): float
    {
        $mrr = 0.0;
        $activeStatuses = ['active', 'trialing'];

        Subscription::whereNotNull('amount_eur')
            ->whereNotNull('plan_interval')
            ->whereIn('status', $activeStatuses)
            ->chunk(100, function ($subscriptions) use (&$mrr) {
                foreach ($subscriptions as $subscription) {
                    $amountEur = (float) $subscription->amount_eur;
                    $interval = $subscription->plan_interval;
                    $intervalCount = (int) ($subscription->plan_interval_count ?? 1);

                    // Convert to monthly equivalent
                    $monthlyAmount = match ($interval) {
                        'day' => $amountEur * 30 / $intervalCount,
                        'week' => $amountEur * 4.33 / $intervalCount, // Average weeks per month
                        'month' => $amountEur / $intervalCount,
                        'year' => $amountEur / (12 * $intervalCount),
                        default => 0.0,
                    };

                    $mrr += $monthlyAmount;
                }
            });

        return $mrr;
    }
}
