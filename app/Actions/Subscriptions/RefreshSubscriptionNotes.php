<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Services\Currency\CurrencyConversionService;
use App\Support\Invoices\InvoiceNoteBuilder;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class RefreshSubscriptionNotes
{
    public function __construct(
        private readonly InvoiceNoteBuilder $builder,
        private readonly CurrencyConversionService $conversionService,
        private readonly StripeClient $stripe,
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
                    $this->syncStripeInvoiceNote($subscription, $note);
                    $updated++;
                }
            }
        });

        return $updated;
    }

    private function syncStripeInvoiceNote(Subscription $subscription, ?string $note): void
    {
        if (blank($subscription->stripe_id)) {
            return;
        }

        $currency = strtoupper($subscription->price_currency ?? '');

        if ($currency === 'EUR') {
            $this->updateStripeFooter($subscription, '');

            return;
        }

        if (blank($note)) {
            return;
        }

        $this->updateStripeFooter($subscription, $note);
    }

    private function updateStripeFooter(Subscription $subscription, string $footer): void
    {
        try {
            $this->stripe->subscriptions->update($subscription->stripe_id, [
                'invoice_settings' => [
                    'footer' => $footer,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('No se pudo actualizar la nota de factura en Stripe.', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

