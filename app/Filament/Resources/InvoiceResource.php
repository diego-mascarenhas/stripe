<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Services\Currency\CurrencyConversionService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Facturas';

    protected static ?string $modelLabel = 'Factura';

    protected static ?string $pluralModelLabel = 'Facturas';

    protected static UnitEnum|string|null $navigationGroup = 'Facturación';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('invoice_created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->excludeDrafts())
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Número de factura')
                    ->description(fn (Invoice $record): ?string => $record->invoice_created_at?->format('d/m/Y'))
                    ->searchable()
                    ->sortable(false)
                    ->url(fn (Invoice $record): ?string => $record->hosted_invoice_url, shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->description(fn (Invoice $record): ?string => $record->customer_email)
                    ->searchable(['customer_name', 'customer_description', 'customer_email'])
                    ->sortable()
                    ->wrap()
                    ->url(fn (Invoice $record): ?string => $record->customer_id
                        ? "https://dashboard.stripe.com/customers/{$record->customer_id}"
                        : null,
                        shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('customer_tax_id')
                    ->label('ID Fiscal')
                    ->wrap()
                    ->toggleable()
                    ->formatStateUsing(fn (Invoice $record): string => $record->customer_name ?? 'Cliente')
                    ->description(fn (Invoice $record): ?string => $record->customer_tax_id),
                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Importe')
                    ->html()
                    ->formatStateUsing(fn (?float $state, Invoice $record): string => self::formatMoneyWithOriginal($state, $record))
                    ->extraCellAttributes(['class' => 'text-end']),
                Tables\Columns\TextColumn::make('computed_tax_amount')
                    ->label('Impuesto')
                    ->html()
                    ->formatStateUsing(fn (?float $state, Invoice $record): string => self::formatMoneyWithOriginal($state, $record))
                    ->extraCellAttributes(['class' => 'text-end']),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->html()
                    ->formatStateUsing(fn (?float $state, Invoice $record): string => self::formatMoneyWithOriginal($state, $record))
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
                Tables\Columns\ViewColumn::make('status')
                    ->label('Estado')
                    ->view('filament.tables.columns.invoice-status')
                    ->sortable()
                    ->extraCellAttributes(['class' => 'text-center'])
                    ->extraHeaderAttributes(['class' => 'text-center']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'open' => 'Abierta',
                        'overdue' => 'Vencida',
                        'paid' => 'Pagada',
                        'void' => 'Anulada',
                        'uncollectible' => 'Incobrable',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        if ($value === 'overdue') {
                            return $query
                                ->where('status', 'open')
                                ->whereNotNull('invoice_due_date')
                                ->where('invoice_due_date', '<', now());
                        }

                        return $query->where('status', $value);
                    }),
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
            ->emptyStateHeading('No hay facturas registradas')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
        ];
    }

    private static function formatMoneyWithOriginal(?float $amount, Invoice $record): string
    {
        if ($amount === null) {
            return '—';
        }

        $currency = strtoupper($record->currency ?? 'USD');
        $conversionService = app(CurrencyConversionService::class);
        $converted = $conversionService->convert($amount, $currency, 'EUR');
        $eurValue = $converted ?? ($currency === 'EUR' ? $amount : null);

        $main = number_format($eurValue ?? $amount, 2, ',', '.').' €';

        if ($currency === 'EUR' || $eurValue === null) {
            return "<div class=\"text-end\"><span class=\"block\">{$main}</span></div>";
        }

        $original = number_format($amount, 2, ',', '.').' '.$currency;

        return "<div class=\"text-end\"><span class=\"block\">{$main}</span><br><span class=\"text-xs text-gray-500\">{$original}</span></div>";
    }
}

