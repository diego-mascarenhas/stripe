<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Services\Currency\CurrencyConversionService;
use App\Support\Invoices\InvoiceNoteBuilder;

class RefreshSubscriptionNotes
{
    public function __construct(
        private readonly InvoiceNoteBuilder $builder,
        private readonly CurrencyConversionService $conversionService,
    ) {
    }

    public function handle(): int
    {
        $updated = 0;

        Subscription::chunkById(100, function ($subscriptions) use (&$updated) {
            foreach ($subscriptions as $subscription) {
                $amount = $this->builder->resolveBaseAmount($subscription);
                $note = $this->builder->buildForSubscription($subscription);

                $converted = $amount !== null && $subscription->price_currency
                    ? $this->conversionService->convertForTargets(
                        $amount,
                        strtoupper($subscription->price_currency),
                        ['USD', 'ARS', 'EUR'],
                    )
                    : [];

                $changes = [
                    'invoice_note' => $note,
                    'amount_usd' => $converted['USD'] ?? ($subscription->price_currency === 'USD' ? $amount : null),
                    'amount_ars' => $converted['ARS'] ?? null,
                    'amount_eur' => $converted['EUR'] ?? null,
                ];

                $hasChanges = collect($changes)->some(
                    fn ($value, $attribute) => $subscription->{$attribute} !== $value,
                );

                if ($hasChanges) {
                    $subscription->forceFill($changes)->save();
                    $updated++;
                }
            }
        });

        return $updated;
    }
}

