<?php

namespace App\Actions\CreditNotes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Stripe\StripeClient;

class DownloadStripeCreditNotePdfs
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    /**
     * @return array{downloaded:int, skipped:int, failed:int}
     */
    public function handle(int $year, string $disk = 'local', ?string $status = 'issued'): array
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

        // Stripe Credit Notes only support filtering by status for issued/void.
        if (filled($status)) {
            $params['status'] = $status;
        }

        $collection = $this->stripe->creditNotes->all($params);

        foreach ($collection->autoPagingIterator() as $creditNote) {
            $createdAt = Carbon::createFromTimestampUTC((int) $creditNote->created);
            $quarter = 'Q'.$createdAt->quarter;
            $baseDir = sprintf('stripe/credit-notes/%d/%d-%s', $year, $year, $quarter);

            $pdfUrl = $creditNote->pdf;

            if (blank($pdfUrl)) {
                $skipped++;

                continue;
            }

            $number = $creditNote->number ?: $creditNote->id;
            $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', $number) ?: $creditNote->id;
            $fileName = sprintf(
                '%s-%s.pdf',
                $createdAt->format('Y-m-d'),
                $safeNumber
            );
            $path = $baseDir.'/'.$fileName;

            if (Storage::disk($disk)->exists($path)) {
                $skipped++;

                continue;
            }

            try {
                $response = Http::timeout(60)->get($pdfUrl);

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
