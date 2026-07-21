<?php

namespace App\Actions\CreditNotes;

use App\Models\CreditNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssignFiscalCreditNoteNumbers
{
    /**
     * Assign sequential Spanish fiscal numbers (R-YYYY-NNNN) to credit notes missing one.
     *
     * @return array{assigned:int, skipped:int}
     */
    public function handle(?int $year = null): array
    {
        $assigned = 0;
        $skipped = 0;

        $query = CreditNote::query()
            ->whereNull('fiscal_number')
            ->orderBy('credit_note_created_at')
            ->orderBy('id');

        if ($year !== null) {
            $query->whereYear('credit_note_created_at', $year);
        }

        $notes = $query->get();

        if ($notes->isEmpty()) {
            return ['assigned' => 0, 'skipped' => 0];
        }

        DB::transaction(function () use ($notes, &$assigned, &$skipped) {
            foreach ($notes as $note) {
                if (filled($note->fiscal_number)) {
                    $skipped++;

                    continue;
                }

                $createdAt = $note->credit_note_created_at instanceof Carbon
                    ? $note->credit_note_created_at
                    : Carbon::parse($note->credit_note_created_at ?? $note->created_at);

                $seriesYear = (int) $createdAt->year;
                $series = sprintf('R-%d', $seriesYear);

                $nextSequence = (int) CreditNote::query()
                    ->where('fiscal_series', $series)
                    ->lockForUpdate()
                    ->max('fiscal_sequence');

                $nextSequence++;

                $fiscalNumber = sprintf('%s-%04d', $series, $nextSequence);

                $note->forceFill([
                    'fiscal_series' => $series,
                    'fiscal_sequence' => $nextSequence,
                    'fiscal_number' => $fiscalNumber,
                ])->save();

                $assigned++;
            }
        });

        return [
            'assigned' => $assigned,
            'skipped' => $skipped,
        ];
    }
}
