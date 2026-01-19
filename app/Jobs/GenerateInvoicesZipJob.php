<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GenerateInvoicesZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutos

    public $tries = 1;

    public function __construct(
        public string $startDate,
        public string $endDate,
        public int $quarter,
        public int $year,
        public string $fileName
    ) {
    }

    public function handle(): void
    {
        try {
            $start = \Carbon\Carbon::parse($this->startDate);
            $end = \Carbon\Carbon::parse($this->endDate);

            // Obtener facturas del trimestre (excluyendo borradores)
            $invoices = Invoice::where('status', '!=', 'draft')
                ->whereBetween('invoice_created_at', [$start, $end])
                ->orderBy('invoice_created_at')
                ->get();

            if ($invoices->isEmpty()) {
                Log::info("No se encontraron facturas para Q{$this->quarter}/{$this->year}");

                return;
            }

            // Crear directorio si no existe
            $zipDirectory = 'invoices-zip';
            Storage::disk('public')->makeDirectory($zipDirectory);

            $zipPath = storage_path('app/public/'.$zipDirectory.'/'.$this->fileName);

            // Crear archivo ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('No se pudo crear el archivo ZIP');
            }

            $downloadedCount = 0;
            $errors = [];

            foreach ($invoices as $invoice) {
                $pdfUrl = $invoice->invoice_pdf ?? $invoice->hosted_invoice_url;

                if (! $pdfUrl) {
                    $errors[] = "Factura {$invoice->number}: Sin URL de PDF";
                    continue;
                }

                try {
                    // Descargar PDF con timeout largo
                    $response = Http::timeout(60)->get($pdfUrl);

                    if ($response->successful()) {
                        $pdfContent = $response->body();
                        $sanitizedNumber = preg_replace('/[^A-Za-z0-9\-]/', '_', $invoice->number ?? $invoice->stripe_id);
                        $pdfFileName = "{$sanitizedNumber}.pdf";

                        // Añadir al ZIP
                        $zip->addFromString($pdfFileName, $pdfContent);
                        $downloadedCount++;
                    } else {
                        $errors[] = "Factura {$invoice->number}: Error HTTP {$response->status()}";
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Factura {$invoice->number}: {$e->getMessage()}";
                }

                // Liberar memoria cada 10 archivos
                if ($downloadedCount % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            $zip->close();

            // Log del resultado
            $logMessage = "ZIP generado: {$downloadedCount} de {$invoices->count()} facturas descargadas.";
            if (! empty($errors)) {
                $logMessage .= ' Errores: '.count($errors);
            }
            Log::info($logMessage);

            if ($downloadedCount === 0) {
                Storage::disk('public')->delete($zipDirectory.'/'.$this->fileName);
            }
        } catch (\Throwable $exception) {
            Log::error('Error generando ZIP de facturas: '.$exception->getMessage());
            throw $exception;
        }
    }
}
