<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

class Invoice extends Model
{
    protected $fillable = [
        'stripe_id',
        'stripe_subscription_id',
        'customer_id',
        'customer_email',
        'customer_name',
        'number',
        'status',
        'billing_reason',
        'closed',
        'currency',
        'amount_due',
        'amount_paid',
        'amount_remaining',
        'subtotal',
        'tax',
        'total',
        'total_discount_amount',
        'applied_coupons',
        'invoice_created_at',
        'invoice_due_date',
        'paid',
        'hosted_invoice_url',
        'invoice_pdf',
        'last_synced_at',
        'customer_tax_id',
        'customer_address_country',
        'raw_payload',
    ];

    protected $casts = [
        'closed' => 'boolean',
        'paid' => 'boolean',
        'amount_due' => 'float',
        'amount_paid' => 'float',
        'amount_remaining' => 'float',
        'subtotal' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'total_discount_amount' => 'float',
        'invoice_created_at' => 'datetime',
        'invoice_due_date' => 'datetime',
        'last_synced_at' => 'datetime',
        'customer_address_country' => 'string',
        'raw_payload' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'stripe_subscription_id', 'stripe_id');
    }

    public function getCustomerTaxIdAttribute(?string $value): ?string
    {
        if (filled($value)) {
            return $value;
        }

        $taxIds = Arr::get($this->raw_payload, 'customer_details.tax_ids', []);

        if (empty($taxIds)) {
            return null;
        }

        $first = Arr::first($taxIds, fn ($item) => filled(Arr::get($item, 'value')));

        if (! $first) {
            return null;
        }

        $value = Arr::get($first, 'value');
        $type = Arr::get($first, 'type');

        return $value ? ($type ? "{$value} ({$type})" : $value) : null;
    }

    public function getCustomerAddressCountryAttribute(?string $value): ?string
    {
        if (filled($value)) {
            return strtoupper($value);
        }

        $country = Arr::get($this->raw_payload, 'customer_details.address.country')
            ?? Arr::get($this->raw_payload, 'customer_address.country');

        return $country ? strtoupper($country) : null;
    }

    public function getComputedTaxAmountAttribute(): ?float
    {
        $taxes = Arr::get($this->raw_payload, 'total_taxes', []);

        if (! is_array($taxes) || empty($taxes)) {
            return $this->tax;
        }

        $total = collect($taxes)->sum(fn ($tax) => Arr::get($tax, 'amount', 0));

        return $total > 0 ? $total / 100 : $this->tax;
    }

    /**
     * Get the status label in Spanish
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'Pagada',
            'open' => 'Abierta',
            'void' => 'Anulada',
            'uncollectible' => 'Incobrable',
            'draft' => 'Borrador',
            default => ucfirst($this->status ?? '—'),
        };
    }

    /**
     * Get the status color for badges
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'success',
            'open' => 'warning',
            'void' => 'danger',
            'uncollectible' => 'danger',
            'draft' => 'gray',
            default => 'gray',
        };
    }
}

