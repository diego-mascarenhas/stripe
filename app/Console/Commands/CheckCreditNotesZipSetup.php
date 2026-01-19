<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckCreditNotesZipSetup extends Command
{
    protected $signature = 'creditnotes:check-setup';

    protected $description = 'Verificar configuración para la generación de ZIPs de notas de crédito';

    public function handle(): int
    {
        $this->info('🔍 Verificando configuración...');
        $this->newLine();

        $allGood = true;

        // 1. Verificar queue connection
        $this->info('1. Queue Connection');
        $queueConnection = config('queue.default');
        $this->line("   Conexión actual: {$queueConnection}");

        if ($queueConnection === 'sync') {
            $this->warn('   ⚠️  NOTA: Queue está en modo "sync" (síncrono)');
            $this->line('   El sistema funcionará pero el navegador esperará hasta que termine');
            $this->line('   Puede causar timeout con muchas notas de crédito (>100)');
            $this->line('   Recomendación: Cambiar QUEUE_CONNECTION a "database" en .env');
        } else {
            $this->info('   ✅ Queue configurado para ejecución en background');
        }

        $this->newLine();

        // 2. Verificar tabla jobs
        $this->info('2. Tabla de Jobs');
        try {
            \DB::table('jobs')->count();
            $this->info('   ✅ Tabla "jobs" existe y es accesible');
        } catch (\Exception $e) {
            $this->error('   ❌ Error: No se puede acceder a la tabla "jobs"');
            $this->error('   Ejecuta: php artisan migrate');
            $allGood = false;
        }

        $this->newLine();

        // 3. Verificar directorio de storage
        $this->info('3. Directorio de Storage');
        $directory = 'credit-notes-zip';

        if (! Storage::disk('public')->exists($directory)) {
            $this->warn("   ⚠️  Directorio '{$directory}' no existe");
            $this->info('   Creando directorio...');
            Storage::disk('public')->makeDirectory($directory);
            $this->info('   ✅ Directorio creado');
        } else {
            $this->info('   ✅ Directorio existe');
        }

        $storagePath = storage_path('app/public/'.$directory);
        $this->line("   Ruta: {$storagePath}");

        if (! is_writable(dirname($storagePath))) {
            $this->error('   ❌ El directorio no tiene permisos de escritura');
            $this->error('   Ejecuta: chmod -R 775 '.storage_path('app/public'));
            $allGood = false;
        } else {
            $this->info('   ✅ Permisos de escritura correctos');
        }

        $this->newLine();

        // 4. Verificar symlink de storage
        $this->info('4. Symlink de Storage Public');
        $publicStoragePath = public_path('storage');

        if (! file_exists($publicStoragePath)) {
            $this->warn('   ⚠️  Symlink no existe');
            $this->info('   Ejecuta: php artisan storage:link');
            $allGood = false;
        } elseif (! is_link($publicStoragePath)) {
            $this->warn('   ⚠️  Existe pero no es un symlink');
            $this->warn('   Ejecuta: rm -rf public/storage && php artisan storage:link');
            $allGood = false;
        } else {
            $this->info('   ✅ Symlink configurado correctamente');
        }

        $this->newLine();

        // 5. Verificar queue worker
        $this->info('5. Queue Worker');
        $this->warn('   ⚠️  No se puede verificar automáticamente si el worker está corriendo');
        $this->line('   Para verificar manualmente:');
        $this->line('   - Producción: sudo supervisorctl status');
        $this->line('   - Desarrollo: ps aux | grep "queue:work"');
        $this->newLine();
        $this->line('   Para iniciar el worker:');
        $this->line('   php artisan queue:work');

        $this->newLine();

        // 6. Verificar extensión ZipArchive
        $this->info('6. Extensión PHP ZipArchive');
        if (class_exists('ZipArchive')) {
            $this->info('   ✅ Extensión ZipArchive disponible');
        } else {
            $this->error('   ❌ Extensión ZipArchive no está instalada');
            $this->error('   Instala: apt-get install php-zip (Debian/Ubuntu)');
            $allGood = false;
        }

        $this->newLine();

        // Resumen final
        if ($allGood) {
            $this->info('✅ Configuración completa. Todo listo para generar ZIPs!');
            $this->newLine();
            $this->info('📝 Próximos pasos:');
            $this->info('   1. Asegúrate de que el queue worker esté corriendo');
            $this->info('   2. Ve al panel de administración > Notas de Crédito');
            $this->info('   3. Haz clic en "Generar ZIP Trimestre Anterior"');
        } else {
            $this->error('❌ Hay problemas de configuración. Revisa los mensajes anteriores.');
        }

        return $allGood ? self::SUCCESS : self::FAILURE;
    }
}
