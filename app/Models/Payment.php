<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'mercadopago_id',
        'external_reference',
        'payment_type',
        'payment_method',
        'status',
        'status_detail',
        'payer_id',
        'payer_email',
        'payer_first_name',
        'payer_last_name',
        'payer_identification_type',
        'payer_identification_number',
        'currency',
        'transaction_amount',
        'net_amount',
        'total_paid_amount',
        'shipping_cost',
        'mercadopago_fee',
        'payment_created_at',
        'payment_approved_at',
        'money_release_date',
        'description',
        'installments',
        'issuer_id',
        'operation_type',
        'live_mode',
        'captured',
        'last_synced_at',
        'raw_payload',
    ];

    protected $casts = [
        'transaction_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'total_paid_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'mercadopago_fee' => 'decimal:2',
        'payment_created_at' => 'datetime',
        'payment_approved_at' => 'datetime',
        'money_release_date' => 'datetime',
        'last_synced_at' => 'datetime',
        'live_mode' => 'boolean',
        'captured' => 'boolean',
        'installments' => 'integer',
        'raw_payload' => 'array',
    ];

    /**
     * Get the status label in Spanish
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'Aprobado',
            'pending' => 'Pendiente',
            'in_process' => 'En proceso',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'charged_back' => 'Contracargo',
            default => ucfirst($this->status ?? '—'),
        };
    }

    /**
     * Get the status color for badges
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'success',
            'pending' => 'warning',
            'in_process' => 'info',
            'rejected' => 'danger',
            'cancelled' => 'danger',
            'refunded' => 'warning',
            'charged_back' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the payer full name
     */
    public function getPayerFullNameAttribute(): string
    {
        if ($this->payer_first_name || $this->payer_last_name) {
            return trim("{$this->payer_first_name} {$this->payer_last_name}");
        }

        return $this->payer_email ?? 'N/A';
    }

    /**
     * Get the payment method label
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        $methods = [
            'credit_card' => 'Tarjeta de crédito',
            'debit_card' => 'Tarjeta de débito',
            'ticket' => 'Efectivo',
            'atm' => 'Cajero automático',
            'bank_transfer' => 'Transferencia bancaria',
            'account_money' => 'Dinero en cuenta',
        ];

        return $methods[$this->payment_type] ?? ucfirst($this->payment_type ?? 'N/A');
    }

    /**
     * Check if the payment is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the payment is pending
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'in_process']);
    }

    /**
     * Check if the payment is rejected
     */
    public function isRejected(): bool
    {
        return in_array($this->status, ['rejected', 'cancelled']);
    }
}

