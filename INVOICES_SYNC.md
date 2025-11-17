# Sincronización de Facturas de Stripe

## 📋 Descripción

Este sistema sincroniza automáticamente las facturas de Stripe con la base de datos local, permitiendo:
- Consultas rápidas sin llamadas a la API de Stripe
- Historial completo de facturas
- Exportación a CSV para reportes contables
- Cron job diario para mantener los datos actualizados

## 🗄️ Tabla de Base de Datos

La tabla `invoices` almacena:
- Información del cliente (ID, nombre, email)
- Detalles de la factura (número, estado, montos)
- Enlaces a PDFs y páginas de Stripe
- Relación con suscripciones
- Payload completo de Stripe (para auditoría)

## 🔄 Sincronización

### Manual
```bash
php artisan invoices:sync
```

### Automática (Cron)
El sistema ejecuta automáticamente la sincronización diariamente a las **6:30 AM**:

```php
$schedule->command('invoices:sync')->dailyAt('06:30');
```

### Desde el Panel Admin
1. Ir a `/admin/invoices`
2. Hacer clic en "Sincronizar con Stripe"
3. Esperar la notificación de confirmación

## 📊 Visualización

### Listado de Facturas
- **URL**: `/admin/invoices`
- **Funciones**:
  - Ver todas las facturas sincronizadas (últimas 200)
  - Descargar CSV con formato contable
  - Sincronización manual desde el panel

### Detalle de Suscripción
- **URL**: `/admin/subscriptions/{id}`
- **Funciones**:
  - Ver facturas asociadas a la suscripción
  - Descargar PDFs individuales
  - Ver facturas en Stripe

## 🔗 Relaciones

```php
// Obtener facturas de una suscripción
$subscription->invoices()->get();

// Obtener suscripción de una factura
$invoice->subscription;
```

## 📅 Programación de Tareas

El sistema ejecuta 3 comandos diariamente:

```php
06:00 - currency:sync        // Actualiza tipos de cambio
06:15 - subscriptions:sync   // Sincroniza suscripciones
06:30 - invoices:sync        // Sincroniza facturas
```

Para ejecutar el scheduler:
```bash
php artisan schedule:work
```

O configurar el cron en el servidor:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## 🚀 Comandos Útiles

```bash
# Sincronizar todo el sistema
php artisan currency:sync
php artisan subscriptions:sync
php artisan invoices:sync

# Ver el estado del scheduler
php artisan schedule:list

# Limpiar caché
php artisan optimize:clear
```

## 📝 Notas

- Las facturas se sincronizan usando el método `autoPagingIterator()` de Stripe, que maneja automáticamente la paginación
- Se guarda el payload completo de Stripe para auditoría
- Los montos se almacenan en formato decimal (ya convertidos de centavos)
- El sistema maneja errores gracefully y los reporta al log de Laravel

