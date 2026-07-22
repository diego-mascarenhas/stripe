<?php

namespace App\Console\Commands;

use App\Actions\CreditNotes\DownloadStripeCreditNotePdfs as DownloadStripeCreditNotePdfsAction;
use Illuminate\Console\Command;

class DownloadStripeCreditNotePdfs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'creditnotes:download-pdfs
                            {year=2026 : Year to download credit notes from}
                            {--include-voided : Include voided credit notes}
                            {--disk=local : Filesystem disk where PDFs are stored}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download Stripe credit note PDFs into storage folders grouped by quarter.';

    /**
     * Execute the console command.
     */
    public function handle(DownloadStripeCreditNotePdfsAction $download): int
    {
        $year = (int) $this->argument('year');
        $disk = (string) $this->option('disk');
        $includeVoided = (bool) $this->option('include-voided');

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please provide a year between 2000 and 2100.');

            return self::FAILURE;
        }

        $this->info("Downloading Stripe credit note PDFs for {$year}...");
        $this->line("Disk: {$disk}");
        $this->line('Include voided: '.($includeVoided ? 'yes' : 'no'));

        try {
            $result = $download->handle($year, $disk, $includeVoided);

            $this->newLine();
            $this->info('Completed.');
            $this->line("Downloaded: {$result['downloaded']}");
            $this->line("Skipped: {$result['skipped']}");
            $this->line("Failed: {$result['failed']}");
            $this->line("Base folder: storage/app/private/stripe/credit-notes/{$year}");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Error downloading Stripe credit note PDFs: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
