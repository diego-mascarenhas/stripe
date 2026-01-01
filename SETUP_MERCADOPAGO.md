# 🚀 Guía de Configuración Rápida - MercadoPago

Esta es una guía paso a paso para configurar la sincronización de pagos de MercadoPago.

## ✅ Paso 1: Obtener Credenciales de MercadoPago

### Para Desarrollo/Pruebas

1. Ve a [https://www.mercadopago.com.ar/developers/panel](https://www.mercadopago.com.ar/developers/panel)
2. Inicia sesión con tu cuenta
3. Crea una nueva aplicación o selecciona una existente
4. Ve a **"Credenciales de prueba"**
5. Copia el **"Access Token"** (comienza con `TEST-`)

### Para Producción

1. En el mismo panel de desarrolladores
2. Ve a **"Credenciales de producción"**
3. Copia el **"Access Token"** (comienza con `APP_USR-`)

## ✅ Paso 2: Configurar Variables de Entorno

Abre tu archivo `.env` y agrega:

```env
# Para pruebas:
MERCADOPAGO_ACCESS_TOKEN=TEST-1234567890-123456-abc123def456-789012345

# O para producción:
MERCADOPAGO_ACCESS_TOKEN=APP_USR-1234567890-123456-abc123def456-789012345

# Opcional:
MERCADOPAGO_PUBLIC_KEY=tu_public_key_aqui
```

**⚠️ IMPORTANTE:** Reemplaza el valor de ejemplo con tu Access Token real.

## ✅ Paso 3: Ejecutar Migraciones

```bash
php artisan migrate
```

Esto creará la tabla `payments` en tu base de datos.

## ✅ Paso 4: Verificar Credenciales

**¡MUY IMPORTANTE!** Antes de sincronizar, verifica que todo funcione:

```bash
php artisan mercadopago:test-credentials
```

### ¿Qué deberías ver?

Si todo está bien:
```
✅ ¡Credenciales válidas!
Se encontraron pagos en tu cuenta:
  • Total de pagos consultados: X
  • Último pago ID: 123456789
  ...
```

Si hay un problema:
```
❌ Error al conectar con MercadoPago
Mensaje: [detalles del error]
```

## ✅ Paso 5: Sincronizar Pagos

Una vez que las credenciales estén verificadas:

```bash
php artisan payments:sync-mercadopago
```

Esto sincronizará los pagos de los últimos 30 días.

### Opciones adicionales:

```bash
# Sincronizar últimos 7 días
php artisan payments:sync-mercadopago --days=7

# Sincronizar rango específico
php artisan payments:sync-mercadopago \
  --begin-date="2025-11-01T00:00:00Z" \
  --end-date="2025-12-01T00:00:00Z"
```

## ✅ Paso 6: Ver Pagos en el Panel

1. Accede a tu panel de Filament
2. Ve a **Facturación** en el menú
3. Haz clic en **Pagos MP**
4. Verás todos los pagos sincronizados

### Botón de Sincronización Manual

Desde el panel también puedes:
- Hacer clic en el botón **"Sincronizar"**
- Confirmar la acción
- Esperar a que se complete

## 🔧 Solución de Problemas Comunes

### "No se encontró MERCADOPAGO_ACCESS_TOKEN"

- Verifica que agregaste la variable al `.env`
- Asegúrate de que no tenga espacios extra
- Reinicia el servidor después de editar `.env`

### "La API respondió pero no devolvió pagos"

- Si usas credenciales TEST, necesitas crear pagos de prueba primero
- Si usas credenciales de producción, verifica que tengas pagos en tu cuenta
- Ajusta el rango de fechas con `--days=90` para buscar más atrás

### "Error 401 Unauthorized"

- El Access Token es inválido o expiró
- Copia nuevamente el token desde el panel de MercadoPago
- Asegúrate de copiar el token completo

### "Error 429 Rate Limiting"

- MercadoPago está limitando las peticiones
- Espera 1-2 minutos antes de volver a intentar
- Reduce la frecuencia de sincronización

## 📚 Documentación Completa

Para más detalles, consulta:
- [MERCADOPAGO_PAYMENTS_SYNC.md](MERCADOPAGO_PAYMENTS_SYNC.md) - Documentación completa

## 🎯 Automatización (Opcional)

Para sincronizar automáticamente cada día, agrega a `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('payments:sync-mercadopago --days=7')
        ->daily();
}
```

Y asegúrate de que el cron esté configurado:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## ✨ ¡Listo!

Ya tienes todo configurado. Los pagos de MercadoPago se sincronizarán automáticamente y podrás verlos en tu panel de administración.

### Archivos Creados

- ✅ `database/migrations/*_create_payments_table.php`
- ✅ `app/Models/Payment.php`
- ✅ `app/Services/MercadoPago/MercadoPagoService.php`
- ✅ `app/Actions/Payments/SyncMercadoPagoPayments.php`
- ✅ `app/Console/Commands/SyncMercadoPagoPayments.php`
- ✅ `app/Console/Commands/TestMercadoPagoCredentials.php`
- ✅ `app/Filament/Resources/PaymentResource.php`
- ✅ `app/Filament/Resources/PaymentResource/Pages/ListPayments.php`

### Comandos Disponibles

- `php artisan mercadopago:test-credentials` - Verificar credenciales
- `php artisan payments:sync-mercadopago` - Sincronizar pagos
- `php artisan migrate` - Crear tabla payments

---

¿Necesitas ayuda? Revisa los logs en `storage/logs/laravel.log`

