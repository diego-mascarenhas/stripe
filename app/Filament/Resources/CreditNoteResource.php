<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditNoteResource\Pages;
use App\Models\CreditNote;
use App\Services\Currency\CurrencyConversionService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class CreditNoteResource extends Resource
{
    protected static ?string $model = CreditNote::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationLabel = 'Notas de Crédito';

    protected static ?string $modelLabel = 'Nota de Crédito';

    protected static ?string $pluralModelLabel = 'Notas de Crédito';

    protected static UnitEnum|string|null $navigationGroup = 'Facturación';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('credit_note_created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query
                ->where('voided', false)
                ->orderByDesc('credit_note_created_at')
                ->orderByRaw("CAST(REPLACE(number, '-', '') AS UNSIGNED) DESC")
            )
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Número')
                    ->description(fn (CreditNote $record): ?string => $record->credit_note_created_at?->format('d/m/Y'))
                    ->searchable()
                    ->sortable(false)
                    ->default(fn (CreditNote $record): string => $record->stripe_id)
                    ->url(fn (CreditNote $record): ?string => 
                        $record->hosted_credit_note_url 
                        ?? ($record->stripe_id ? "https://dashboard.stripe.com/credit_notes/{$record->stripe_id}" : null), 
                        shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('customer_description')
                    ->label('Cliente')
                    ->description(fn (CreditNote $record): ?string => $record->customer_email)
                    ->searchable(['customer_description', 'customer_name', 'customer_email'])
                    ->sortable()
                    ->wrap()
                    ->default(fn (CreditNote $record): string => $record->customer_name ?? '—')
                    ->url(fn (CreditNote $record): ?string => $record->customer_id
                        ? "https://dashboard.stripe.com/customers/{$record->customer_id}"
                        : null,
                        shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('customer_tax_id')
                    ->label('ID Fiscal')
                    ->wrap()
                    ->toggleable()
                    ->formatStateUsing(fn (CreditNote $record): string => $record->customer_name ?? 'Cliente')
                    ->description(fn (CreditNote $record): ?string => $record->customer_tax_id),
                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Importe')
                    ->html()
                    ->formatStateUsing(fn (?float $state, CreditNote $record): string => self::formatMoneyWithOriginal($state, $record))
                    ->extraCellAttributes(['class' => 'text-end']),
                Tables\Columns\TextColumn::make('tax')
                    ->label('Impuesto')
                    ->html()
                    ->formatStateUsing(fn (?float $state, CreditNote $record): string => self::formatMoneyWithOriginal($state, $record))
                    ->extraCellAttributes(['class' => 'text-end']),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->html()
                    ->formatStateUsing(fn (?float $state, CreditNote $record): string => self::formatMoneyWithOriginal($state, $record))
                    ->extraCellAttributes(['class' => 'text-end']),
                Tables\Columns\BadgeColumn::make('currency')
                    ->label('Moneda')
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? 'USD'))
                    ->colors([
                        'success' => 'eur',
                        'warning' => 'ars',
                        'info' => 'usd',
                    ])
                    ->extraCellAttributes(['class' => 'text-center'])
                    ->extraHeaderAttributes(['class' => 'text-center']),
                Tables\Columns\TextColumn::make('customer_address_country')
                    ->label('País')
                    ->formatStateUsing(fn (?string $state): string => $state ?? '—')
                    ->badge()
                    ->toggleable()
                    ->extraCellAttributes(['class' => 'text-center'])
                    ->extraHeaderAttributes(['class' => 'text-center']),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'issued' => 'Emitida',
                        'void' => 'Anulada',
                        default => ucfirst($state ?? '—'),
                    })
                    ->colors([
                        'success' => 'issued',
                        'danger' => 'void',
                    ])
                    ->sortable()
                    ->extraCellAttributes(['class' => 'text-center'])
                    ->extraHeaderAttributes(['class' => 'text-center']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'issued' => 'Emitida',
                        'void' => 'Anulada',
                    ]),
                Tables\Filters\SelectFilter::make('currency')
                    ->label('Moneda')
                    ->options([
                        'eur' => 'EUR',
                        'ars' => 'ARS',
                        'usd' => 'USD',
                    ]),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No hay notas de crédito registradas')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditNotes::route('/'),
        ];
    }

    private static function formatMoneyWithOriginal(?float $amount, CreditNote $record): string
    {
        if ($amount === null) {
            return '—';
        }

        $conversionService = app(CurrencyConversionService::class);
        $originalCurrency = $record->currency;

        $formattedEur = number_format($conversionService->convert($amount, $originalCurrency, 'EUR') ?? $amount, 2, ',', '.').' €';

        if (strtoupper($originalCurrency) !== 'EUR') {
            $formattedOriginal = number_format($amount, 2, ',', '.').' '.strtoupper($originalCurrency);

            return <<<HTML
                <div class="flex flex-col items-end">
                    <span>{$formattedEur}</span>
                    <span class="text-xs text-gray-500">{$formattedOriginal}</span>
                </div>
            HTML;
        }

        return $formattedEur;
    }
}

