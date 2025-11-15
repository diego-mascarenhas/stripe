<?php

namespace App\Services\Currency;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CurrencyConversionService
{
    private ?Collection $cachedRates = null;
    private readonly string $baseCurrency;
    private readonly array $targetCurrencies;

    public function __construct(private readonly CurrencyRateService $rateService)
    {
        $config = config('services.currencyfreaks');

        $this->baseCurrency = strtoupper(data_get($config, 'base_currency', 'USD'));
        $this->targetCurrencies = array_values(data_get($config, 'target_currencies', []));
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency): ?float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        if ($fromCurrency !== $this->baseCurrency) {
            $amount = $this->convertToUsd($amount, $fromCurrency);

            if ($amount === null) {
                return null;
            }

            $fromCurrency = $this->baseCurrency;
        }

        if ($toCurrency === $this->baseCurrency) {
            return $amount;
        }

        return $this->convertUsdTo($toCurrency, $amount);
    }

    public function convertUsdTo(string $targetCurrency, float $amountInUsd): ?float
    {
        $rate = $this->getRateFor($targetCurrency);

        if (! $rate) {
            return null;
        }

        return (float) $amountInUsd * (float) $rate->rate;
    }

    public function convertToUsd(float $amount, string $fromCurrency): ?float
    {
        $rate = $this->getRateFor($fromCurrency);

        if (! $rate) {
            return null;
        }

        if ($amount === 0.0) {
            return 0.0;
        }

        return (float) $amount / (float) $rate->rate;
    }

    public function convertForTargets(float $amount, string $fromCurrency, array $targets): array
    {
        $results = [];

        foreach ($targets as $target) {
            $converted = $this->convert($amount, $fromCurrency, $target);

            if ($converted !== null) {
                $results[strtoupper($target)] = $converted;
            }
        }

        return $results;
    }

    public function lastUpdatedAt(): ?Carbon
    {
        $rates = $this->getRates();

        if ($rates->isEmpty()) {
            return null;
        }

        return $rates->max(fn (ExchangeRate $rate) => $rate->fetched_at);
    }

    public function getRates(): Collection
    {
        $this->ensureRatesLoaded();

        return $this->cachedRates ?? collect();
    }

    private function ensureRatesLoaded(): void
    {
        if ($this->cachedRates !== null) {
            return;
        }

        $targets = $this->targetCurrencies;
        $this->cachedRates = ExchangeRate::query()
            ->where('base_currency', $this->baseCurrency)
            ->whereIn('target_currency', $targets)
            ->orderByDesc('fetched_at')
            ->get()
            ->unique('target_currency')
            ->keyBy(fn (ExchangeRate $rate) => strtoupper($rate->target_currency));

        if ($this->cachedRates->isNotEmpty()) {
            return;
        }

        $this->rateService->syncLatestRates();

        $this->cachedRates = ExchangeRate::query()
            ->where('base_currency', $this->baseCurrency)
            ->whereIn('target_currency', $targets)
            ->orderByDesc('fetched_at')
            ->get()
            ->unique('target_currency')
            ->keyBy(fn (ExchangeRate $rate) => strtoupper($rate->target_currency));
    }

    private function getRateFor(string $targetCurrency): ?ExchangeRate
    {
        $this->ensureRatesLoaded();

        return $this->cachedRates?->get(strtoupper($targetCurrency));
    }
}

