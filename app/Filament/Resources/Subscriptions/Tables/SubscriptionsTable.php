<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Actions\Subscriptions\UpdateStripeSubscriptionMetadata;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Support\Subscriptions\ManualPurchaseManager;
use App\Support\Subscriptions\SubscriptionMetadataManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
            ->defaultSort('current_period_end', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->description(fn (Subscription $record): ?string => $record->customer_email)
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('plan_name')
                    ->label('Plan')
                    ->description(fn (Subscription $record): ?string => $record->billing_frequency)
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'buy' => 'Compra',
                        default => 'Venta',
                    })
                    ->colors([
                        'danger' => 'buy',
                        'success' => 'sell',
                    ])
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('current_period_end')
                    ->label('Próxima')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_original')
                    ->label('Valor')
                    ->state(fn (Subscription $record): string => $formatAmount(
                        $originalAmount($record),
                        $record->price_currency,
                    ))
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
                
                // DNS and WHM Status Icons
                Tables\Columns\IconColumn::make('data.dns_has_own_ns')
                    ->label('')
                    ->icon(fn (?string $state): string => 'heroicon-o-server')
                    ->color(fn (?string $state): string => $state === 'true' ? 'success' : ($state === 'false' ? 'warning' : 'gray'))
                    ->tooltip(fn (?string $state, ?Subscription $record): string => 
                        $state === 'true' ? 'Nameservers configurados' : 
                        ($state === 'false' ? 'Nameservers no configurados' : 
                        'Sin sincronizar')
                    )
                    ->default('—')
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('data.dns_domain_points_to_service')
                    ->label('')
                    ->icon(fn (?string $state): string => 'heroicon-o-globe-alt')
                    ->color(fn (?string $state): string => $state === 'true' ? 'success' : ($state === 'false' ? 'danger' : 'gray'))
                    ->tooltip(fn (?string $state): string => 
                        $state === 'true' ? 'Dominio apunta correctamente' : 
                        ($state === 'false' ? 'Dominio no apunta' : 
                        'Sin sincronizar')
                    )
                    ->default('—')
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('data.dns_mail_points_to_service')
                    ->label('')
                    ->icon(fn (?string $state): string => 'heroicon-o-envelope')
                    ->color(fn (?string $state): string => $state === 'true' ? 'success' : ($state === 'false' ? 'danger' : 'gray'))
                    ->tooltip(fn (?string $state): string => 
                        $state === 'true' ? 'Mail apunta correctamente' : 
                        ($state === 'false' ? 'Mail no apunta' : 
                        'Sin sincronizar')
                    )
                    ->default('—')
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('data.whm_status')
                    ->label('')
                    ->icon(fn (?string $state): string => match($state) {
                        'active' => 'heroicon-o-check-circle',
                        'suspended' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (?string $state): string => match($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (?string $state): string => match($state) {
                        'active' => 'Servicio activo',
                        'suspended' => 'Servicio suspendido',
                        default => 'Sin sincronizar',
                    })
                    ->default('—')
                    ->toggleable(),
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('price_currency')
                    ->label('Moneda facturación')
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? 'USD'))
                    ->colors([
                        'primary',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('data.server')
                    ->label('Servidor')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('data.domain')
                    ->label('Dominio')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('data.user')
                    ->label('Usuario')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('data.email')
                    ->label('Email metadata')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'sell' => 'Venta',
                        'buy' => 'Compra',
                    ]),
            ])
            ->recordUrl(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record]))
            ->actions([
                Action::make('edit')
                    ->label('')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->tooltip('Editar compra')
                    ->visible(fn (Subscription $record): bool => $record->type === 'buy')
                    ->form(fn () => ManualPurchaseManager::schema())
                    ->fillForm(function (Subscription $record): array {
                        return [
                            'vendor_name' => $record->customer_name,
                            'vendor_email' => $record->customer_email,
                            'plan_name' => $record->plan_name,
                            'plan_interval' => $record->plan_interval ?? 'month',
                            'status' => $record->status ?? 'active',
                            'price_currency' => strtolower($record->price_currency ?? 'eur'),
                            'amount_total' => $record->amount_total ?? 0,
                            'current_period_end' => $record->current_period_end ?? now()->addMonth(),
                            'notes' => $record->invoice_note,
                        ];
                    })
                    ->action(function (array $data, Subscription $record): void {
                        ManualPurchaseManager::save($data, $record);

                        Notification::make()
                            ->title('Compra actualizada')
                            ->body('La suscripción de compra se actualizó correctamente.')
                            ->success()
                            ->send();
                    }),
                Action::make('metadata')
                    ->label('')
                    ->icon('heroicon-o-tag')
                    ->color('info')
                    ->tooltip('Editar metadata')
                    ->visible(fn (Subscription $record): bool => $record->type === 'sell')
                    ->form(fn () => SubscriptionMetadataManager::schema())
                    ->fillForm(fn (Subscription $record): array => SubscriptionMetadataManager::fillForm($record))
                    ->action(function (array $data, Subscription $record): void {
                        try {
                            app(UpdateStripeSubscriptionMetadata::class)->handle($record, $data);

                            Notification::make()
                                ->title('Metadata actualizada')
                                ->body('La metadata de la suscripción se actualizó correctamente en Stripe.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Error al actualizar')
                                ->body('No se pudo actualizar la metadata: '.$exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No hay suscripciones registradas')
            ->poll('60s');
    }
}
