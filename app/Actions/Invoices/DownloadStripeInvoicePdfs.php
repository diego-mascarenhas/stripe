<?php

namespace App\Actions\Invoices;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Stripe\StripeClient;

class DownloadStripeInvoicePdfs
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    /**
     * @return array{downloaded:int, skipped:int, failed:int}
     */
    public function handle(int $year, string $disk = 'local', ?string $status = 'paid'): array
    {
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        $from = Carbon::create($year, 1, 1, 0, 0, 0, 'UTC')->timestamp;
        $to = Carbon::create($year + 1, 1, 1, 0, 0, 0, 'UTC')->timestamp;

        $params = [
            'limit' => 100,
            'created' => [
                'gte' => $from,
                'lt' => $to,
            ],
        ];

        if (filled($status)) {
            $params['status'] = $status;
        }

        $collection = $this->stripe->invoices->all($params);

        foreach ($collection->autoPagingIterator() as $invoice) {
            $createdAt = Carbon::createFromTimestampUTC((int) $invoice->created);
            $quarter = 'Q'.$createdAt->quarter;
            $baseDir = sprintf('stripe/invoices/%d/%d-%s', $year, $year, $quarter);

            $invoicePdfUrl = $invoice->invoice_pdf;

            if (blank($invoicePdfUrl)) {
                $skipped++;

                continue;
            }

            $invoiceNumber = $invoice->number ?: $invoice->id;
            $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', $invoiceNumber) ?: $invoice->id;
            $fileName = sprintf(
                '%s-%s.pdf',
                $createdAt->format('Y-m-d'),
                $safeInvoiceNumber
            );
            $path = $baseDir.'/'.$fileName;

            if (Storage::disk($disk)->exists($path)) {
                $skipped++;

                continue;
            }

            try {
                $response = Http::timeout(60)->get($invoicePdfUrl);

                if (! $response->successful()) {
                    $failed++;

                    continue;
                }

                Storage::disk($disk)->put($path, $response->body());
                $downloaded++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return [
            'downloaded' => $downloaded,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }
}
