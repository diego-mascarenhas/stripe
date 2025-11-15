<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'fetched_at',
        'provider',
        'payload',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'fetched_at' => 'datetime',
        'payload' => 'array',
    ];

    public function scopeLatestForTargets($query, array $targets, string $base)
    {
        return $query->where('base_currency', strtoupper($base))
            ->whereIn('target_currency', array_map('strtoupper', $targets))
            ->orderByDesc('fetched_at');
    }
}
