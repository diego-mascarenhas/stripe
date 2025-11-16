<?php

namespace App\Filament\Resources\CreditNoteResource\Pages;

use App\Actions\CreditNotes\SyncStripeCreditNotes;
use App\Filament\Resources\CreditNoteResource;
use App\Models\CreditNote;
use App\Services\Currency\CurrencyConversionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCreditNotes extends ListRecords
{
    protected static string $resource = CreditNoteResource::class;

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
                        $count = app(SyncStripeCreditNotes::class)->handle();

                        Notification::make()
                            ->title('Sincronización completada')
                            ->body("{$count} notas de crédito sincronizadas desde Stripe.")
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
        $fileName = 'notas-credito-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($handle, [
                'Número',
                'Fecha',
                'Razón Social',
                'ID Fiscal',
                'Importe',
                'Moneda',
                'Tax',
                'Total (EUR)',
                'País',
                'Estado',
                'Tipo',
                'Razón',
                'Memo',
            ]);

            $conversionService = app(CurrencyConversionService::class);

            CreditNote::where('voided', false)
                ->orderByDesc('credit_note_created_at')
                ->orderByRaw("CAST(REPLACE(number, '-', '') AS UNSIGNED) DESC")
                ->chunk(200, function ($chunk) use ($handle, $conversionService) {
                    foreach ($chunk as $creditNote) {
                        $currency = strtoupper($creditNote->currency ?? 'USD');
                        $subtotal = $creditNote->subtotal ?? 0;
                        $tax = $creditNote->tax ?? 0;
                        $total = $creditNote->total ?? 0;

                        // Tax only shows for EUR
                        $taxDisplay = $currency === 'EUR' ? number_format($tax, 2, ',', '.') : '';

                        // Convert total to EUR
                        $totalEur = $conversionService->convert($total, $currency, 'EUR');
                        $totalEurFormatted = $totalEur !== null ? number_format($totalEur, 2, ',', '.') : '';

                        // Status translation
                        $statusLabel = match ($creditNote->status) {
                            'issued' => 'Emitida',
                            'void' => 'Anulada',
                            default => ucfirst($creditNote->status ?? '—'),
                        };

                        // Type translation
                        $typeLabel = match ($creditNote->type) {
                            'pre_payment' => 'Pre-pago',
                            'post_payment' => 'Post-pago',
                            default => ucfirst($creditNote->type ?? '—'),
                        };

                        // Reason translation
                        $reasonLabel = match ($creditNote->reason) {
                            'duplicate' => 'Duplicado',
                            'fraudulent' => 'Fraudulento',
                            'order_change' => 'Cambio de orden',
                            'product_unsatisfactory' => 'Producto insatisfactorio',
                            default => ucfirst($creditNote->reason ?? '—'),
                        };

                        fputcsv($handle, [
                            $creditNote->number ?? $creditNote->stripe_id,
                            $creditNote->credit_note_created_at?->format('d/m/Y') ?? '',
                            $creditNote->customer_name ?? '',
                            $creditNote->customer_tax_id ?? '',
                            number_format($subtotal, 2, ',', '.'),
                            $currency,
                            $taxDisplay,
                            $totalEurFormatted,
                            strtoupper($creditNote->customer_address_country ?? ''),
                            $statusLabel,
                            $typeLabel,
                            $reasonLabel,
                            $creditNote->memo ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}

