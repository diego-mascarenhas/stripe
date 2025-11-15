<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        $formatAmount = static function (?float $amount, ?string $currency): string {
            if ($amount === null || $currency === null) {
                return '—';
            }

            return number_format($amount, 2, ',', '.').' '.strtoupper($currency);
        };

        $originalAmount = static function (Subscription $record): ?float {
            return $record->amount_total
                ?? $record->amount_subtotal
                ?? ($record->unit_amount !== null
                    ? (float) $record->unit_amount * (int) max($record->quantity ?? 1, 1)
                    : null);
        };

        return $table
            ->defaultSort('current_period_end', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->description(fn (Subscription $record): ?string => $record->customer_email)
                    ->searchable()
                    ->wrap(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Activa',
                        'past_due' => 'Vencida',
                        'canceled' => 'Cancelada',
                        'trialing' => 'En prueba',
                        'incomplete' => 'Incompleta',
                        'unpaid' => 'Impaga',
                        'incomplete_expired' => 'Expirada',
                        default => ucfirst($state ?? '—'),
                    })
                    ->colors([
                        'success' => static fn ($state): bool => in_array($state, ['active', 'trialing']),
                        'warning' => static fn ($state): bool => in_array($state, ['past_due', 'incomplete']),
                        'danger' => static fn ($state): bool => in_array($state, ['canceled', 'unpaid', 'incomplete_expired']),
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan_name')
                    ->label('Plan')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('billing_frequency')
                    ->label('Frecuencia')
                    ->state(fn (Subscription $record): ?string => self::formatFrequency($record))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount_original')
                    ->label('Importe original')
                    ->state(fn (Subscription $record): string => $formatAmount(
                        $originalAmount($record),
                        $record->price_currency,
                    ))
                    ->wrap(),
                Tables\Columns\TextColumn::make('amount_usd')
                    ->label('USD')
                    ->state(fn (Subscription $record): string => $formatAmount(
                        $record->amount_usd ?? $record->amount_total ?? $record->amount_subtotal,
                        'USD',
                    ))
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_ars')
                    ->label('ARS')
                    ->state(fn (Subscription $record): string => $formatAmount(
                        $record->amount_ars,
                        'ARS',
                    ))
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_eur')
                    ->label('EUR')
                    ->state(fn (Subscription $record): string => $formatAmount(
                        $record->amount_eur,
                        'EUR',
                    ))
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_country')
                    ->label('País')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? '—'))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('price_currency')
                    ->label('Moneda facturación')
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? 'USD'))
                    ->colors([
                        'primary',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->multiple()
                    ->default(['past_due', 'active'])
                    ->options([
                        'active' => 'Activa',
                        'past_due' => 'Vencida',
                        'canceled' => 'Cancelada',
                        'trialing' => 'En prueba',
                        'incomplete' => 'Incompleta',
                        'unpaid' => 'Impaga',
                        'incomplete_expired' => 'Expirada',
                    ]),
                Tables\Filters\SelectFilter::make('plan_name')
                    ->label('Plan')
                    ->options(fn (): array => Subscription::query()
                        ->whereNotNull('plan_name')
                        ->distinct()
                        ->orderBy('plan_name')
                        ->pluck('plan_name', 'plan_name')
                        ->toArray()),
            ])
            ->recordUrl(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record]))
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No hay suscripciones registradas')
            ->poll('60s');
    }

    private static function formatFrequency(Subscription $subscription): ?string
    {
        if (! $subscription->plan_interval) {
            return null;
        }

        $count = $subscription->plan_interval_count ?? 1;
        $intervalMap = [
            'day' => ['singular' => 'día', 'plural' => 'días'],
            'week' => ['singular' => 'semana', 'plural' => 'semanas'],
            'month' => ['singular' => 'mes', 'plural' => 'meses'],
            'year' => ['singular' => 'año', 'plural' => 'años'],
        ];

        $interval = $intervalMap[$subscription->plan_interval] ?? [
            'singular' => $subscription->plan_interval,
            'plural' => "{$subscription->plan_interval}s",
        ];

        $label = $count > 1 ? $interval['plural'] : $interval['singular'];

        return "{$count} {$label}";
    }
}
