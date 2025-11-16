<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Actions\Invoices\SyncStripeInvoices;
use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
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
        $fileName = 'invoices-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Número',
                'Cliente',
                'Email',
                'Fecha',
                'Total',
                'Moneda',
                'Estado',
                'Suscripción',
                'Subtotal',
                'Impuestos',
                'Descuentos',
                'Cupones',
                'Pagado',
            ]);

            Invoice::orderByDesc('invoice_created_at')
                ->chunk(200, function ($chunk) use ($handle) {
                    foreach ($chunk as $invoice) {
                        fputcsv($handle, [
                            $invoice->number ?? $invoice->stripe_id,
                            $invoice->customer_name,
                            $invoice->customer_email,
                            $invoice->invoice_created_at?->format('Y-m-d H:i:s') ?? '',
                            $invoice->total ?? 0,
                            strtoupper($invoice->currency ?? 'USD'),
                            $invoice->status,
                            $invoice->stripe_subscription_id ?? 'N/A',
                            $invoice->subtotal ?? 0,
                            $invoice->tax ?? 0,
                            $invoice->total_discount_amount ?? 0,
                            $invoice->applied_coupons ?? '',
                            $invoice->paid ? 'Sí' : 'No',
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}

