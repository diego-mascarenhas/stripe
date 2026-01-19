<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOldCreditNotesZips extends Command
{
    protected $signature = 'creditnotes:clean-zips {--days=30 : Días de antigüedad para eliminar archivos}';

    protected $description = 'Eliminar archivos ZIP antiguos de notas de crédito';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $directory = 'credit-notes-zip';

        if (! Storage::disk('public')->exists($directory)) {
            $this->info('No hay archivos ZIP para limpiar.');

            return self::SUCCESS;
        }

        $files = Storage::disk('public')->files($directory);
        $deletedCount = 0;
        $cutoffTime = now()->subDays($days)->timestamp;

        foreach ($files as $file) {
            $lastModified = Storage::disk('public')->lastModified($file);

            if ($lastModified < $cutoffTime) {
                Storage::disk('public')->delete($file);
                $deletedCount++;
                $this->info("Eliminado: {$file}");
            }
        }

        if ($deletedCount > 0) {
            $this->info("Se eliminaron {$deletedCount} archivos ZIP antiguos.");
        } else {
            $this->info('No se encontraron archivos ZIP antiguos para eliminar.');
        }

        return self::SUCCESS;
    }
}
