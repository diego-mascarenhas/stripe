<?php

namespace App\Actions\CreditNotes;

use App\Models\CreditNote;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Stripe\StripeClient;

class SyncStripeCreditNotes
{
    public function __construct(private readonly StripeClient $stripe)
    {
    }

    public function handle(): int
    {
        $processed = 0;

        try {
            $params = [
                'limit' => 100,
                'expand' => [
                    'data.customer',
                    'data.invoice',
                ],
            ];

            $collection = $this->stripe->creditNotes->all($params);

            foreach ($collection->autoPagingIterator() as $stripeCreditNote) {
                $payload = $stripeCreditNote->toArray();
                $mapped = $this->mapCreditNote($payload);

                $creditNote = CreditNote::firstWhere('stripe_id', $mapped['stripe_id']);

                if ($creditNote) {
                    $creditNote->update($mapped + ['last_synced_at' => now()]);
                } else {
                    CreditNote::create($mapped + ['last_synced_at' => now()]);
                }

                $processed++;
            }
        } catch (\Throwable $exception) {
            report($exception);
            throw $exception;
        }

        return $processed;
    }

    private function mapCreditNote(array $payload): array
    {
        $customer = Arr::get($payload, 'customer');
        $customerArray = is_array($customer) ? $customer : [];
        $invoice = Arr::get($payload, 'invoice');

        // Calculate tax from total_tax_amounts if tax field is not present
        $tax = Arr::get($payload, 'tax');
        if ($tax === null || $tax === 0) {
            $totalTaxAmounts = Arr::get($payload, 'total_tax_amounts', []);
            if (is_array($totalTaxAmounts) && ! empty($totalTaxAmounts)) {
                $taxSum = collect($totalTaxAmounts)->sum('amount');
                $tax = $taxSum > 0 ? $taxSum : null;
            }
        }

        return [
            'stripe_id' => Arr::get($payload, 'id'),
            'stripe_invoice_id' => is_string($invoice)
                ? $invoice
                : Arr::get($invoice, 'id'),
            'stripe_refund_id' => Arr::get($payload, 'refund'),
            'customer_id' => is_string($customer)
                ? $customer
                : Arr::get($customerArray, 'id'),
            'customer_email' => Arr::get($customerArray, 'email')
                ?? Arr::get($payload, 'customer_email'),
            'customer_name' => Arr::get($customerArray, 'name')
                ?? Arr::get($payload, 'customer_name'),
            'customer_description' => Arr::get($customerArray, 'description')
                ?? Arr::get($payload, 'customer_description'),
            'customer_tax_id' => $this->resolveCustomerTaxId($payload),
            'customer_address_country' => $this->resolveCustomerCountry($payload),
            'number' => Arr::get($payload, 'number'),
            'status' => Arr::get($payload, 'status'),
            'type' => Arr::get($payload, 'type'),
            'reason' => Arr::get($payload, 'reason'),
            'currency' => strtoupper(Arr::get($payload, 'currency', 'USD')),
            'amount' => $this->normalizeAmount(Arr::get($payload, 'amount')),
            'subtotal' => $this->normalizeAmount(Arr::get($payload, 'subtotal')),
            'tax' => $this->normalizeAmount($tax),
            'total' => $this->normalizeAmount(Arr::get($payload, 'total')),
            'discount_amount' => $this->normalizeAmount(Arr::get($payload, 'discount_amount')),
            'memo' => Arr::get($payload, 'memo'),
            'credit_note_created_at' => $this->normalizeTimestamp(Arr::get($payload, 'created')),
            'voided' => (bool) Arr::get($payload, 'voided', false),
            'voided_at' => $this->normalizeTimestamp(Arr::get($payload, 'voided_at')),
            'pdf' => Arr::get($payload, 'pdf'),
            'hosted_credit_note_url' => Arr::get($payload, 'hosted_credit_note_url'),
            'raw_payload' => $payload,
        ];
    }

    private function resolveCustomerTaxId(array $payload): ?string
    {
        // Try multiple locations where Stripe might store tax IDs
        $taxIds = Arr::get($payload, 'customer_details.tax_ids', [])
            ?: Arr::get($payload, 'customer_tax_ids', []);

        if (empty($taxIds) || ! is_array($taxIds)) {
            return null;
        }

        foreach ($taxIds as $taxId) {
            $value = Arr::get($taxId, 'value');

            if (filled($value)) {
                // Return only the value without type
                return $value;
            }
        }

        return null;
    }

    private function resolveCustomerCountry(array $payload): ?string
    {
        $country = Arr::get($payload, 'customer_details.address.country')
            ?? Arr::get($payload, 'customer_address.country');

        return $country ? strtoupper($country) : null;
    }

    private function normalizeAmount(?int $amount): ?float
    {
        if ($amount === null) {
            return null;
        }

        return $amount / 100;
    }

    private function normalizeTimestamp(?int $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::createFromTimestampUTC($value)->setTimezone(config('app.timezone'));
    }
}

