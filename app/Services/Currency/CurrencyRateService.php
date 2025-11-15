<?php

namespace App\Services\Currency;

use App\Models\ExchangeRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class CurrencyRateService
{
    private const ENDPOINT = 'https://api.currencyfreaks.com/latest';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseCurrency,
        private readonly array $targetCurrencies,
    ) {
    }

    public static function make(): self
    {
        $config = config('services.currencyfreaks');

        return new self(
            apiKey: data_get($config, 'key'),
            baseCurrency: strtoupper(data_get($config, 'base_currency', 'USD')),
            targetCurrencies: array_values(data_get($config, 'target_currencies', [])),
        );
    }

    public function syncLatestRates(): Collection
    {
        $payload = $this->requestRates();
        $fetchedAt = now();

        $rates = collect($this->targetCurrencies)->map(function (string $target) use ($payload, $fetchedAt) {
            $value = data_get($payload, "rates.$target");

            if (blank($value)) {
                return null;
            }

            return ExchangeRate::updateOrCreate(
                [
                    'base_currency' => $this->baseCurrency,
                    'target_currency' => $target,
                    'fetched_at' => $fetchedAt,
                ],
                [
                    'rate' => (float) $value,
                    'provider' => 'currencyfreaks',
                    'payload' => $payload,
                ],
            );
        })->filter();

        return $rates->values();
    }

    private function requestRates(): array
    {
        if (blank($this->apiKey)) {
            throw new \RuntimeException('CurrencyFreaks API key is not configured.');
        }

        $response = Http::timeout(15)->acceptJson()->get(self::ENDPOINT, [
            'apikey' => $this->apiKey,
            'symbols' => implode(',', $this->targetCurrencies),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Unable to download exchange rates from CurrencyFreaks.');
        }

        return $response->json();
    }
}

