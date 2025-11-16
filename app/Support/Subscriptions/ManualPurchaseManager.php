<?php

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Services\Currency\CurrencyConversionService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class ManualPurchaseManager
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('vendor_name')
                ->label('Proveedor')
                ->required()
                ->maxLength(255),
            TextInput::make('vendor_email')
                ->label('Email proveedor')
                ->email()
                ->maxLength(255),
            TextInput::make('plan_name')
                ->label('Servicio')
                ->required()
                ->maxLength(255),
            Select::make('plan_interval')
                ->label('Frecuencia')
                ->options([
                    'week' => 'Semanal',
                    'month' => 'Mensual',
                    'quarter' => 'Trimestral',
                    'semester' => 'Semestral',
                    'year' => 'Anual',
                    'biennial' => 'Cada 2 años',
                    'quinquennial' => 'Cada 5 años',
                    'decennial' => 'Cada 10 años',
                    'indefinite' => 'Indefinido',
                ])
                ->default('month')
                ->reactive()
                ->required(),
            TextInput::make('plan_interval_count')
                ->label('Cantidad de periodos')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required(fn ($get) => $get('plan_interval') !== 'indefinite')
                ->visible(fn ($get) => $get('plan_interval') !== 'indefinite'),
            Select::make('price_currency')
                ->label('Moneda')
                ->options([
                    'ars' => 'ARS',
                    'usd' => 'USD',
                    'eur' => 'EUR',
                ])
                ->default('eur')
                ->required(),
            TextInput::make('amount_total')
                ->label('Importe')
                ->numeric()
                ->required()
                ->prefixIcon('heroicon-o-banknotes'),
            DatePicker::make('current_period_end')
                ->label('Próximo pago')
                ->default(now()->addMonth())
                ->required(),
            Textarea::make('notes')
                ->label('Notas')
                ->rows(3),
        ];
    }

    public static function save(array $data, ?Subscription $record = null): Subscription
    {
        $currency = strtoupper($data['price_currency']);
        $amount = (float) $data['amount_total'];
        $interval = $data['plan_interval'];
        $intervalCount = $interval === 'indefinite'
            ? null
            : (int) ($data['plan_interval_count'] ?? 1);

        $conversion = app(CurrencyConversionService::class)
            ->convertForTargets($amount, $currency, ['USD', 'ARS', 'EUR']);

        $payload = [
            'type' => 'buy',
            'customer_name' => $data['vendor_name'],
            'customer_email' => $data['vendor_email'] ?? null,
            'plan_name' => $data['plan_name'],
            'plan_interval' => $interval,
            'plan_interval_count' => $intervalCount,
            'price_currency' => $currency,
            'amount_subtotal' => $amount,
            'amount_total' => $amount,
            'amount_usd' => $conversion['USD'] ?? ($currency === 'USD' ? $amount : null),
            'amount_ars' => $conversion['ARS'] ?? null,
            'amount_eur' => $conversion['EUR'] ?? ($currency === 'EUR' ? $amount : null),
            'quantity' => 1,
            'status' => 'active',
            'collection_method' => 'manual',
            'current_period_start' => now(),
            'current_period_end' => $data['current_period_end'],
            'invoice_note' => $data['notes'] ?? null,
            'last_synced_at' => now(),
        ];

        if ($record) {
            $record->forceFill($payload)->save();

            return $record;
        }

        return Subscription::create([
            'stripe_id' => 'manual-'.Str::uuid(),
            ...$payload,
        ]);
    }
}

