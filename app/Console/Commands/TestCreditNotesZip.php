<?php

namespace App\Console\Commands;

use App\Jobs\GenerateCreditNotesZipJob;
use App\Models\CreditNote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestCreditNotesZip extends Command
{
    protected $signature = 'creditnotes:test-zip {--limit=1 : Número de notas de crédito a incluir} {--force : Forzar regeneración si existe}';

    protected $description = 'Generar un ZIP de prueba con un número limitado de notas de crédito';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $this->info("🧪 Generando ZIP de prueba con {$limit} nota(s) de crédito...");
        $this->newLine();

        // Obtener las últimas notas de crédito
        $creditNotes = CreditNote::where('voided', false)
            ->whereNotNull('credit_note_created_at')
            ->orderByDesc('credit_note_created_at')
            ->limit($limit)
            ->get();

        if ($creditNotes->isEmpty()) {
            $this->error('❌ No se encontraron notas de crédito en la base de datos.');
            $this->newLine();
            $this->info('Verifica que:');
            $this->line('  1. Hay notas de crédito sincronizadas');
            $this->line('  2. Las notas tienen el campo credit_note_created_at');
            $this->line('  3. Las notas no están marcadas como voided');

            return self::FAILURE;
        }

        $this->info("✅ Encontradas {$creditNotes->count()} nota(s) de crédito:");
        $this->newLine();

        // Mostrar detalles de las notas que se van a incluir
        foreach ($creditNotes as $creditNote) {
            $date = $creditNote->credit_note_created_at?->format('d/m/Y') ?? 'Sin fecha';
            $number = $creditNote->number ?? $creditNote->stripe_id;
            $pdfUrl = $creditNote->pdf ?? $creditNote->hosted_credit_note_url ?? 'Sin PDF';
            $hasPdf = $pdfUrl !== 'Sin PDF' ? '✅' : '❌';

            $this->line("  {$hasPdf} {$number} - {$date} - {$creditNote->customer_name}");

            if ($pdfUrl === 'Sin PDF') {
                $this->warn("     ⚠️  Esta nota no tiene URL de PDF, se omitirá");
            }
        }

        $this->newLine();

        // Verificar que al menos una tiene PDF
        $withPdf = $creditNotes->filter(fn ($cn) => filled($cn->pdf ?? $cn->hosted_credit_note_url))->count();

        if ($withPdf === 0) {
            $this->error('❌ Ninguna de las notas de crédito tiene URL de PDF.');

            return self::FAILURE;
        }

        $fileName = 'test-notas-credito-'.now()->format('Ymd-His').'.zip';
        $zipPath = 'credit-notes-zip/'.$fileName;

        // Verificar si ya existe
        if (Storage::disk('public')->exists($zipPath) && ! $force) {
            $this->warn("⚠️  Ya existe un archivo de prueba reciente.");
            if (! $this->confirm('¿Deseas regenerarlo?', false)) {
                $this->info('Operación cancelada.');

                return self::SUCCESS;
            }
        }

        // Confirmar antes de proceder
        if (! $this->confirm('¿Continuar con la generación del ZIP de prueba?', true)) {
            $this->info('Operación cancelada.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('🔄 Generando ZIP...');

        // Obtener rango de fechas de las notas seleccionadas
        $startDate = $creditNotes->min('credit_note_created_at');
        $endDate = $creditNotes->max('credit_note_created_at');

        try {
            $useQueue = config('queue.default') !== 'sync';

            if ($useQueue) {
                $this->info('📤 Despachando job a la cola...');

                GenerateCreditNotesZipJob::dispatch(
                    $startDate->toDateTimeString(),
                    $endDate->toDateTimeString(),
                    0, // quarter test
                    now()->year,
                    $fileName
                )->onQueue('default');

                $this->newLine();
                $this->info('✅ Job despachado correctamente.');
                $this->newLine();
                $this->info('📝 Para ver el progreso:');
                $this->line('   tail -f storage/logs/laravel.log');
                $this->newLine();
                $this->info('📝 Para descargar el archivo cuando esté listo:');
                $this->line('   Ruta: storage/app/public/'.$zipPath);
                $this->line('   URL:  '.url('storage/'.$zipPath));
            } else {
                $this->info('⚙️  Generando ZIP de forma síncrona (esto puede tardar)...');

                $job = new GenerateCreditNotesZipJob(
                    $startDate->toDateTimeString(),
                    $endDate->toDateTimeString(),
                    0,
                    now()->year,
                    $fileName
                );

                $job->handle();

                $fullPath = storage_path('app/public/'.$zipPath);

                if (file_exists($fullPath)) {
                    $sizeKb = round(filesize($fullPath) / 1024, 2);
                    $this->newLine();
                    $this->info('✅ ZIP generado correctamente!');
                    $this->newLine();
                    $this->info("📦 Archivo: {$fileName}");
                    $this->info("📏 Tamaño: {$sizeKb} KB");
                    $this->info("📂 Ruta: storage/app/public/{$zipPath}");
                    $this->info("🌐 URL:  ".url('storage/'.$zipPath));
                    $this->newLine();
                    $this->info('💡 Puedes descargar el archivo desde:');
                    $this->line('   - El navegador usando la URL de arriba');
                    $this->line('   - O directamente desde: '.$fullPath);
                } else {
                    $this->error('❌ El archivo ZIP no se generó correctamente.');
                    $this->error('Revisa los logs: tail -f storage/logs/laravel.log');

                    return self::FAILURE;
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('❌ Error al generar ZIP: '.$exception->getMessage());
            $this->newLine();
            $this->error('Stack trace:');
            $this->line($exception->getTraceAsString());

            return self::FAILURE;
        }
    }
}
