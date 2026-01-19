<?php

namespace App\Jobs;

use App\Models\CreditNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GenerateCreditNotesZipJob implements ShouldQueue
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

            // Obtener notas de crédito del trimestre
            $creditNotes = CreditNote::where('voided', false)
                ->whereBetween('credit_note_created_at', [$start, $end])
                ->orderBy('credit_note_created_at')
                ->get();

            if ($creditNotes->isEmpty()) {
                Log::info("No se encontraron notas de crédito para Q{$this->quarter}/{$this->year}");

                return;
            }

            // Crear directorio si no existe
            $zipDirectory = 'credit-notes-zip';
            Storage::disk('public')->makeDirectory($zipDirectory);

            $zipPath = storage_path('app/public/'.$zipDirectory.'/'.$this->fileName);

            // Crear archivo ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('No se pudo crear el archivo ZIP');
            }

            $downloadedCount = 0;
            $errors = [];

            foreach ($creditNotes as $creditNote) {
                $pdfUrl = $creditNote->pdf ?? $creditNote->hosted_credit_note_url;

                if (! $pdfUrl) {
                    $errors[] = "Nota de crédito {$creditNote->number}: Sin URL de PDF";
                    continue;
                }

                try {
                    // Descargar PDF con timeout largo
                    $response = Http::timeout(60)->get($pdfUrl);

                    if ($response->successful()) {
                        $pdfContent = $response->body();
                        $sanitizedNumber = preg_replace('/[^A-Za-z0-9\-]/', '_', $creditNote->number ?? $creditNote->stripe_id);
                        $pdfFileName = "{$sanitizedNumber}.pdf";

                        // Añadir al ZIP
                        $zip->addFromString($pdfFileName, $pdfContent);
                        $downloadedCount++;
                    } else {
                        $errors[] = "Nota de crédito {$creditNote->number}: Error HTTP {$response->status()}";
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Nota de crédito {$creditNote->number}: {$e->getMessage()}";
                }

                // Liberar memoria cada 10 archivos
                if ($downloadedCount % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            $zip->close();

            // Log del resultado
            $logMessage = "ZIP generado: {$downloadedCount} de {$creditNotes->count()} notas de crédito descargadas.";
            if (! empty($errors)) {
                $logMessage .= ' Errores: '.count($errors);
            }
            Log::info($logMessage);

            if ($downloadedCount === 0) {
                Storage::disk('public')->delete($zipDirectory.'/'.$this->fileName);
            }
        } catch (\Throwable $exception) {
            Log::error('Error generando ZIP de notas de crédito: '.$exception->getMessage());
            throw $exception;
        }
    }
}
