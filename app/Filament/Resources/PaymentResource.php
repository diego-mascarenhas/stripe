<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Pagos MP';

    protected static ?string $modelLabel = 'Pago';

    protected static ?string $pluralModelLabel = 'Pagos';

    protected static UnitEnum|string|null $navigationGroup = 'Facturación';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('payment_created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('mercadopago_id')
                    ->label('ID Pago')
                    ->description(fn (Payment $record): ?string => $record->payment_created_at?->format('d/m/Y H:i'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('ID copiado')
                    ->url(fn (Payment $record): ?string => $record->live_mode
                        ? "https://www.mercadopago.com.ar/activities/{$record->mercadopago_id}"
                        : null,
                        shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('payer_full_name')
                    ->label('Cliente')
                    ->description(fn (Payment $record): ?string => $record->payer_email)
                    ->searchable(['payer_email', 'payer_first_name', 'payer_last_name'])
                    ->sortable(false)
                    ->wrap(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->limit(30)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('external_reference')
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transaction_amount')
                    ->label('Monto')
                    ->money(fn (Payment $record): string => strtoupper($record->currency ?? 'ARS'))
                    ->sortable()
                    ->extraCellAttributes(['class' => 'text-end']),
                Tables\Columns\TextColumn::make('payment_method_label')
                    ->label('Método')
                    ->badge()
                    ->colors([
                        'success' => fn ($state): bool => str_contains(strtolower($state), 'crédito'),
                        'info' => fn ($state): bool => str_contains(strtolower($state), 'débito'),
                        'warning' => fn ($state): bool => str_contains(strtolower($state), 'efectivo'),
                    ])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('installments')
                    ->label('Cuotas')
                    ->formatStateUsing(fn (int $state): string => $state > 1 ? "{$state}x" : '1x')
                    ->badge()
                    ->color(fn (int $state): string => $state > 1 ? 'info' : 'gray')
                    ->extraCellAttributes(['class' => 'text-center'])
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (Payment $record): string => $record->status_label)
                    ->colors([
                        'success' => 'approved',
                        'warning' => 'pending',
                        'info' => 'in_process',
                        'danger' => fn (string $state): bool => in_array($state, ['rejected', 'cancelled', 'charged_back']),
                    ])
                    ->sortable()
                    ->extraCellAttributes(['class' => 'text-center'])
                    ->extraHeaderAttributes(['class' => 'text-center']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'approved' => 'Aprobado',
                        'pending' => 'Pendiente',
                        'in_process' => 'En proceso',
                        'rejected' => 'Rechazado',
                        'cancelled' => 'Cancelado',
                        'refunded' => 'Reembolsado',
                        'charged_back' => 'Contracargo',
                    ]),
                Tables\Filters\SelectFilter::make('payment_type')
                    ->label('Tipo de pago')
                    ->options([
                        'credit_card' => 'Tarjeta de crédito',
                        'debit_card' => 'Tarjeta de débito',
                        'ticket' => 'Efectivo',
                        'bank_transfer' => 'Transferencia',
                        'account_money' => 'Dinero en cuenta',
                    ]),
                Tables\Filters\TernaryFilter::make('live_mode')
                    ->label('Modo producción')
                    ->trueLabel('Producción')
                    ->falseLabel('Test')
                    ->placeholder('Todos'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_in_mercadopago')
                    ->label('Ver en MP')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Payment $record): ?string => $record->live_mode
                        ? "https://www.mercadopago.com.ar/activities/{$record->mercadopago_id}"
                        : null,
                        shouldOpenInNewTab: true)
                    ->visible(fn (Payment $record): bool => $record->live_mode),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No hay pagos registrados')
            ->emptyStateDescription('Los pagos de MercadoPago aparecerán aquí después de la sincronización.')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}

