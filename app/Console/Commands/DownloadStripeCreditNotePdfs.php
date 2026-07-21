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
                            {--status=issued : Credit note status filter (issued, void, or all)}
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
        $statusOption = (string) $this->option('status');
        $status = $statusOption === 'all' ? null : $statusOption;

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please provide a year between 2000 and 2100.');

            return self::FAILURE;
        }

        $this->info("Downloading Stripe credit note PDFs for {$year}...");
        $this->line("Disk: {$disk}");
        $this->line('Status filter: '.($status ?? 'all'));

        try {
            $result = $download->handle($year, $disk, $status);

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
