<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use UnitEnum;

class Invoices extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $title = 'Invoices';

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
            Action::make('refresh')
                ->label('Actualizar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->loadInvoices()),
            Action::make('download')
                ->label('Descargar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->downloadCsv()),
        ];
    }

    protected function loadInvoices(): void
    {
        try {
            /** @var StripeClient $stripe */
            $stripe = app(StripeClient::class);

            $params = [
                'limit' => 100,
                'expand' => [
                    'data.customer',
                    'data.subscription',
                ],
            ];

            if ($customerId = request()->query('customer')) {
                $params['customer'] = $customerId;
            }

            if ($status = request()->query('status')) {
                $params['status'] = $status;
            }

            $response = $stripe->invoices->all($params);

            $this->invoices = collect($response->data)
                ->map(function ($invoice) {
                    $discountTotal = collect($invoice->total_discount_amounts ?? [])
                        ->sum(fn ($amount) => $amount->amount ?? 0);

                    return [
                        'id' => $invoice->id,
                        'amount_due' => $this->formatMoney($invoice->amount_due),
                        'billing' => $invoice->collection_method,
                        'closed' => $invoice->auto_advance ? 'true' : 'false',
                        'currency' => strtoupper($invoice->currency ?? 'USD'),
                        'customer' => $invoice->customer ?? $invoice->customer_email ?? '—',
                        'date' => optional($invoice->created ? now()->setTimestamp($invoice->created) : null)?->format('Y-m-d H:i') ?? '',
                        'due_date' => optional($invoice->due_date ? now()->setTimestamp($invoice->due_date) : null)?->format('Y-m-d H:i') ?? '',
                        'number' => $invoice->number,
                        'paid' => $invoice->paid ? 'true' : 'false',
                        'subscription' => $invoice->subscription,
                        'subtotal' => $this->formatMoney($invoice->subtotal),
                        'total_discount' => $this->formatMoney($discountTotal),
                        'coupons' => collect($invoice->discounts ?? [])->map(fn ($discount) => $discount->coupon?->id)->filter()->implode(','),
                        'tax' => $this->formatMoney($invoice->tax),
                        'tax_percent' => $invoice->tax_percent,
                        'total' => $this->formatMoney($invoice->total),
                        'amount_paid' => $this->formatMoney($invoice->amount_paid),
                        'status' => $invoice->status,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url,
                        'invoice_pdf' => $invoice->invoice_pdf,
                    ];
                })
                ->all();
        } catch (\Throwable $exception) {
            report($exception);
            $this->invoices = [];
        }
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
                'Billing',
                'Closed',
                'Currency',
                'Customer',
                'Date (UTC)',
                'Due Date (UTC)',
                'Number',
                'Paid',
                'Subscription',
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

