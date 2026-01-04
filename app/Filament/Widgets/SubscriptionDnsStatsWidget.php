<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionDnsStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Count subscriptions with metadata
        $totalWithMetadata = Subscription::where('type', 'sell')
            ->whereNotNull('data->domain')
            ->count();

        // DNS Nameservers
        $nameserversOk = Subscription::where('type', 'sell')
            ->where('data->dns_has_own_ns', 'true')
            ->count();

        $nameserversNok = Subscription::where('type', 'sell')
            ->where('data->dns_has_own_ns', 'false')
            ->count();

        // Domain IP
        $domainOk = Subscription::where('type', 'sell')
            ->where('data->dns_domain_points_to_service', 'true')
            ->count();

        $domainNok = Subscription::where('type', 'sell')
            ->where('data->dns_domain_points_to_service', 'false')
            ->count();

        // Mail Server
        $mailOk = Subscription::where('type', 'sell')
            ->where('data->dns_mail_points_to_service', 'true')
            ->count();

        $mailNok = Subscription::where('type', 'sell')
            ->where('data->dns_mail_points_to_service', 'false')
            ->count();

        // SPF
        $spfOk = Subscription::where('type', 'sell')
            ->where('data->has_spf_include', 'true')
            ->count();

        $spfNok = Subscription::where('type', 'sell')
            ->where('data->has_spf_include', 'false')
            ->count();

        // WHM Status
        $whmActive = Subscription::where('type', 'sell')
            ->where('data->whm_status', 'active')
            ->count();

        $whmSuspended = Subscription::where('type', 'sell')
            ->where('data->whm_status', 'suspended')
            ->count();

        return [
            Stat::make('Nameservers', $nameserversOk)
                ->description("{$nameserversNok} incorrectos de {$totalWithMetadata} dominios")
                ->descriptionIcon('heroicon-o-server')
                ->color($nameserversOk > 0 ? 'success' : 'gray'),

            Stat::make('Dominios', $domainOk)
                ->description("{$domainNok} no apuntan de {$totalWithMetadata} dominios")
                ->descriptionIcon('heroicon-o-globe-alt')
                ->color($domainOk > 0 ? 'success' : 'gray'),

            Stat::make('Mail', $mailOk)
                ->description("{$mailNok} no apuntan de {$totalWithMetadata} dominios")
                ->descriptionIcon('heroicon-o-envelope')
                ->color($mailOk > 0 ? 'success' : 'gray'),

            Stat::make('SPF', $spfOk)
                ->description("{$spfNok} sin include de {$totalWithMetadata} dominios")
                ->descriptionIcon('heroicon-o-shield-check')
                ->color($spfOk > 0 ? 'success' : 'gray'),

            Stat::make('Servicios Activos', $whmActive)
                ->description("{$whmSuspended} suspendidos")
                ->descriptionIcon('heroicon-o-check-circle')
                ->color($whmActive > 0 ? 'success' : 'gray'),

            Stat::make('Sin Sincronizar', $totalWithMetadata - max($nameserversOk, $nameserversNok, $domainOk, $domainNok))
                ->description('Dominios sin datos DNS')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color('warning'),
        ];
    }
}
