<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Actions\Invoices\SyncStripeInvoices;
use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\ExchangeRate;
use App\Services\Currency\CurrencyConversionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sincronizar con Stripe')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $count = app(SyncStripeInvoices::class)->handle();

                        Notification::make()
                            ->title('Sincronización completada')
                            ->body("{$count} facturas sincronizadas desde Stripe.")
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Error al sincronizar')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('download-csv')
                ->label('Descargar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->downloadCsv()),
        ];
    }

    protected function downloadCsv(): StreamedResponse
    {
        $fileName = 'facturas-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($handle, [
                'Comprobante',
                'Fecha',
                'Razón Social',
                'ID Fiscal',
                'Importe',
                'Moneda',
                'Cambio',
                'Importe (EUR)',
                'Tax (EUR)',
                'Total (EUR)',
                'País',
                'Estado',
                'Link Factura',
            ]);

            $conversionService = app(CurrencyConversionService::class);

            // Obtener query con filtros aplicados
            $query = $this->getFilteredTableQuery();

            $query->chunk(200, function ($chunk) use ($handle) {
                foreach ($chunk as $invoice) {
                    $currency = strtoupper($invoice->currency ?? 'EUR');
                    $subtotal = $invoice->subtotal ?? 0;
                    $tax = $invoice->computed_tax_amount ?? $invoice->tax ?? 0;
                    $total = $invoice->total ?? 0;

                    // Usar amount_due que ya tiene descuentos aplicados
                    $amountWithDiscount = $invoice->amount_due ?? $total;

                    $invoiceDate = $invoice->invoice_created_at;

                    // Obtener tipo de cambio de la fecha de la factura según la moneda
                    $rateValue = null;
                    $exchangeRateDisplay = '';

                    if ($currency !== 'EUR' && $invoiceDate) {
                        // El sistema guarda USD como base, necesitamos calcular MONEDA→EUR
                        // usando USD→MONEDA y USD→EUR

                        $usdToTargetRate = ExchangeRate::where('base_currency', 'USD')
                            ->where('target_currency', $currency)
                            ->where('fetched_at', '<=', $invoiceDate)
                            ->orderByDesc('fetched_at')
                            ->first();

                        $usdToEurRate = ExchangeRate::where('base_currency', 'USD')
                            ->where('target_currency', 'EUR')
                            ->where('fetched_at', '<=', $invoiceDate)
                            ->orderByDesc('fetched_at')
                            ->first();

                        if ($usdToTargetRate && $usdToEurRate) {
                            // Para convertir MONEDA→EUR:
                            // 1 MONEDA = (1 / usdToTarget) USD
                            // X USD = usdToEur EUR
                            // Por lo tanto: 1 MONEDA = (usdToEur / usdToTarget) EUR
                            $rateValue = (float) $usdToEurRate->rate / (float) $usdToTargetRate->rate;

                            // Para mostrar EUR→MONEDA (invertido)
                            $eurToMoneda = 1 / $rateValue;
                            $exchangeRateDisplay = number_format($eurToMoneda, 4, ',', '.');
                        } else {
                            $exchangeRateDisplay = 'N/A';
                        }
                    }

                    // Calcular importes en EUR
                    $subtotalEur = null;
                    $taxEur = null;
                    $totalEur = null;

                    if ($currency === 'EUR') {
                        $subtotalEur = $subtotal;
                        $taxEur = $tax;
                        $totalEur = $amountWithDiscount;
                    } elseif ($rateValue) {
                        // Convertir usando la tasa calculada MONEDA→EUR
                        $subtotalEur = $subtotal * $rateValue;
                        $taxEur = $tax * $rateValue;
                        $totalEur = $amountWithDiscount * $rateValue;
                    }

                    // Formatear montos en EUR
                    $subtotalEurFormatted = $subtotalEur !== null ? number_format($subtotalEur, 2, ',', '.') : '';
                    $taxEurFormatted = $taxEur !== null ? number_format($taxEur, 2, ',', '.') : '';
                    $totalEurFormatted = $totalEur !== null ? number_format($totalEur, 2, ',', '.') : '';

                    // Extract clean tax ID (number only)
                    $taxId = $invoice->customer_tax_id;
                    if ($taxId && preg_match('/^(.+?)\s*\(([^)]+)\)$/', $taxId, $matches)) {
                        $taxId = $matches[1];
                    } elseif ($taxId && preg_match('/^([\d\-]+)([a-z_]+)$/i', $taxId, $matches)) {
                        $taxId = $matches[1];
                    }

                    // Status translation
                    $statusLabel = match ($invoice->status) {
                        'paid' => 'Pagada',
                        'open' => 'Abierta',
                        'void' => 'Anulada',
                        'uncollectible' => 'Incobrable',
                        'draft' => 'Borrador',
                        default => ucfirst($invoice->status ?? '—'),
                    };

                    // Link de descarga de la factura
                    $invoiceLink = $invoice->invoice_pdf ?? $invoice->hosted_invoice_url ?? '';

                    fputcsv($handle, [
                        $invoice->number ?? $invoice->stripe_id,
                        $invoiceDate?->format('d/m/Y') ?? '',
                        $invoice->customer_name ?? '',
                        $taxId ?? '',
                        number_format($subtotal, 2, ',', '.'),
                        $currency,
                        $exchangeRateDisplay,
                        $subtotalEurFormatted,
                        $taxEurFormatted,
                        $totalEurFormatted,
                        strtoupper($invoice->customer_address_country ?? ''),
                        $statusLabel,
                        $invoiceLink,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    public function getFilteredTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        // Query base (misma que usa la tabla)
        $query = Invoice::query()
            ->where('status', '!=', 'draft')
            ->orderByDesc('invoice_created_at')
            ->orderByRaw("CAST(REPLACE(number, '-', '') AS UNSIGNED) DESC");

        // Obtener filtros activos
        $filters = $this->tableFilters;

        // Aplicar filtro de Estado
        if (isset($filters['status']['value']) && filled($filters['status']['value'])) {
            $statusValue = $filters['status']['value'];

            if ($statusValue === 'overdue') {
                $query->where('status', 'open')
                    ->whereNotNull('invoice_due_date')
                    ->where('invoice_due_date', '<', now());
            } else {
                $query->where('status', $statusValue);
            }
        }

        // Aplicar filtro de Moneda
        if (isset($filters['currency']['value']) && filled($filters['currency']['value'])) {
            $query->where('currency', $filters['currency']['value']);
        }

        return $query;
    }
}

