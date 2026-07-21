<?php

namespace App\Console\Commands;

use App\Actions\CreditNotes\AssignFiscalCreditNoteNumbers;
use App\Models\CreditNote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AssignFiscalCreditNoteNumbersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'creditnotes:assign-fiscal-numbers
                            {--year= : Only assign numbers for a given year}
                            {--export : Also export a Humano-ready CSV after assigning}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign sequential Spanish fiscal numbers (R-YYYY-NNNN) to Stripe credit notes.';

    /**
     * Execute the console command.
     */
    public function handle(AssignFiscalCreditNoteNumbers $assign): int
    {
        $yearOption = $this->option('year');
        $year = filled($yearOption) ? (int) $yearOption : null;

        $this->info('Assigning fiscal numbers to credit notes...');

        try {
            $result = $assign->handle($year);

            $this->info("Assigned: {$result['assigned']}");
            $this->line("Skipped: {$result['skipped']}");

            if ($this->option('export')) {
                $path = $this->exportHumanoCsv($year);
                $this->info("Humano CSV exported: {$path}");
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Error assigning fiscal numbers: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function exportHumanoCsv(?int $year): string
    {
        $fileName = 'humano/credit-notes-'.($year ?? 'all').'-'.now()->format('Y-m-d-His').'.csv';
        $absolutePath = Storage::disk('local')->path($fileName);

        Storage::disk('local')->makeDirectory('humano');

        $handle = fopen($absolutePath, 'w');

        fputcsv($handle, [
            'Numero Fiscal',
            'Serie',
            'Secuencia',
            'Comprobante Stripe',
            'Stripe ID',
            'Factura Stripe',
            'Fecha',
            'Cliente',
            'Email',
            'ID Fiscal',
            'Pais',
            'Importe',
            'Impuesto',
            'Total',
            'Moneda',
            'Estado',
            'Tipo',
            'Razon',
            'Memo',
            'PDF Stripe',
        ], ';');

        $query = CreditNote::query()
            ->whereNotNull('fiscal_number')
            ->orderBy('fiscal_series')
            ->orderBy('fiscal_sequence');

        if ($year !== null) {
            $query->where('fiscal_series', sprintf('R-%d', $year));
        }

        $query->chunk(200, function ($chunk) use ($handle) {
            foreach ($chunk as $note) {
                fputcsv($handle, [
                    $note->fiscal_number,
                    $note->fiscal_series,
                    $note->fiscal_sequence,
                    $note->number,
                    $note->stripe_id,
                    $note->stripe_invoice_id,
                    $note->credit_note_created_at?->format('d/m/Y'),
                    $note->customer_name ?? $note->customer_description,
                    $note->customer_email,
                    $note->customer_tax_id,
                    $note->customer_address_country,
                    number_format((float) ($note->subtotal ?? 0), 2, ',', ''),
                    number_format((float) ($note->tax ?? 0), 2, ',', ''),
                    number_format((float) ($note->total ?? $note->amount ?? 0), 2, ',', ''),
                    strtoupper($note->currency ?? ''),
                    $note->status,
                    $note->type,
                    $note->reason,
                    $note->memo,
                    $note->pdf ?? $note->hosted_credit_note_url,
                ], ';');
            }
        });

        fclose($handle);

        return $absolutePath;
    }
}
