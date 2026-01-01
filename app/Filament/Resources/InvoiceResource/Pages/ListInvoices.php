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
                    $invoiceDate = $invoice->invoice_created_at;

                    // Valores originales SIN descuento (para cálculos EUR)
                    $subtotal = $invoice->subtotal ?? 0;
                    $tax = $invoice->computed_tax_amount ?? $invoice->tax ?? 0;

                    // Total original = subtotal + tax + discount_amount
                    $discountAmount = $invoice->total_discount_amount ?? 0;
                    $total = $invoice->total ?? 0;

                    // Si hay descuento, el total original es total + discount
                    if ($discountAmount > 0) {
                        $totalOriginal = $total + $discountAmount;
                    } else {
                        $totalOriginal = $total;
                    }

                    // Valor CON descuento (para columna Importe)
                    $amountDue = $invoice->amount_due ?? $total;

                    // Obtener tipo de cambio de la fecha de la factura según la moneda
                    $rateValue = null;
                    $exchangeRateDisplay = '';

                    if ($currency === 'USD' && $invoiceDate) {
                        // Para USD, buscar directamente USD→EUR
                        $usdToEurRate = ExchangeRate::where('base_currency', 'USD')
                            ->where('target_currency', 'EUR')
                            ->where('fetched_at', '<=', $invoiceDate)
                            ->orderByDesc('fetched_at')
                            ->first();

                        // Si no encuentra en esa fecha o anterior, buscar la siguiente
                        if (!$usdToEurRate) {
                            $usdToEurRate = ExchangeRate::where('base_currency', 'USD')
                                ->where('target_currency', 'EUR')
                                ->where('fetched_at', '>=', $invoiceDate)
                                ->orderBy('fetched_at')
                                ->first();
                        }

                        if ($usdToEurRate) {
                            $rateValue = (float) $usdToEurRate->rate;
                            // Para USD→EUR, mostrar EUR→USD (invertido)
                            $eurToUsd = 1 / $rateValue;
                            $exchangeRateDisplay = number_format($eurToUsd, 4, ',', '.');
                        } else {
                            $exchangeRateDisplay = 'N/A';
                        }
                    } elseif ($currency !== 'EUR' && $invoiceDate) {
                        // Para otras monedas, calcular usando USD como intermediario

                        $usdToTargetRate = ExchangeRate::where('base_currency', 'USD')
                            ->where('target_currency', $currency)
                            ->where('fetched_at', '<=', $invoiceDate)
                            ->orderByDesc('fetched_at')
                            ->first();

                        // Si no encuentra, buscar la siguiente
                        if (!$usdToTargetRate) {
                            $usdToTargetRate = ExchangeRate::where('base_currency', 'USD')
                                ->where('target_currency', $currency)
                                ->where('fetched_at', '>=', $invoiceDate)
                                ->orderBy('fetched_at')
                                ->first();
                        }

                        $usdToEurRate = ExchangeRate::where('base_currency', 'USD')
                            ->where('target_currency', 'EUR')
                            ->where('fetched_at', '<=', $invoiceDate)
                            ->orderByDesc('fetched_at')
                            ->first();

                        // Si no encuentra, buscar la siguiente
                        if (!$usdToEurRate) {
                            $usdToEurRate = ExchangeRate::where('base_currency', 'USD')
                                ->where('target_currency', 'EUR')
                                ->where('fetched_at', '>=', $invoiceDate)
                                ->orderBy('fetched_at')
                                ->first();
                        }

                        if ($usdToTargetRate && $usdToEurRate) {
                            // Calcular MONEDA→EUR
                            $rateValue = (float) $usdToEurRate->rate / (float) $usdToTargetRate->rate;

                            // Mostrar EUR→MONEDA (invertido)
                            $eurToMoneda = 1 / $rateValue;
                            $exchangeRateDisplay = number_format($eurToMoneda, 4, ',', '.');
                        } else {
                            $exchangeRateDisplay = 'N/A';
                        }
                    }

                    // Calcular importes en EUR usando valores ORIGINALES (sin descuento)
                    $subtotalEur = null;
                    $taxEur = null;
                    $totalEur = null;

                    if ($currency === 'EUR') {
                        $subtotalEur = $subtotal;
                        $taxEur = $tax;
                        $totalEur = $totalOriginal; // Usar total original SIN descuento
                        $exchangeRateDisplay = ''; // No mostrar para EUR
                    } elseif ($rateValue) {
                        // Convertir valores ORIGINALES usando la tasa calculada
                        $subtotalEur = $subtotal * $rateValue;
                        $taxEur = $tax * $rateValue;
                        $totalEur = $totalOriginal * $rateValue; // Usar total original SIN descuento
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
                        number_format($amountDue, 2, ',', '.'), // Importe con descuento
                        $currency,
                        $exchangeRateDisplay,
                        $subtotalEurFormatted, // Subtotal SIN descuento en EUR
                        $taxEurFormatted,      // Tax SIN descuento en EUR
                        $totalEurFormatted,    // Total SIN descuento en EUR
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

