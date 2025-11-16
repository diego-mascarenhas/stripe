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
        
        // INGRESOS (type = 'sell')
        $totalSubscriptions = Subscription::where('type', 'sell')->count();
        $activeSubscriptions = Subscription::where('type', 'sell')->whereIn('status', $activeStatuses)->count();
        $pastDueSubscriptions = Subscription::where('type', 'sell')->where('status', 'past_due')->count();
        $canceledSubscriptions = Subscription::where('type', 'sell')->where('status', 'canceled')->count();

        // Total converted to EUR (only active/trialing) - INGRESOS
        $totalEurIncome = Subscription::where('type', 'sell')
            ->whereNotNull('amount_eur')
            ->whereIn('status', $activeStatuses)
            ->sum('amount_eur');

        // Monthly Recurring Revenue (MRR) - normalized to monthly equivalent in EUR - INGRESOS
        $mrrIncome = $this->calculateMonthlyRecurringRevenue('sell');

        // GASTOS (type = 'buy')
        $totalExpenses = Subscription::where('type', 'buy')->count();
        $activeExpenses = Subscription::where('type', 'buy')->whereIn('status', $activeStatuses)->count();

        // Total converted to EUR (only active/trialing) - GASTOS
        $totalEurExpenses = Subscription::where('type', 'buy')
            ->whereNotNull('amount_eur')
            ->whereIn('status', $activeStatuses)
            ->sum('amount_eur');

        // Monthly Recurring Expenses (MRE) - normalized to monthly equivalent in EUR - GASTOS
        $mrrExpenses = $this->calculateMonthlyRecurringRevenue('buy');

        // PROFIT
        $profit = $totalEurIncome - $totalEurExpenses;
        $mrrProfit = $mrrIncome - $mrrExpenses;

        // Sum by billing currency (only active/trialing) - para los widgets originales
        $billedInEur = Subscription::where('type', 'sell')
            ->where('price_currency', 'eur')
            ->whereIn('status', $activeStatuses)
            ->sum('amount_total');
        $countEur = Subscription::where('type', 'sell')
            ->where('price_currency', 'eur')
            ->whereIn('status', $activeStatuses)
            ->count();

        $billedInArs = Subscription::where('type', 'sell')
            ->where('price_currency', 'ars')
            ->whereIn('status', $activeStatuses)
            ->sum('amount_total');
        $countArs = Subscription::where('type', 'sell')
            ->where('price_currency', 'ars')
            ->whereIn('status', $activeStatuses)
            ->count();

        $billedInUsd = Subscription::where('type', 'sell')
            ->where('price_currency', 'usd')
            ->whereIn('status', $activeStatuses)
            ->sum('amount_total');
        $countUsd = Subscription::where('type', 'sell')
            ->where('price_currency', 'usd')
            ->whereIn('status', $activeStatuses)
            ->count();

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
            // INGRESOS
            Stat::make('Ingresos Totales (EUR)', number_format($totalEurIncome, 2, ',', '.').' €')
                ->description(number_format($activeSubscriptions, 0, ',', '.').' suscripciones activas')
                ->descriptionIcon('heroicon-m-arrow-up-circle')
                ->color('success'),

            // GASTOS
            Stat::make('Gastos Totales (EUR)', number_format($totalEurExpenses, 2, ',', '.').' €')
                ->description(number_format($activeExpenses, 0, ',', '.').' suscripciones activas')
                ->descriptionIcon('heroicon-m-arrow-down-circle')
                ->color('danger'),

            // PROFIT TOTAL
            Stat::make('Profit Total (EUR)', number_format($profit, 2, ',', '.').' €')
                ->description('Ingresos - Gastos (suscripciones activas)')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($profit >= 0 ? 'success' : 'danger'),

            // MRR INGRESOS
            Stat::make('MRR Ingresos', number_format($mrrIncome, 2, ',', '.').' €')
                ->description('Ingresos mensuales recurrentes')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            // MRR GASTOS
            Stat::make('MRR Gastos', number_format($mrrExpenses, 2, ',', '.').' €')
                ->description('Gastos mensuales recurrentes')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),

            // MRR PROFIT
            Stat::make('MRR Profit', number_format($mrrProfit, 2, ',', '.').' €')
                ->description('MRR Ingresos - MRR Gastos')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($mrrProfit >= 0 ? 'success' : 'danger'),

            // WIDGETS ORIGINALES
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

            Stat::make('Tipo de cambio EUR/ARS', $rateDescription)
                ->description($rateDate ? "Actualizado: {$rateDate}" : 'Ejecutar sincronización de tasas')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('gray'),
        ];
    }

    private function calculateMonthlyRecurringRevenue(string $type = 'sell'): float
    {
        $mrr = 0.0;
        $activeStatuses = ['active', 'trialing'];

        Subscription::where('type', $type)
            ->whereNotNull('amount_eur')
            ->whereNotNull('plan_interval')
            ->where('plan_interval', '!=', 'indefinite')
            ->whereIn('status', $activeStatuses)
            ->chunk(100, function ($subscriptions) use (&$mrr) {
                foreach ($subscriptions as $subscription) {
                    $amountEur = (float) $subscription->amount_eur;
                    $interval = $subscription->plan_interval;
                    $intervalCount = max((int) ($subscription->plan_interval_count ?? 1), 1);

                    $monthlyAmount = match ($interval) {
                        'day' => $amountEur * 30 / $intervalCount,
                        'week' => $amountEur * 4.33 / $intervalCount,
                        'month' => $amountEur / $intervalCount,
                        'quarter' => $amountEur / (3 * $intervalCount),
                        'semester' => $amountEur / (6 * $intervalCount),
                        'year' => $amountEur / (12 * $intervalCount),
                        'biennial' => $amountEur / (24 * $intervalCount),
                        'quinquennial' => $amountEur / (60 * $intervalCount),
                        'decennial' => $amountEur / (120 * $intervalCount),
                        default => 0.0,
                    };

                    $mrr += $monthlyAmount;
                }
            });

        return $mrr;
    }
}
