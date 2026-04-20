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
    public function handle(int $year, string $disk = 'local', bool $includeVoided = false): array
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

        $collection = $this->stripe->creditNotes->all($params);

        foreach ($collection->autoPagingIterator() as $creditNote) {
            if (! $includeVoided && (bool) ($creditNote->voided ?? false)) {
                $skipped++;

                continue;
            }

            $createdAt = Carbon::createFromTimestampUTC((int) $creditNote->created);
            $quarter = 'Q'.$createdAt->quarter;
            $baseDir = sprintf('stripe/credit-notes/%d/%d-%s', $year, $year, $quarter);

            $creditNotePdfUrl = $creditNote->pdf ?? $creditNote->hosted_credit_note_url;

            if (blank($creditNotePdfUrl)) {
                $skipped++;

                continue;
            }

            $creditNoteNumber = $creditNote->number ?: $creditNote->id;
            $safeCreditNoteNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', $creditNoteNumber) ?: $creditNote->id;
            $fileName = sprintf(
                '%s-%s.pdf',
                $createdAt->format('Y-m-d'),
                $safeCreditNoteNumber
            );
            $path = $baseDir.'/'.$fileName;

            if (Storage::disk($disk)->exists($path)) {
                $skipped++;

                continue;
            }

            try {
                $response = Http::timeout(60)->get($creditNotePdfUrl);

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
