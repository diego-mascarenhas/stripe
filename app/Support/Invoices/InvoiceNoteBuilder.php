<?php

namespace App\Support\Invoices;

use App\Models\Subscription;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Support\Str;

class InvoiceNoteBuilder
{
    public function __construct(private readonly CurrencyConversionService $conversionService)
    {
    }

    public function buildForSubscription(Subscription $subscription): ?string
    {
        $amount = $this->resolveBaseAmount($subscription);

        if ($amount === null || blank($subscription->price_currency)) {
            return null;
        }

        return $this->build($amount, $subscription->price_currency);
    }

    public function build(float $amount, string $currency): ?string
    {
        $currency = strtoupper($currency);

        if ($currency === 'EUR') {
            return null;
        }

        $eur = $this->conversionService->convert($amount, $currency, 'EUR');

        if ($eur === null) {
            return null;
        }

        $date = $this->conversionService->lastUpdatedAt()?->format('d/m/Y') ?? now()->format('d/m/Y');

        return sprintf(
            'Valor aproximado según tipo de cambio estimado al %s: %s equivalentes a %s EUR.',
            $date,
            $this->formatAmount($amount, $currency),
            $this->formatAmount($eur, 'EUR'),
        );
    }

    private function formatAmount(float $amount, string $currency): string
    {
        $formatted = number_format($amount, 2, ',', '.');

        return sprintf('%s %s', $formatted, Str::upper($currency));
    }

    public function resolveBaseAmount(Subscription $subscription): ?float
    {
        return $subscription->amount_total
            ?? $subscription->amount_subtotal
            ?? ($subscription->unit_amount !== null
                ? (float) $subscription->unit_amount * (int) max($subscription->quantity ?? 1, 1)
                : null);
    }
}

