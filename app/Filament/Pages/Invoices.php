<?php

namespace App\Filament\Pages;

use App\Actions\Invoices\SyncStripeInvoices;
use App\Models\Invoice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class Invoices extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Facturas';

    protected static ?string $title = 'Facturas';

    protected static ?string $slug = 'invoices';

    protected static UnitEnum|string|null $navigationGroup = 'Facturación';

    protected string $view = 'filament.pages.invoices';

    /** @var array<int, array<string, mixed>> */
    public array $invoices = [];

    public function mount(): void
    {
        $this->loadInvoices();
    }

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

                        $this->loadInvoices();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Error al sincronizar')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('download')
                ->label('Descargar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->downloadCsv()),
        ];
    }

    protected function loadInvoices(): void
    {
        $this->invoices = Invoice::query()
            ->orderByDesc('invoice_created_at')
            ->limit(200)
            ->get()
            ->map(function (Invoice $invoice) {
                return [
                    'id' => $invoice->stripe_id,
                    'amount_due' => number_format($invoice->amount_due ?? 0, 2, ',', '.'),
                    'billing' => $invoice->billing_reason,
                    'closed' => $invoice->closed ? 'true' : 'false',
                    'currency' => $invoice->currency,
                    'customer' => $invoice->customer_name ?? $invoice->customer_email ?? '—',
                    'date' => $invoice->invoice_created_at?->format('Y-m-d H:i:s') ?? '',
                    'due_date' => $invoice->invoice_due_date?->format('Y-m-d H:i:s') ?? '',
                    'number' => $invoice->number,
                    'paid' => $invoice->paid ? 'true' : 'false',
                    'subscription' => $invoice->stripe_subscription_id ?? 'N/A',
                    'subtotal' => number_format($invoice->subtotal ?? 0, 2, ',', '.'),
                    'total_discount' => number_format($invoice->total_discount_amount ?? 0, 2, ',', '.'),
                    'coupons' => $invoice->applied_coupons ?? '',
                    'tax' => number_format($invoice->tax ?? 0, 2, ',', '.'),
                    'tax_percent' => $invoice->raw_payload['tax_percent'] ?? 0,
                    'total' => number_format($invoice->total ?? 0, 2, ',', '.'),
                    'amount_paid' => number_format($invoice->amount_paid ?? 0, 2, ',', '.'),
                    'status' => $invoice->status,
                    'invoice_pdf' => $invoice->invoice_pdf,
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                ];
            })
            ->all();
    }

    protected function downloadCsv()
    {
        $fileName = 'invoices-' . now()->format('Y-m-d-His') . '.csv';
        $invoices = $this->invoices;

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id',
                'Amount Due',
                'Billing Reason',
                'Closed',
                'Currency',
                'Customer',
                'Date (UTC)',
                'Due Date (UTC)',
                'Number',
                'Paid',
                'Subscription ID',
                'Subtotal',
                'Total Discount Amount',
                'Applied Coupons',
                'Tax',
                'Tax Percent',
                'Total',
                'Amount Paid',
                'Status',
            ]);

            foreach ($invoices as $invoice) {
                fputcsv($handle, [
                    $invoice['id'],
                    $invoice['amount_due'],
                    $invoice['billing'],
                    $invoice['closed'],
                    $invoice['currency'],
                    $invoice['customer'],
                    $invoice['date'],
                    $invoice['due_date'],
                    $invoice['number'],
                    $invoice['paid'],
                    $invoice['subscription'],
                    $invoice['subtotal'],
                    $invoice['total_discount'],
                    $invoice['coupons'],
                    $invoice['tax'],
                    $invoice['tax_percent'],
                    $invoice['total'],
                    $invoice['amount_paid'],
                    $invoice['status'],
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function formatMoney(?int $amount): string
    {
        if ($amount === null) {
            return '0,00';
        }

        return number_format($amount / 100, 2, ',', '.');
    }
}

