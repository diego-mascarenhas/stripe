<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'raw_payload' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'stripe_subscription_id', 'stripe_id');
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

