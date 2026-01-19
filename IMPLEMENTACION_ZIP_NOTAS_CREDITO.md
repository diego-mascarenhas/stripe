# Implementación: Descarga de Notas de Crédito por Trimestre

## Resumen

Se ha implementado exitosamente la funcionalidad para descargar todas las notas de crédito del trimestre anterior en un archivo ZIP.

## ✅ Lo que se implementó

### 1. **Job para Generación de ZIP en Background**
- **Archivo**: `app/Jobs/GenerateCreditNotesZipJob.php`
- **Funcionalidad**: 
  - Descarga los PDFs de todas las notas de crédito del trimestre anterior
  - Crea un archivo ZIP con todos los PDFs
  - Maneja errores y timeouts individuales
  - Libera memoria periódicamente para evitar problemas con grandes volúmenes
  - Timeout configurado a 10 minutos

### 2. **Interfaz en Filament**
- **Archivo**: `app/Filament/Resources/CreditNoteResource/Pages/ListCreditNotes.php`
- **Botones agregados**:
  - **"Generar ZIP Trimestre Anterior"**: Inicia el proceso de generación
  - **"Descargar ZIP Trimestre Anterior"**: Aparece cuando el archivo está listo
- **Características**:
  - Detección automática del trimestre anterior
  - Modal de confirmación con información del período
  - Funciona tanto en modo asíncrono (con queue worker) como síncrono

### 3. **Comandos de Mantenimiento**

#### `php artisan creditnotes:clean-zips`
- **Archivo**: `app/Console/Commands/CleanOldCreditNotesZips.php`
- **Uso**: Elimina archivos ZIP antiguos
- **Opciones**: `--days=N` (por defecto 30 días)
- **Ejemplo**: `php artisan creditnotes:clean-zips --days=60`

#### `php artisan creditnotes:check-setup`
- **Archivo**: `app/Console/Commands/CheckCreditNotesZipSetup.php`
- **Uso**: Verifica que todo esté configurado correctamente
- **Verifica**:
  - Configuración de colas
  - Tabla de jobs
  - Directorios y permisos
  - Symlink de storage
  - Extensión ZipArchive

### 4. **Documentación**
- **Archivo**: `CREDIT_NOTES_ZIP.md`
- Guía completa de uso y configuración
- Troubleshooting
- Ejemplos de configuración para producción

## 📦 Archivos Creados/Modificados

### Creados:
1. `app/Jobs/GenerateCreditNotesZipJob.php` - Job principal
2. `app/Console/Commands/CleanOldCreditNotesZips.php` - Comando de limpieza
3. `app/Console/Commands/CheckCreditNotesZipSetup.php` - Comando de verificación
4. `CREDIT_NOTES_ZIP.md` - Documentación completa
5. `IMPLEMENTACION_ZIP_NOTAS_CREDITO.md` - Este archivo
6. `storage/app/public/credit-notes-zip/` - Directorio para los ZIPs

### Modificados:
1. `app/Filament/Resources/CreditNoteResource/Pages/ListCreditNotes.php` - Agregados botones y funcionalidad

## 🚀 Cómo Usar

### Opción 1: Con Queue Worker (Recomendado para producción)

1. **Configurar el .env**:
   ```bash
   QUEUE_CONNECTION=database
   ```

2. **Iniciar el queue worker**:
   ```bash
   # Desarrollo
   php artisan queue:work
   
   # Producción (con supervisor)
   # Ver CREDIT_NOTES_ZIP.md para configuración completa
   ```

3. **Usar desde el panel**:
   - Ve a "Notas de Crédito"
   - Clic en "Generar ZIP Trimestre Anterior"
   - Confirma
   - Espera unos minutos y recarga la página
   - Clic en "Descargar ZIP Trimestre Anterior"

### Opción 2: Sin Queue Worker (Modo simple)

1. **El .env ya está configurado así por defecto**:
   ```bash
   QUEUE_CONNECTION=sync
   ```

2. **Usar desde el panel**:
   - Ve a "Notas de Crédito"
   - Clic en "Generar ZIP Trimestre Anterior"
   - Confirma y espera (puede tardar varios minutos)
   - Una vez completado, recarga la página
   - Clic en "Descargar ZIP Trimestre Anterior"

⚠️ **NOTA**: En modo sync, si hay muchas notas de crédito (>100), puede causar timeout del navegador.

## 🔧 Configuración Inicial Realizada

Se ejecutaron los siguientes comandos automáticamente:

```bash
# 1. Crear symlink de storage
php artisan storage:link

# 2. Crear directorio para ZIPs
mkdir -p storage/app/public/credit-notes-zip

# 3. Verificar configuración
php artisan creditnotes:check-setup
```

## 📊 Estado Actual

```
✅ Symlink de storage creado
✅ Directorio de ZIPs creado
✅ Extensión ZipArchive disponible
✅ Tabla de jobs existe
⚠️  Queue en modo "sync" (funcionará pero de forma síncrona)
```

## 🔄 Para Cambiar a Modo Asíncrono (Opcional)

Si deseas usar el modo asíncrono para evitar timeouts:

1. **Edita el archivo `.env`**:
   ```bash
   QUEUE_CONNECTION=database
   ```

2. **Reinicia el servidor** (si usas Herd, solo guarda el archivo)

3. **Inicia el queue worker**:
   ```bash
   php artisan queue:work
   ```

4. **Para producción, configura Supervisor** (ver `CREDIT_NOTES_ZIP.md`)

## 📁 Estructura de Archivos Generados

```
storage/app/public/credit-notes-zip/
├── notas-credito-Q4-2025.zip  (contiene ABC-123.pdf, ABC-124.pdf, etc.)
├── notas-credito-Q1-2026.zip
└── ...
```

Cada ZIP se nombra según el trimestre: `notas-credito-Q{trimestre}-{año}.zip`

## 🧹 Mantenimiento

### Limpiar ZIPs antiguos manualmente:
```bash
php artisan creditnotes:clean-zips --days=30
```

### Programar limpieza automática (opcional):

Edita `app/Console/Kernel.php` y agrega:

```php
protected function schedule(Schedule $schedule): void
{
    // Limpiar ZIPs antiguos cada mes
    $schedule->command('creditnotes:clean-zips --days=30')
        ->monthly()
        ->at('03:00');
}
```

## 🐛 Solución de Problemas

### El botón de descarga no aparece:
1. Verifica que el proceso terminó: revisa los logs
2. Recarga la página (F5)
3. Ejecuta: `php artisan creditnotes:check-setup`

### Timeout del navegador:
1. Cambia `QUEUE_CONNECTION=database` en `.env`
2. Inicia el queue worker: `php artisan queue:work`

### Ver logs:
```bash
tail -f storage/logs/laravel.log
```

### Ver jobs en cola:
```bash
# Jobs pendientes
php artisan queue:monitor

# Jobs fallidos
php artisan queue:failed

# Reintentar jobs fallidos
php artisan queue:retry all
```

## ✨ Características Adicionales

- **Nomenclatura limpia**: Los PDFs dentro del ZIP se nombran con el número de comprobante
- **Manejo de errores**: Si algunos PDFs fallan, el ZIP se genera con los exitosos
- **Logging**: Todos los errores se registran en el log de Laravel
- **Gestión de memoria**: Liberación de memoria cada 10 archivos procesados
- **Sin archivos temporales basura**: Los ZIPs fallidos se eliminan automáticamente

## 📖 Documentación Adicional

Para más detalles, consulta `CREDIT_NOTES_ZIP.md`
