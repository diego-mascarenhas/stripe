# Guía de Pruebas - ZIP de Notas de Crédito

## Estado Actual
✅ Tienes **34 notas de crédito** disponibles para probar

## Opción 1: Prueba Rápida con 1 Nota de Crédito (RECOMENDADO)

### Paso 1: Ejecutar el comando de prueba

```bash
cd /Users/magoo/Sites/stripe
php artisan creditnotes:test-zip --limit=1
```

Este comando:
- ✅ Busca la nota de crédito más reciente
- ✅ Verifica que tenga URL de PDF
- ✅ Genera un ZIP de prueba
- ✅ Te muestra la ruta para descargarlo

### Paso 2: Verificar el resultado

Después de ejecutar el comando, verás algo como:

```
✅ ZIP generado correctamente!

📦 Archivo: test-notas-credito-20260119-143022.zip
📏 Tamaño: 42.5 KB
📂 Ruta: storage/app/public/credit-notes-zip/test-notas-credito-20260119-143022.zip
🌐 URL:  http://stripe.test/storage/credit-notes-zip/test-notas-credito-20260119-143022.zip
```

### Paso 3: Descargar y verificar

Opción A - Desde el navegador:
```
Abre: http://stripe.test/storage/credit-notes-zip/test-notas-credito-20260119-143022.zip
```

Opción B - Desde Finder:
```
Abre: /Users/magoo/Sites/stripe/storage/app/public/credit-notes-zip/
```

### Paso 4: Extraer y verificar el contenido

1. Descomprime el ZIP
2. Verifica que contiene 1 archivo PDF
3. Abre el PDF para confirmar que es una nota de crédito válida

---

## Opción 2: Prueba con 3 Notas de Crédito

Si la primera prueba funciona, prueba con más notas:

```bash
php artisan creditnotes:test-zip --limit=3
```

---

## Opción 3: Prueba desde el Panel de Administración

### Crear un botón de prueba temporal

1. Ve a: **Notas de Crédito** en el panel
2. Verás los botones ya implementados
3. **NOTA**: Como estás en desarrollo con `QUEUE_CONNECTION=sync`, el botón funcionará pero esperará hasta terminar

### Modificación temporal para probar con menos notas

Si quieres probar el botón del panel pero solo con 1 nota, puedes hacer una modificación temporal:

```bash
# Abrir el archivo en tu editor
open /Users/magoo/Sites/stripe/app/Jobs/GenerateCreditNotesZipJob.php
```

Y en la línea donde dice:

```php
$creditNotes = CreditNote::where('voided', false)
    ->whereBetween('credit_note_created_at', [$start, $end])
    ->orderBy('credit_note_created_at')
    ->get();
```

Cámbialo temporalmente a:

```php
$creditNotes = CreditNote::where('voided', false)
    ->whereBetween('credit_note_created_at', [$start, $end])
    ->orderBy('credit_note_created_at')
    ->limit(1)  // ⚠️ SOLO PARA PRUEBAS - ELIMINAR DESPUÉS
    ->get();
```

**⚠️ IMPORTANTE**: Recuerda eliminar el `->limit(1)` después de probar.

---

## Verificaciones Paso a Paso

### 1. Verificar que el comando existe y está registrado

```bash
php artisan list | grep creditnotes
```

Deberías ver:
```
creditnotes:check-setup     Verificar configuración...
creditnotes:clean-zips      Eliminar archivos ZIP antiguos...
creditnotes:test-zip        Generar un ZIP de prueba...
```

### 2. Ver detalles de las notas de crédito disponibles

```bash
php artisan tinker
```

Luego dentro de tinker:

```php
// Ver las 5 notas más recientes
App\Models\CreditNote::where('voided', false)
    ->orderByDesc('credit_note_created_at')
    ->limit(5)
    ->get(['id', 'number', 'customer_name', 'credit_note_created_at', 'pdf'])
    ->each(function($cn) {
        echo sprintf(
            "%s - %s - %s - PDF: %s\n",
            $cn->number,
            $cn->customer_name,
            $cn->credit_note_created_at?->format('d/m/Y'),
            $cn->pdf ? 'Sí' : 'No'
        );
    });

// Salir
exit
```

### 3. Verificar configuración actual

```bash
php artisan creditnotes:check-setup
```

---

## Escenarios de Prueba

### ✅ Prueba Básica (5 minutos)
1. Ejecutar: `php artisan creditnotes:test-zip --limit=1`
2. Descargar el ZIP generado
3. Verificar que contiene 1 PDF válido

### ✅ Prueba Media (10 minutos)
1. Ejecutar: `php artisan creditnotes:test-zip --limit=5`
2. Verificar tiempos de ejecución
3. Verificar que todos los PDFs están presentes

### ✅ Prueba del Panel (15 minutos)
1. Ir al panel de administración
2. Ir a "Notas de Crédito"
3. Ver los botones implementados
4. (Opcional) Probar generación desde el panel

---

## Qué Verificar en Cada Prueba

- [ ] El comando se ejecuta sin errores
- [ ] Se crea el archivo ZIP
- [ ] El ZIP se puede descargar
- [ ] El ZIP se puede descomprimir
- [ ] Los PDFs dentro están correctos y se pueden abrir
- [ ] Los nombres de archivo son legibles (basados en el número de comprobante)
- [ ] No hay errores en los logs

---

## Ver Logs Durante las Pruebas

En otra terminal, ejecuta:

```bash
tail -f /Users/magoo/Sites/stripe/storage/logs/laravel.log
```

Esto te mostrará cualquier error o mensaje informativo en tiempo real.

---

## Limpiar Archivos de Prueba

Después de probar, puedes limpiar los archivos de prueba:

```bash
# Ver archivos de prueba
ls -lh /Users/magoo/Sites/stripe/storage/app/public/credit-notes-zip/

# Eliminar archivos de prueba (los que empiezan con "test-")
rm /Users/magoo/Sites/stripe/storage/app/public/credit-notes-zip/test-*.zip

# O usar el comando de limpieza
php artisan creditnotes:clean-zips --days=0
```

---

## Problemas Comunes y Soluciones

### ❌ "No se encontraron notas de crédito"

**Solución**: Sincroniza las notas primero:
```bash
php artisan creditnotes:sync
```

### ❌ "Error al descargar PDF"

**Causa**: La URL del PDF puede estar expirada o no ser accesible.

**Verificación**:
```bash
php artisan tinker
```

```php
$cn = App\Models\CreditNote::where('voided', false)->first();
echo $cn->pdf ?? $cn->hosted_credit_note_url ?? 'Sin URL';
```

**Solución**: Resincroniza las notas para obtener URLs actualizadas.

### ❌ "Class ZipArchive not found"

**Solución**:
```bash
# Verificar extensión
php -m | grep zip

# Si no aparece, instalarla (macOS con Herd ya la tiene)
# En Linux/Ubuntu:
# sudo apt-get install php-zip
```

---

## Checklist Final Antes de Producción

- [ ] Prueba con 1 nota ejecutada exitosamente
- [ ] Prueba con 3-5 notas ejecutada exitosamente
- [ ] ZIP descargable y contenido verificado
- [ ] Logs sin errores críticos
- [ ] Archivos de prueba limpiados
- [ ] Documentación revisada
- [ ] (Opcional) Configurar `QUEUE_CONNECTION=database` para producción
- [ ] (Opcional) Configurar Supervisor para el queue worker en producción

---

## Siguiente Paso Recomendado

```bash
php artisan creditnotes:test-zip --limit=1
```

¡Empieza con esto y verás todo el proceso! 🚀
