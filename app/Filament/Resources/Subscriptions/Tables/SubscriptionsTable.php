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
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount_original')
                    ->label('Valor')
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('amount_ars')
                    ->label('ARS')
                    ->state(fn (Subscription $record): string => $formatAmount(
                        $record->amount_ars,
                        'ARS',
                    ))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('amount_eur')
                    ->label('EUR')
                    ->state(fn (Subscription $record): string => $formatAmount(
                        $record->amount_eur,
                        'EUR',
                    ))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('customer_country')
                    ->label('País')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? '—'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_tax_id')
                    ->label('Datos Fiscales')
                    ->state(fn (Subscription $record): string => $record->customer_tax_id
                        ? trim($record->customer_tax_id . ' ' . ($record->customer_tax_id_type ? "({$record->customer_tax_id_type})" : ''))
                        : '—')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('price_currency')
                    ->label('Moneda facturación')
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? 'USD'))
                    ->colors([
                        'primary',
                    ])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('current_period_end')
                    ->label('Próximo ciclo')
                    ->state(fn (Subscription $record): string => $record->current_period_end
                        ? $record->current_period_end->format('d/m/Y') . ' (' . $record->current_period_end->diffForHumans() . ')'
                        : '—')
                    ->wrap()
                    ->sortable()
                    ->toggleable(),
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
}
