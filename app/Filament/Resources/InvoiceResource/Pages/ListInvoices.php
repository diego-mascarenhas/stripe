<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Actions\Invoices\SyncStripeInvoices;
use App\Filament\Resources\InvoiceResource;
use App\Jobs\GenerateInvoicesZipJob;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Services\Currency\CurrencyConversionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    protected function getHeaderActions(): array
    {
        $quarterRange = $this->getPreviousQuarterRange();
        $fileName = "facturas-Q{$quarterRange['quarter']}-{$quarterRange['year']}.zip";
        $zipExists = Storage::disk('public')->exists('invoices-zip/'.$fileName);
        $useQueue = config('queue.default') !== 'sync';

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
            Action::make('generate-previous-quarter')
                ->label($zipExists ? 'Regenerar ZIP Trimestre Anterior' : 'Generar ZIP Trimestre Anterior')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar ZIP de facturas')
                ->modalDescription(function () use ($quarterRange, $zipExists, $useQueue) {
                    $message = "Se generará un archivo ZIP con todas las facturas entre {$quarterRange['start']->format('d/m/Y')} y {$quarterRange['end']->format('d/m/Y')}.";
                    if ($zipExists) {
                        $message .= "\n\nYa existe un archivo ZIP. Si continúas, se regenerará.";
                    }
                    if ($useQueue) {
                        $message .= "\n\nEl proceso se ejecutará en segundo plano. Recarga la página en unos minutos para ver el botón de descarga.";
                    } else {
                        $message .= "\n\nNOTA: El proceso puede tardar varios minutos. No cierres esta ventana.";
                    }

                    return $message;
                })
                ->action(fn () => $this->generatePreviousQuarterZip()),
            Action::make('download-previous-quarter-zip')
                ->label('Descargar ZIP Trimestre Anterior')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('info')
                ->visible($zipExists)
                ->action(fn () => $this->downloadGeneratedZip($fileName)),
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
                'Link',
            ]);

            $conversionService = app(CurrencyConversionService::class);

            // Obtener query con filtros aplicados
            $query = $this->getFilteredTableQuery();

            $query->chunk(200, function ($chunk) use ($handle) {
                foreach ($chunk as $invoice) {
                    $currency = strtoupper($invoice->currency ?? 'EUR');
                    $invoiceDate = $invoice->invoice_created_at;

                    // Payload crudo para obtener subtotales y total con/sin descuento
                    $payload = $invoice->raw_payload ?? [];

                    // Valores reportados por Stripe (centavos)
                    $rawSubtotal = data_get($payload, 'subtotal');
                    $rawTotal = data_get($payload, 'total');
                    $rawTax = data_get($payload, 'tax');
                    $rawDiscountAmounts = data_get($payload, 'total_discount_amounts', []);
                    $rawDiscount = 0;
                    if (is_array($rawDiscountAmounts)) {
                        $rawDiscount = collect($rawDiscountAmounts)->sum(fn ($d) => data_get($d, 'amount', 0));
                    }

                    // Convertir a unidades monetarias
                    $payloadSubtotal = $rawSubtotal !== null ? $rawSubtotal / 100 : null;
                    $payloadTotal = $rawTotal !== null ? $rawTotal / 100 : null;
                    $payloadTax = $rawTax !== null ? $rawTax / 100 : null;
                    $payloadDiscount = $rawDiscount / 100;

                    // Valor realmente cobrado
                    $amountDue = $invoice->amount_due ?? $payloadTotal ?? $invoice->total ?? 0;

                    // Subtotal/total base para calcular el ratio de descuento
                    $baseSubtotal = $invoice->subtotal ?? $payloadSubtotal ?? 0;
                    $baseTotal = $invoice->total ?? $payloadTotal ?? 0;

                    // Si tenemos subtotal/total del payload, usar ratio preciso
                    if ($payloadSubtotal && $payloadTotal) {
                        $discountRatio = $payloadSubtotal > 0 ? ($payloadTotal / $payloadSubtotal) : 1;
                    } else {
                        // Fallback: usar descuentos registrados o amount_due
                        $totalOriginal = $baseTotal + ($invoice->total_discount_amount ?? $payloadDiscount ?? 0);
                        $discountRatio = $totalOriginal > 0 ? ($amountDue / $totalOriginal) : 1;
                    }

                    // Si la factura/nota de crédito quedó en cero, forzar todo a cero
                    if (($amountDue ?? 0) == 0) {
                        $subtotal = 0;
                        $tax = 0;
                        $total = 0;
                        $discountRatio = 0;
                    } else {
                        // Aplicar ratio a subtotal y tax
                        $subtotal = $baseSubtotal * $discountRatio;
                        $tax = ($invoice->computed_tax_amount ?? $invoice->tax ?? $payloadTax ?? 0) * $discountRatio;
                        $total = $amountDue;
                    }

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

                    // Calcular importes en EUR usando valores CON descuento
                    $subtotalEur = null;
                    $taxEur = null;
                    $totalEur = null;

                    if ($currency === 'EUR') {
                        $subtotalEur = $subtotal;
                        $taxEur = $tax;
                        $totalEur = $total; // Valor CON descuento
                        $exchangeRateDisplay = ''; // No mostrar para EUR
                    } elseif ($rateValue) {
                        // Convertir valores CON descuento usando la tasa calculada
                        $subtotalEur = $subtotal * $rateValue;
                        $taxEur = $tax * $rateValue;
                        $totalEur = $total * $rateValue; // Valor CON descuento
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
                        number_format($total, 2, ',', '.'), // Importe con descuento
                        $currency,
                        $exchangeRateDisplay,
                        $subtotalEurFormatted, // Subtotal con descuento en EUR
                        $taxEurFormatted,      // Tax con descuento en EUR
                        $totalEurFormatted,    // Total con descuento en EUR
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

    protected function getPreviousQuarterRange(): array
    {
        $now = now();
        $currentQuarter = (int) ceil($now->month / 3);
        $previousQuarter = $currentQuarter - 1;
        $year = $now->year;

        if ($previousQuarter < 1) {
            $previousQuarter = 4;
            $year--;
        }

        $startMonth = ($previousQuarter - 1) * 3 + 1;
        $endMonth = $startMonth + 2;

        $start = now()->setYear($year)->setMonth($startMonth)->startOfMonth();
        $end = now()->setYear($year)->setMonth($endMonth)->endOfMonth();

        return [
            'start' => $start,
            'end' => $end,
            'quarter' => $previousQuarter,
            'year' => $year,
        ];
    }

    protected function generatePreviousQuarterZip(): void
    {
        $quarterRange = $this->getPreviousQuarterRange();
        $quarter = $quarterRange['quarter'];
        $year = $quarterRange['year'];
        $fileName = "facturas-Q{$quarter}-{$year}.zip";

        // Verificar si hay facturas
        $count = Invoice::where('status', '!=', 'draft')
            ->whereBetween('invoice_created_at', [$quarterRange['start'], $quarterRange['end']])
            ->count();

        if ($count === 0) {
            Notification::make()
                ->title('Sin facturas')
                ->body("No se encontraron facturas para el trimestre {$quarter}/{$year}.")
                ->warning()
                ->send();

            return;
        }

        $useQueue = config('queue.default') !== 'sync';

        if ($useQueue) {
            // Dispatch del Job en background
            GenerateInvoicesZipJob::dispatch(
                $quarterRange['start']->toDateTimeString(),
                $quarterRange['end']->toDateTimeString(),
                $quarter,
                $year,
                $fileName
            );

            Notification::make()
                ->title('Generación iniciada')
                ->body("El ZIP con {$count} facturas se está generando en segundo plano. Recarga la página en unos minutos para ver el botón de descarga.")
                ->success()
                ->duration(10000)
                ->send();
        } else {
            // Ejecutar síncronamente con timeouts aumentados
            set_time_limit(600); // 10 minutos
            ini_set('max_execution_time', '600');

            try {
                $job = new GenerateInvoicesZipJob(
                    $quarterRange['start']->toDateTimeString(),
                    $quarterRange['end']->toDateTimeString(),
                    $quarter,
                    $year,
                    $fileName
                );

                $job->handle();

                Notification::make()
                    ->title('ZIP generado correctamente')
                    ->body("El ZIP con las facturas está listo para descargar. Recarga la página para ver el botón de descarga.")
                    ->success()
                    ->duration(10000)
                    ->send();
            } catch (\Throwable $exception) {
                Notification::make()
                    ->title('Error al generar ZIP')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }

    protected function downloadGeneratedZip(string $fileName): BinaryFileResponse
    {
        $zipPath = storage_path('app/public/invoices-zip/'.$fileName);

        if (! file_exists($zipPath)) {
            Notification::make()
                ->title('Archivo no encontrado')
                ->body('El archivo ZIP ya no existe o aún se está generando.')
                ->warning()
                ->send();

            return response()->download($zipPath);
        }

        return response()->download($zipPath, $fileName);
    }
}

