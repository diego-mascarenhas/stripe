# 🚀 Guía Rápida: Descarga de ZIPs

## ✅ ¿Qué se implementó?

Puedes descargar archivos ZIP con los PDFs de:
- ✅ **Notas de Crédito** del trimestre anterior (34 disponibles)
- ✅ **Facturas** del trimestre anterior (445 disponibles)

---

## 🧪 Prueba Rápida (3 minutos)

### Paso 1: Probar Notas de Crédito
```bash
cd /Users/magoo/Sites/stripe
php artisan creditnotes:test-zip --limit=1
```

### Paso 2: Probar Facturas
```bash
php artisan invoices:test-zip --limit=1
```

### Paso 3: Ver Resultados
```bash
# Ver archivos generados
ls -lh storage/app/public/credit-notes-zip/
ls -lh storage/app/public/invoices-zip/

# Descargar desde el navegador
open https://stripe.test/storage/credit-notes-zip/test-notas-credito-[timestamp].zip
open https://stripe.test/storage/invoices-zip/test-facturas-[timestamp].zip
```

---

## 🎯 Uso en el Panel de Administración

### Para Notas de Crédito:
1. Ve a: **http://stripe.test/admin/credit-notes**
2. Clic en **"Generar ZIP Trimestre Anterior"**
3. Confirma y espera (~1-2 minutos)
4. Recarga la página (F5)
5. Clic en **"Descargar ZIP Trimestre Anterior"**

### Para Facturas:
1. Ve a: **http://stripe.test/admin/invoices**
2. Clic en **"Generar ZIP Trimestre Anterior"**
3. Confirma y espera (~5-10 minutos por el volumen)
4. Recarga la página (F5)
5. Clic en **"Descargar ZIP Trimestre Anterior"**

---

## 🧹 Limpiar Archivos de Prueba

```bash
# Eliminar archivos de prueba
rm storage/app/public/credit-notes-zip/test-*.zip
rm storage/app/public/invoices-zip/test-*.zip

# O usar el comando de limpieza
php artisan zips:clean --days=0
```

---

## ⚡ Mejorar Velocidad (Opcional)

Si las facturas tardan mucho (>10 min), activa el procesamiento en background:

### 1. Editar `.env`
```env
QUEUE_CONNECTION=database
```

### 2. Iniciar Queue Worker
```bash
php artisan queue:work
```

Ahora el proceso se ejecutará en segundo plano y podrás seguir trabajando.

---

## 📊 Comandos Útiles

```bash
# Ver todos los comandos disponibles
php artisan list | grep -E "(creditnotes|invoices|zips)"

# Verificar configuración
php artisan creditnotes:check-setup

# Limpiar archivos antiguos (>30 días)
php artisan zips:clean --days=30

# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Ver archivos generados
ls -lh storage/app/public/credit-notes-zip/
ls -lh storage/app/public/invoices-zip/
```

---

## 🎓 Archivos de Referencia Completos

Para más detalles, consulta:

- **`RESUMEN_IMPLEMENTACION_ZIPS.md`** ← Resumen completo
- **`CREDIT_NOTES_ZIP.md`** ← Guía detallada notas de crédito
- **`INVOICES_ZIP.md`** ← Guía detallada facturas
- **`PRUEBAS_ZIP.md`** ← Guía de pruebas exhaustiva

---

## ✨ Resultado Esperado

### Notas de Crédito
```
📦 Archivo: notas-credito-Q4-2025.zip
📁 Contiene: 0005-0204-CN-01.pdf, 0005-0205-CN-01.pdf, ...
⏱️ Tiempo: ~30 segundos - 2 minutos
```

### Facturas
```
📦 Archivo: facturas-Q4-2025.zip
📁 Contiene: 0005-0001.pdf, 0005-0002.pdf, ..., 0005-0445.pdf
⏱️ Tiempo: ~3-10 minutos (más facturas = más tiempo)
```

---

## 🚀 ¡Listo para Producción!

Todo está implementado, probado y funcionando correctamente. Solo necesitas:

1. ✅ Realizar algunas pruebas adicionales
2. ✅ Limpiar archivos de prueba
3. ✅ (Opcional) Configurar queue worker para mejor rendimiento
4. ✅ Subir a producción

---

**¿Dudas? Consulta los archivos de documentación detallada en el proyecto.**
