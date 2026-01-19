<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOldZips extends Command
{
    protected $signature = 'zips:clean {--days=30 : Días de antigüedad para eliminar archivos} {--type= : Tipo de archivo (invoices, creditnotes, o all)}';

    protected $description = 'Eliminar archivos ZIP antiguos de facturas y notas de crédito';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $type = $this->option('type') ?? 'all';

        $directories = match ($type) {
            'invoices' => ['invoices-zip' => 'facturas'],
            'creditnotes' => ['credit-notes-zip' => 'notas de crédito'],
            default => [
                'invoices-zip' => 'facturas',
                'credit-notes-zip' => 'notas de crédito',
            ],
        };

        $this->info("🧹 Limpiando archivos ZIP antiguos (>{$days} días)...");
        $this->newLine();

        $totalDeleted = 0;
        $cutoffTime = now()->subDays($days)->timestamp;

        foreach ($directories as $directory => $label) {
            if (! Storage::disk('public')->exists($directory)) {
                $this->line("⏭️  Directorio '{$directory}' no existe, saltando...");
                continue;
            }

            $files = Storage::disk('public')->files($directory);
            $deletedCount = 0;

            $this->info("📂 Procesando {$label}...");

            foreach ($files as $file) {
                $lastModified = Storage::disk('public')->lastModified($file);

                if ($lastModified < $cutoffTime) {
                    $fileName = basename($file);
                    Storage::disk('public')->delete($file);
                    $deletedCount++;
                    $totalDeleted++;
                    $this->line("   ✅ Eliminado: {$fileName}");
                }
            }

            if ($deletedCount === 0) {
                $this->line("   ℹ️  No hay archivos antiguos de {$label}");
            } else {
                $this->info("   ✅ Eliminados {$deletedCount} archivo(s) de {$label}");
            }

            $this->newLine();
        }

        if ($totalDeleted > 0) {
            $this->info("✅ Total eliminados: {$totalDeleted} archivo(s)");
        } else {
            $this->info('ℹ️  No se encontraron archivos antiguos para eliminar.');
        }

        return self::SUCCESS;
    }
}
