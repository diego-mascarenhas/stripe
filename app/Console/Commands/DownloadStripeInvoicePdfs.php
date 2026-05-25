<?php

namespace App\Console\Commands;

use App\Actions\Invoices\DownloadStripeInvoicePdfs as DownloadStripeInvoicePdfsAction;
use Illuminate\Console\Command;

class DownloadStripeInvoicePdfs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:download-pdfs
                            {year=2026 : Year to download invoices from}
                            {--status=paid : Invoice status filter (paid, open, draft, uncollectible, void, or all)}
                            {--disk=local : Filesystem disk where PDFs are stored}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download Stripe invoice PDFs into storage folders grouped by quarter.';

    /**
     * Execute the console command.
     */
    public function handle(DownloadStripeInvoicePdfsAction $download): int
    {
        $year = (int) $this->argument('year');
        $disk = (string) $this->option('disk');
        $statusOption = (string) $this->option('status');
        $status = $statusOption === 'all' ? null : $statusOption;

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please provide a year between 2000 and 2100.');

            return self::FAILURE;
        }

        $this->info("Downloading Stripe invoice PDFs for {$year}...");
        $this->line("Disk: {$disk}");
        $this->line('Status filter: '.($status ?? 'all'));

        try {
            $result = $download->handle($year, $disk, $status);

            $this->newLine();
            $this->info('Completed.');
            $this->line("Downloaded: {$result['downloaded']}");
            $this->line("Skipped: {$result['skipped']}");
            $this->line("Failed: {$result['failed']}");
            $this->line("Base folder: storage/app/stripe/invoices/{$year}");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Error downloading Stripe invoice PDFs: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
