# Descarga de Facturas por Trimestre

## Descripción

Esta funcionalidad permite generar y descargar un archivo ZIP con todas las facturas (PDFs) del trimestre anterior.

## Características

- **Procesamiento en background**: El ZIP se genera mediante un Job en la cola para evitar timeouts
- **Detección automática del trimestre anterior**: Calcula automáticamente qué trimestre descargar
- **Gestión de archivos**: Los ZIPs se almacenan en `storage/app/public/invoices-zip/`
- **Interfaz visual**: Botones dinámicos que cambian según el estado del archivo
- **Excluye borradores**: Solo incluye facturas en estados finales (paid, open, void, etc.)

## Uso

### 1. Generar ZIP del Trimestre Anterior

1. Ve a la sección **Facturas** en el panel de administración
2. Haz clic en el botón **"Generar ZIP Trimestre Anterior"**
3. Confirma la acción en el modal
4. El sistema iniciará el proceso en background (o síncronamente según configuración)
5. Recibirás una notificación indicando que el proceso ha comenzado

### 2. Descargar el ZIP Generado

1. Una vez que el ZIP esté listo, aparecerá un nuevo botón **"Descargar ZIP Trimestre Anterior"**
2. Haz clic en este botón para descargar el archivo
3. El ZIP contendrá todos los PDFs de las facturas del trimestre, nombrados con su número de comprobante

## Pruebas

### Prueba Rápida con 1 Factura

```bash
cd /Users/magoo/Sites/stripe
php artisan invoices:test-zip --limit=1
```

Este comando:
- ✅ Busca la factura más reciente
- ✅ Verifica que tenga URL de PDF
- ✅ Genera un ZIP de prueba
- ✅ Te muestra la ruta para descargarlo

### Prueba con Múltiples Facturas

```bash
# Probar con 5 facturas
php artisan invoices:test-zip --limit=5

# Probar con 10 facturas
php artisan invoices:test-zip --limit=10
```

## Mantenimiento

### Limpiar Archivos ZIP Antiguos

Se recomienda ejecutar periódicamente el comando de limpieza:

```bash
# Eliminar ZIPs mayores a 30 días (por defecto)
php artisan zips:clean --type=invoices

# Eliminar ZIPs mayores a 60 días
php artisan zips:clean --type=invoices --days=60

# Limpiar tanto facturas como notas de crédito
php artisan zips:clean --days=30
```

### Programar Limpieza Automática

Agrega esto al scheduler en `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Limpiar ZIPs antiguos cada mes
    $schedule->command('zips:clean --days=30')
        ->monthly()
        ->at('03:00');
}
```

## Estructura de Archivos

```
storage/
└── app/
    └── public/
        └── invoices-zip/
            ├── facturas-Q1-2025.zip
            ├── facturas-Q2-2025.zip
            ├── facturas-Q3-2025.zip
            └── facturas-Q4-2025.zip
```

Cada archivo ZIP contiene:
```
facturas-Q1-2025.zip
├── 0005-0001.pdf
├── 0005-0002.pdf
├── 0005-0003.pdf
└── ...
```

## Consideraciones

- **Timeout del Job**: El Job tiene un timeout de 10 minutos (600 segundos)
- **Memoria**: El Job libera memoria cada 10 archivos procesados
- **Reintentos**: El Job solo se intenta una vez. Si falla, revisa los logs
- **Logs**: Los errores se registran en `storage/logs/laravel.log`
- **Borradores excluidos**: Las facturas en estado "draft" no se incluyen
- **Volumen**: Con 445 facturas disponibles, la generación puede tardar varios minutos

## Requisitos Técnicos

### Queue Worker (Opcional pero Recomendado)

Para procesamiento en background:

```bash
# Iniciar el worker (en desarrollo)
php artisan queue:work

# En producción (con Supervisor)
php artisan queue:work --queue=default --tries=3 --timeout=900
```

### Sin Queue Worker

Si `QUEUE_CONNECTION=sync` (configuración actual), el proceso se ejecuta inmediatamente pero puede tardar varios minutos. El navegador esperará hasta que termine.

## Troubleshooting

### El botón de descarga no aparece

- Verifica que el proceso terminó: revisa los logs
- Recarga la página (F5)
- Verifica que el archivo existe: `ls -lh storage/app/public/invoices-zip/`

### Timeout del navegador

- Cambia `QUEUE_CONNECTION=database` en `.env`
- Inicia el queue worker: `php artisan queue:work`

### Ver logs en tiempo real

```bash
tail -f storage/logs/laravel.log
```

### Ver estado de jobs en cola

```bash
# Jobs pendientes
php artisan queue:monitor

# Jobs fallidos
php artisan queue:failed

# Reintentar jobs fallidos
php artisan queue:retry all
```

## Comandos Disponibles

```bash
# Probar con 1 factura
php artisan invoices:test-zip --limit=1

# Probar con 5 facturas
php artisan invoices:test-zip --limit=5

# Limpiar ZIPs antiguos de facturas
php artisan zips:clean --type=invoices --days=30

# Limpiar todos los ZIPs (facturas y notas de crédito)
php artisan zips:clean --days=30

# Verificar configuración del sistema
php artisan creditnotes:check-setup  # También aplica para facturas
```

## Comparación con Notas de Crédito

Esta funcionalidad es idéntica a la de notas de crédito, con las siguientes diferencias:

| Aspecto | Facturas | Notas de Crédito |
|---------|----------|------------------|
| Directorio | `storage/app/public/invoices-zip/` | `storage/app/public/credit-notes-zip/` |
| Nombre archivo | `facturas-Q{n}-{año}.zip` | `notas-credito-Q{n}-{año}.zip` |
| Campo PDF | `invoice_pdf` / `hosted_invoice_url` | `pdf` / `hosted_credit_note_url` |
| Campo fecha | `invoice_created_at` | `credit_note_created_at` |
| Exclusiones | Estado `draft` | Campo `voided = true` |
| Volumen típico | Mayor (~445 facturas) | Menor (~34 notas) |

## Ejemplo de Uso Completo

```bash
# 1. Asegurarse de que el worker está corriendo (opcional)
php artisan queue:work &

# 2. Probar con 1 factura
php artisan invoices:test-zip --limit=1

# 3. Verificar el archivo generado
ls -lh storage/app/public/invoices-zip/

# 4. Abrir en el navegador (si funciona la prueba)
open https://stripe.test/storage/invoices-zip/test-facturas-[timestamp].zip

# 5. Ir al panel de administración y generar el ZIP real del trimestre
# (hacer clic en "Generar ZIP Trimestre Anterior" en la sección Facturas)

# 6. Opcional: Limpiar archivos de prueba
rm storage/app/public/invoices-zip/test-*.zip

# 7. Limpiar archivos antiguos
php artisan zips:clean --type=invoices --days=30
```

## Estado Actual

✅ Funcionalidad implementada y probada
✅ 445 facturas disponibles para descarga
✅ Comando de prueba funcionando correctamente
✅ ZIP de prueba generado exitosamente (30 KB)
✅ Sin errores en logs
