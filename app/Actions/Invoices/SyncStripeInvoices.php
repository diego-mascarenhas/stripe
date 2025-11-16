<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Stripe\StripeClient;

class SyncStripeInvoices
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    public function handle(): int
    {
        $processed = 0;

        try {
            $params = [
                'limit' => 100,
                'expand' => [
                    'data.customer',
                    'data.subscription',
                ],
            ];

            $collection = $this->stripe->invoices->all($params);

            foreach ($collection->autoPagingIterator() as $stripeInvoice) {
                $payload = $stripeInvoice->toArray();
                $mapped = $this->mapInvoice($payload);

                $invoice = Invoice::firstWhere('stripe_id', $mapped['stripe_id']);

                if ($invoice) {
                    $invoice->update($mapped + ['last_synced_at' => now()]);
                } else {
                    Invoice::create($mapped + ['last_synced_at' => now()]);
                }

                $processed++;
            }
        } catch (\Throwable $exception) {
            report($exception);
            throw $exception;
        }

        return $processed;
    }

    private function mapInvoice(array $payload): array
    {
        $customer = Arr::get($payload, 'customer');
        $customerArray = is_array($customer) ? $customer : [];
        $subscription = Arr::get($payload, 'subscription');

        // Extract discount information
        $totalDiscountAmount = 0;
        $appliedCoupons = [];

        if (! empty($payload['discount'])) {
            if (! empty($payload['discount']['coupon']['id'])) {
                $appliedCoupons[] = $payload['discount']['coupon']['id'];
            }
            if (! empty($payload['discount']['promotion_code'])) {
                $appliedCoupons[] = $payload['discount']['promotion_code'];
            }
            if (! empty($payload['discount']['amount_off'])) {
                $totalDiscountAmount = $payload['discount']['amount_off'] / 100;
            }
        }

        return [
            'stripe_id' => Arr::get($payload, 'id'),
            'stripe_subscription_id' => is_string($subscription)
                ? $subscription
                : Arr::get($subscription, 'id'),
            'customer_id' => is_string($customer)
                ? $customer
                : Arr::get($customerArray, 'id'),
            'customer_email' => Arr::get($customerArray, 'email')
                ?? Arr::get($payload, 'customer_email'),
            'customer_name' => Arr::get($customerArray, 'name')
                ?? Arr::get($payload, 'customer_name'),
            'number' => Arr::get($payload, 'number'),
            'status' => Arr::get($payload, 'status'),
            'billing_reason' => Arr::get($payload, 'billing_reason'),
            'closed' => (bool) Arr::get($payload, 'closed', false),
            'currency' => strtoupper(Arr::get($payload, 'currency', 'USD')),
            'amount_due' => $this->normalizeAmount(Arr::get($payload, 'amount_due')),
            'amount_paid' => $this->normalizeAmount(Arr::get($payload, 'amount_paid')),
            'amount_remaining' => $this->normalizeAmount(Arr::get($payload, 'amount_remaining')),
            'subtotal' => $this->normalizeAmount(Arr::get($payload, 'subtotal')),
            'tax' => $this->normalizeAmount(Arr::get($payload, 'tax')),
            'total' => $this->normalizeAmount(Arr::get($payload, 'total')),
            'total_discount_amount' => $totalDiscountAmount,
            'applied_coupons' => ! empty($appliedCoupons) ? implode(', ', $appliedCoupons) : null,
            'customer_tax_id' => $this->resolveCustomerTaxId($payload),
            'customer_address_country' => $this->resolveCustomerCountry($payload),
            'invoice_created_at' => $this->normalizeTimestamp(Arr::get($payload, 'created')),
            'invoice_due_date' => $this->normalizeTimestamp(Arr::get($payload, 'due_date')),
            'paid' => (bool) Arr::get($payload, 'paid', false),
            'hosted_invoice_url' => Arr::get($payload, 'hosted_invoice_url'),
            'invoice_pdf' => Arr::get($payload, 'invoice_pdf'),
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
                $type = Arr::get($taxId, 'type');

                return $type ? "{$value} ({$type})" : $value;
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

