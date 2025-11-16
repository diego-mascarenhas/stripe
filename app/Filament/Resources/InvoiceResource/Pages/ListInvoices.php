<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Actions\Invoices\SyncStripeInvoices;
use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
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
                'Tax',
                'Total (EUR)',
                'País',
                'Estado',
            ]);

            $conversionService = app(CurrencyConversionService::class);

            Invoice::where('status', '!=', 'draft')
                ->orderByDesc('invoice_created_at')
                ->orderByRaw("CAST(REPLACE(number, '-', '') AS UNSIGNED) DESC")
                ->chunk(200, function ($chunk) use ($handle, $conversionService) {
                    foreach ($chunk as $invoice) {
                        $currency = strtoupper($invoice->currency ?? 'USD');
                        $subtotal = $invoice->subtotal ?? 0;
                        $tax = $invoice->computed_tax_amount ?? $invoice->tax ?? 0;
                        $total = $invoice->total ?? 0;

                        // Tax only shows for EUR invoices
                        $taxDisplay = $currency === 'EUR' ? number_format($tax, 2, ',', '.') : '';

                        // Convert total to EUR
                        $totalEur = $conversionService->convert($total, $currency, 'EUR');
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

                        fputcsv($handle, [
                            $invoice->number ?? $invoice->stripe_id,
                            $invoice->invoice_created_at?->format('d/m/Y') ?? '',
                            $invoice->customer_name ?? '',
                            $taxId ?? '',
                            number_format($subtotal, 2, ',', '.'),
                            $currency,
                            $taxDisplay,
                            $totalEurFormatted,
                            strtoupper($invoice->customer_address_country ?? ''),
                            $statusLabel,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}

