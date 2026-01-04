<?php

namespace App\Providers;

use App\Models\Subscription;
use App\Observers\SubscriptionObserver;
use App\Services\Currency\CurrencyRateService;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrencyRateService::class, function (): CurrencyRateService {
            $config = config('services.currencyfreaks');

            return new CurrencyRateService(
                apiKey: data_get($config, 'key'),
                baseCurrency: data_get($config, 'base_currency', 'USD'),
                targetCurrencies: data_get($config, 'target_currencies', []),
            );
        });

        $this->app->singleton(StripeClient::class, function (): StripeClient {
            $secret = config('services.stripe.secret');

            if (blank($secret)) {
                throw new \RuntimeException('Stripe secret not configured.');
            }

            return new StripeClient($secret);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Subscription::observe(SubscriptionObserver::class);
    }
}
