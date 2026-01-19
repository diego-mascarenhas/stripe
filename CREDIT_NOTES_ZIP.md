# Descarga de Notas de Crédito por Trimestre

## Descripción

Esta funcionalidad permite generar y descargar un archivo ZIP con todas las notas de crédito (PDFs) del trimestre anterior.

## Características

- **Procesamiento en background**: El ZIP se genera mediante un Job en la cola para evitar timeouts
- **Detección automática del trimestre anterior**: Calcula automáticamente qué trimestre descargar
- **Gestión de archivos**: Los ZIPs se almacenan en `storage/app/public/credit-notes-zip/`
- **Interfaz visual**: Botones dinámicos que cambian según el estado del archivo

## Uso

### 1. Generar ZIP del Trimestre Anterior

1. Ve a la sección **Notas de Crédito** en el panel de administración
2. Haz clic en el botón **"Generar ZIP Trimestre Anterior"**
3. Confirma la acción en el modal
4. El sistema iniciará el proceso en background
5. Recibirás una notificación indicando que el proceso ha comenzado

### 2. Descargar el ZIP Generado

1. Una vez que el ZIP esté listo, aparecerá un nuevo botón **"Descargar ZIP Trimestre Anterior"**
2. Haz clic en este botón para descargar el archivo
3. El ZIP contendrá todos los PDFs de las notas de crédito del trimestre, nombrados con su número de comprobante

## Requisitos Técnicos

### Queue Worker

Para que el Job se ejecute, **debe estar corriendo el queue worker**:

```bash
# Iniciar el worker (en desarrollo)
php artisan queue:work

# En producción (con Supervisor o similar)
php artisan queue:work --queue=default --tries=3 --timeout=900
```

### Configuración de Supervisor (Producción)

Crea un archivo `/etc/supervisor/conf.d/stripe-queue-worker.conf`:

```ini
[program:stripe-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /ruta/a/tu/proyecto/artisan queue:work --sleep=3 --tries=3 --timeout=900
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=tu-usuario
numprocs=1
redirect_stderr=true
stdout_logfile=/ruta/a/tu/proyecto/storage/logs/worker.log
stopwaitsecs=3600
```

Luego ejecuta:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start stripe-queue-worker:*
```

### Verificar el Estado del Job

Puedes verificar el estado de los jobs en la base de datos:

```bash
# Ver jobs pendientes
php artisan queue:monitor

# Ver jobs fallidos
php artisan queue:failed

# Reintentar jobs fallidos
php artisan queue:retry all
```

## Mantenimiento

### Limpiar Archivos ZIP Antiguos

Se recomienda ejecutar periódicamente el comando de limpieza para eliminar archivos antiguos:

```bash
# Eliminar ZIPs mayores a 30 días (por defecto)
php artisan creditnotes:clean-zips

# Eliminar ZIPs mayores a 60 días
php artisan creditnotes:clean-zips --days=60
```

Puedes agregar esto al scheduler en `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Limpiar ZIPs antiguos cada mes
    $schedule->command('creditnotes:clean-zips --days=30')
        ->monthly()
        ->at('03:00');
}
```

## Estructura de Archivos

```
storage/
└── app/
    └── public/
        └── credit-notes-zip/
            ├── notas-credito-Q1-2025.zip
            ├── notas-credito-Q2-2025.zip
            ├── notas-credito-Q3-2025.zip
            └── notas-credito-Q4-2025.zip
```

Cada archivo ZIP contiene:
```
notas-credito-Q1-2025.zip
├── ABC-123.pdf
├── ABC-124.pdf
├── ABC-125.pdf
└── ...
```

## Consideraciones

- **Timeout del Job**: El Job tiene un timeout de 10 minutos (600 segundos)
- **Memoria**: El Job libera memoria cada 10 archivos procesados para evitar problemas con grandes volúmenes
- **Reintentos**: El Job solo se intenta una vez. Si falla, revisa los logs
- **Logs**: Los errores se registran en el log de Laravel (`storage/logs/laravel.log`)

## Troubleshooting

### El botón de descarga no aparece

- Verifica que el queue worker esté corriendo
- Revisa los logs para ver si el Job se ejecutó correctamente
- Verifica que el directorio `storage/app/public/credit-notes-zip/` exista y tenga permisos de escritura

### El Job falla

1. Revisa los logs: `tail -f storage/logs/laravel.log`
2. Verifica los jobs fallidos: `php artisan queue:failed`
3. Revisa los detalles del error en la tabla `failed_jobs`

### Timeout en la descarga de PDFs

- El Job descarga cada PDF con un timeout de 60 segundos
- Si algunos PDFs fallan, el ZIP se generará con los que sí se pudieron descargar
- Los errores se registran en el log

### No hay notas de crédito

Si no hay notas de crédito para el trimestre anterior, recibirás una notificación y el Job no se ejecutará.

## Ejemplo de Uso Completo

```bash
# 1. Asegurarse de que el worker está corriendo
php artisan queue:work &

# 2. Ir al panel de administración y generar el ZIP
# (hacer clic en "Generar ZIP Trimestre Anterior")

# 3. Verificar el progreso
php artisan queue:monitor

# 4. Una vez completado, descargar el ZIP desde el panel
# (hacer clic en "Descargar ZIP Trimestre Anterior")

# 5. Opcional: Limpiar archivos antiguos
php artisan creditnotes:clean-zips --days=30
```
