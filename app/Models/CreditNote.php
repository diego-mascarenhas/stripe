<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    protected $fillable = [
        'stripe_id',
        'stripe_invoice_id',
        'stripe_refund_id',
        'customer_id',
        'customer_email',
        'customer_name',
        'customer_description',
        'customer_tax_id',
        'customer_address_country',
        'number',
        'status',
        'type',
        'reason',
        'currency',
        'amount',
        'subtotal',
        'tax',
        'total',
        'discount_amount',
        'memo',
        'credit_note_created_at',
        'voided',
        'voided_at',
        'pdf',
        'hosted_credit_note_url',
        'last_synced_at',
        'raw_payload',
    ];

    protected $casts = [
        'amount' => 'float',
        'subtotal' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'discount_amount' => 'float',
        'voided' => 'boolean',
        'credit_note_created_at' => 'datetime',
        'voided_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'type_label',
        'reason_label',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'stripe_invoice_id', 'stripe_id');
    }

    /**
     * Get the status label in Spanish
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'issued' => 'Emitida',
            'void' => 'Anulada',
            default => ucfirst($this->status ?? '—'),
        };
    }

    /**
     * Get the status color for badges
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'issued' => 'success',
            'void' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the type label in Spanish
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'pre_payment' => 'Pre-pago',
            'post_payment' => 'Post-pago',
            default => ucfirst($this->type ?? '—'),
        };
    }

    /**
     * Get the reason label in Spanish
     */
    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'duplicate' => 'Duplicado',
            'fraudulent' => 'Fraudulento',
            'order_change' => 'Cambio de orden',
            'product_unsatisfactory' => 'Producto insatisfactorio',
            default => ucfirst($this->reason ?? '—'),
        };
    }

    public function getCustomerTaxIdAttribute(?string $value): ?string
    {
        if (filled($value)) {
            return $value;
        }

        $taxIds = \Illuminate\Support\Arr::get($this->raw_payload, 'customer_details.tax_ids', []);

        if (empty($taxIds)) {
            // Si no hay en el payload de la nota de crédito, intentar obtener de la factura relacionada
            if ($this->invoice) {
                return $this->invoice->customer_tax_id;
            }
            return null;
        }

        $first = \Illuminate\Support\Arr::first($taxIds, fn ($item) => filled(\Illuminate\Support\Arr::get($item, 'value')));

        if (! $first) {
            // Si no hay en el payload de la nota de crédito, intentar obtener de la factura relacionada
            if ($this->invoice) {
                return $this->invoice->customer_tax_id;
            }
            return null;
        }

        $value = \Illuminate\Support\Arr::get($first, 'value');
        $type = \Illuminate\Support\Arr::get($first, 'type');

        return $value ? ($type ? "{$value} ({$type})" : $value) : null;
    }

    public function getCustomerAddressCountryAttribute(?string $value): ?string
    {
        if (filled($value)) {
            return strtoupper($value);
        }

        $country = \Illuminate\Support\Arr::get($this->raw_payload, 'customer_details.address.country')
            ?? \Illuminate\Support\Arr::get($this->raw_payload, 'customer_address.country');

        if ($country) {
            return strtoupper($country);
        }

        // Si no hay en el payload de la nota de crédito, intentar obtener de la factura relacionada
        if ($this->invoice) {
            return $this->invoice->customer_address_country;
        }

        return null;
    }
}

