<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionChange;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Stripe\StripeSubscriptionService;
use App\Support\Invoices\InvoiceNoteBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SyncStripeSubscriptions
{
    public function __construct(
        private readonly StripeSubscriptionService $stripe,
        private readonly InvoiceNoteBuilder $noteBuilder,
        private readonly CurrencyConversionService $conversionService,
    ) {
    }

    public function handle(): int
    {
        $processed = 0;

        foreach ($this->stripe->subscriptions() as $stripeSubscription) {
            $payload = $stripeSubscription->toArray();
            $mapped = $this->mapSubscription($payload);

            $note = $mapped['amount_for_note'] !== null
                ? $this->noteBuilder->build($mapped['amount_for_note'], $mapped['price_currency'])
                : null;
            $mapped['invoice_note'] = $note;
            unset($mapped['amount_for_note']);

            $subscription = Subscription::firstWhere('stripe_id', $mapped['stripe_id']);

            if ($subscription) {
                $this->updateSubscription($subscription, $mapped);
            } else {
                $subscription = Subscription::create($mapped + ['last_synced_at' => now()]);

                SubscriptionChange::create([
                    'subscription_id' => $subscription->id,
                    'source' => 'stripe',
                    'changed_fields' => array_keys($mapped),
                    'previous_values' => null,
                    'current_values' => Arr::only($subscription->toArray(), array_keys($mapped)),
                    'detected_at' => now(),
                ]);
            }

            $processed++;
        }

        return $processed;
    }

    private function updateSubscription(Subscription $subscription, array $payload): void
    {
        $subscription->fill($payload + ['last_synced_at' => now()]);
        $dirty = $subscription->getDirty();

        if (empty($dirty)) {
            return;
        }

        $original = Arr::only($subscription->getOriginal(), array_keys($dirty));

        $subscription->save();

        SubscriptionChange::create([
            'subscription_id' => $subscription->id,
            'source' => 'stripe',
            'changed_fields' => array_keys($dirty),
            'previous_values' => $original,
            'current_values' => Arr::only($subscription->fresh()->toArray(), array_keys($dirty)),
            'detected_at' => now(),
        ]);
    }

    private function mapSubscription(array $payload): array
    {
        $item = Arr::get($payload, 'items.data.0', []);
        $price = Arr::get($item, 'price', []);
        $customer = Arr::get($payload, 'customer');
        $customerArray = is_array($customer) ? $customer : [];

        $unitAmount = $this->normalizeAmount(
            Arr::get($price, 'unit_amount_decimal'),
            Arr::get($price, 'unit_amount'),
        );

        $quantity = (int) (Arr::get($item, 'quantity', 1) ?: 1);

        $amountSubtotal = $unitAmount !== null ? $unitAmount * $quantity : null;
        $amountTotal = $this->normalizeAmount(
            Arr::get($payload, 'latest_invoice.amount_due_decimal'),
            Arr::get($payload, 'latest_invoice.amount_due'),
        ) ?? $amountSubtotal;

        $priceCurrency = strtoupper(Arr::get($price, 'currency', 'USD'));

        $amountForNote = $amountTotal ?? $amountSubtotal ?? $unitAmount;

        $convertedAmounts = $amountForNote !== null
            ? $this->conversionService->convertForTargets(
                $amountForNote,
                $priceCurrency,
                ['USD', 'ARS', 'EUR'],
            )
            : [];

        $country = $this->resolveCountry($payload, $customerArray);
        $taxData = $this->resolveTaxData($payload);

        return [
            'stripe_id' => Arr::get($payload, 'id'),
            'customer_id' => is_string($customer)
                ? $customer
                : Arr::get($customerArray, 'id'),
            'customer_email' => Arr::get($payload, 'customer_email')
                ?? Arr::get($payload, 'customer_details.email')
                ?? Arr::get($customerArray, 'email'),
            'customer_name' => Arr::get($payload, 'customer_name')
                ?? Arr::get($payload, 'customer_details.name')
                ?? Arr::get($customerArray, 'name'),
            'customer_country' => $country,
            'customer_tax_id_type' => Arr::get($taxData ?? [], 'type'),
            'customer_tax_id' => Arr::get($taxData ?? [], 'value'),
            'status' => Arr::get($payload, 'status'),
            'collection_method' => Arr::get($payload, 'collection_method'),
            'plan_name' => Arr::get($price, 'nickname')
                ?? Arr::get($price, 'product.name')
                ?? Arr::get($item, 'plan.nickname')
                ?? Arr::get($payload, 'description'),
            'plan_interval' => Arr::get($price, 'recurring.interval'),
            'plan_interval_count' => Arr::get($price, 'recurring.interval_count'),
            'quantity' => $quantity,
            'price_currency' => $priceCurrency,
            'unit_amount' => $unitAmount,
            'amount_subtotal' => $amountSubtotal,
            'amount_total' => $amountTotal,
            'amount_usd' => $convertedAmounts['USD'] ?? ($priceCurrency === 'USD' ? $amountForNote : null),
            'amount_ars' => $convertedAmounts['ARS'] ?? null,
            'amount_eur' => $convertedAmounts['EUR'] ?? null,
            'current_period_start' => $this->normalizeTimestamp(Arr::get($payload, 'current_period_start')),
            'current_period_end' => $this->normalizeTimestamp(Arr::get($payload, 'current_period_end')),
            'cancel_at_period_end' => (bool) Arr::get($payload, 'cancel_at_period_end', false),
            'canceled_at' => $this->normalizeTimestamp(Arr::get($payload, 'canceled_at')),
            'raw_payload' => $payload,
            'amount_for_note' => $amountForNote,
        ];
    }

    private function normalizeAmount(?string $decimalAmount, ?int $integerAmount): ?float
    {
        if ($decimalAmount !== null) {
            return (float) $decimalAmount;
        }

        if ($integerAmount !== null) {
            return $integerAmount / 100;
        }

        return null;
    }

    private function normalizeTimestamp(?int $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::createFromTimestampUTC($value)->setTimezone(config('app.timezone'));
    }

    private function resolveCountry(array $payload, array $customer): ?string
    {
        return strtoupper(
            Arr::get($payload, 'customer_details.address.country')
            ?? Arr::get($payload, 'latest_invoice.customer_address.country')
            ?? Arr::get($customer, 'address.country')
            ?? Arr::get($payload, 'customer_address.country')
        ) ?: null;
    }

    private function resolveTaxData(array $payload): ?array
    {
        $taxIds = Arr::get($payload, 'customer_details.tax_ids', []);

        if (! is_array($taxIds) || empty($taxIds)) {
            return null;
        }

        return collect($taxIds)
            ->filter(fn ($tax) => filled(Arr::get($tax, 'value')))
            ->first();
    }
}

