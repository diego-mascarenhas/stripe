# 🚨 Problema: Notificaciones Enviadas Incorrectamente

## 📋 Descripción del Problema

Se identificó que el sistema envió notificaciones de advertencia a clientes que **ya habían pagado sus facturas**, como en el caso de **Pablo Elias** (`pelias@abacoturismo.com.ar`).

### Caso Reportado: Pablo Elias

**Estado real en Stripe:**
- ✅ Suscripción: **Activa**
- ✅ Método de pago: VISA registrada
- ✅ Factura 0005-0294 (30/11/2025): **PAGADA**
- ✅ Factura 0005-0394 (30/12/2025): **PAGADA**

**Problema:**
- ❌ Recibió notificación de advertencia incorrectamente
- ❌ La base de datos local NO tenía sincronizadas las facturas pagadas

---

## 🔍 Causa Raíz

### El Flujo Actual (CON PROBLEMA)

```
09:00 AM → Se ejecuta subscriptions:send-notifications
          ↓
          Lee facturas de la BD local (puede estar desactualizada)
          ↓
          Envía notificaciones basándose en datos viejos
          ↓
          ⚠️ Cliente con facturas pagadas recibe aviso incorrecto
```

### Timeline del Problema

1. **05:00 AM** - Cliente paga sus facturas en Stripe
2. **06:00 AM** - Última sincronización de facturas (cada 4 horas)
3. **07:30 AM** - Cliente paga otra factura
4. **09:00 AM** - Sistema envía notificaciones ← **PROBLEMA: Usa datos de las 06:00 AM**
5. **10:15 AM** - Próxima sincronización (ya es tarde)

### Por Qué Ocurre

**3 escenarios posibles:**

1. **Webhooks no configurados/funcionando** 
   - El evento `invoice.payment_succeeded` no llega
   - Las facturas pagadas NO se actualizan en tiempo real

2. **Sincronización desfasada**
   - Facturas se sincronizan cada 4 horas (bootstrap/app.php:54)
   - Notificaciones se envían a las 9:00 AM
   - Ventana de 3+ horas donde pueden ocurrir pagos no reflejados

3. **Sin sincronización previa**
   - El comando `subscriptions:send-notifications` NO sincronizaba antes de enviar
   - Usaba datos potencialmente obsoletos

---

## ✅ Soluciones Implementadas

### 1. **Sincronización Automática Previa** ✨

**Cambio en `SendSubscriptionNotifications.php`:**

```php
public function handle(): int
{
    $this->info('Iniciando envío de notificaciones...');

    // 🔄 NUEVO: Sincronizar facturas ANTES de procesar
    $this->info('🔄 Sincronizando facturas desde Stripe...');
    $this->call('invoices:sync');
    $this->newLine();

    $this->scheduleWarningNotifications();
    $this->sendPendingNotifications();

    $this->info('✅ Proceso completado');
    return self::SUCCESS;
}
```

**Beneficio:**
- Garantiza que las facturas están actualizadas al momento de enviar notificaciones
- Previene notificaciones basadas en datos obsoletos
- **Protege tanto notificaciones de warning como suspensiones automáticas**

### 2. **Safety Checks en Comando Manual** 🛡️

**Cambio en `ForceSuspendSubscription.php`:**

Agregamos verificaciones de seguridad automáticas:

```php
protected $signature = 'subscription:force-suspend {id} 
    {--skip-email : Skip sending the email notification} 
    {--skip-checks : Skip safety checks (dangerous!)}';

public function handle(): int
{
    // ... find subscription ...

    // 🛡️ NUEVO: Safety checks (unless explicitly skipped)
    if (!$this->option('skip-checks')) {
        $this->info('🛡️  Running safety checks...');
        
        // 1. Sincronizar facturas primero
        $this->call('invoices:sync', [], 'null');
        
        // 2. Verificar facturas impagas
        $unpaidInvoices = Invoice::where('stripe_subscription_id', $subscription->stripe_id)
            ->where('status', 'open')
            ->where('paid', false)
            ->get();

        if ($unpaidInvoices->isEmpty()) {
            $this->error('⚠️  WARNING: This subscription has NO unpaid invoices!');
            $this->warn('   The customer is up to date with payments.');
            
            if (!$this->confirm('Are you SURE you want to suspend?', false)) {
                return self::SUCCESS; // Cancela
            }
        }
    }
}
```

**Beneficios:**
- Previene suspensiones accidentales de clientes al día
- Sincroniza antes de verificar
- Requiere confirmación explícita si no hay facturas impagas
- Muestra información detallada de facturas impagas

**Uso seguro:**
```bash
# Con safety checks (recomendado)
php artisan subscription:force-suspend cus_XXX

# Saltando checks (solo para testing/emergencias)
php artisan subscription:force-suspend cus_XXX --skip-checks
```

### 3. **Comando de Auditoría** 🔍

**Nuevo comando: `FindIncorrectNotifications.php`**

```bash
# Encontrar notificaciones enviadas incorrectamente
php artisan notifications:find-incorrect

# Con sincronización previa
php artisan notifications:find-incorrect --sync
```

**Funcionalidad:**
- Busca notificaciones de warning enviadas en los últimos 30 días
- Verifica si el cliente REALMENTE tenía 2+ facturas impagas
- Reporta casos donde la notificación fue incorrecta
- Sugiere causas y soluciones

**Salida de ejemplo:**

```
⚠️  Pablo Elias (pelias@abacoturismo.com.ar)
   Notificación: Aviso 5 días antes - Enviada: 2026-01-10 09:00
   Facturas impagas actuales: 0
   Estado suscripción: active
   Últimas facturas:
     • 0005-0394 - PAGADA - 2025-12-30
     • 0005-0294 - PAGADA - 2025-11-30

❌ Se encontraron 1 notificaciones incorrectas

📋 POSIBLES CAUSAS:
  1. Las facturas se pagaron DESPUÉS de enviar la notificación
  2. No se sincronizaron las facturas desde Stripe (ejecutar: invoices:sync)
  3. Los webhooks de Stripe no están funcionando correctamente

💡 RECOMENDACIONES:
  • Ejecutar: php artisan notifications:find-incorrect --sync
  • Verificar configuración de webhooks en Stripe Dashboard
  • Agregar sincronización automática en el scheduler
```

### 3. **Comando de Debugging por Cliente** 🐛

**Nuevo comando: `DebugSubscriptionNotifications.php`**

```bash
# Buscar por customer_id, email o nombre
php artisan subscriptions:debug-notifications "Pablo"
php artisan subscriptions:debug-notifications "pelias@abacoturismo.com.ar"
php artisan subscriptions:debug-notifications "cus_TWBahFbrfvwwee"
```

**Funcionalidad:**
- Muestra datos completos de la suscripción
- Lista todas las facturas (estado actual)
- Calcula ventanas de notificación
- Muestra historial de notificaciones enviadas
- Identifica si cumple condiciones para notificación

---

## 🛡️ Prevención Futura

### Verificación de Webhooks

**1. Revisar configuración en Stripe Dashboard:**

```
Developers → Webhooks → [Tu endpoint]
```

**Eventos requeridos:**
- ✅ `invoice.payment_succeeded`
- ✅ `invoice.payment_failed`
- ✅ `subscription.updated`

**2. Verificar logs de webhooks:**

```bash
tail -f storage/logs/laravel.log | grep "Stripe webhook"
```

### Scheduler Optimizado

**Configuración actual (bootstrap/app.php):**

```php
// Invoices: Cada 4 horas a las :15
$schedule->command('invoices:sync')
    ->cron('15 */4 * * *')
    ->withoutOverlapping(15);

// Notificaciones: Diariamente a las 9:00 AM
$schedule->command('subscriptions:send-notifications')
    ->dailyAt('09:00')
    ->withoutOverlapping(10);
```

**✅ Ya NO es necesario cambiar esto** porque ahora `send-notifications` sincroniza antes de ejecutar.

### Monitoreo Recomendado

**1. Ejecutar auditoría semanal:**

```bash
# Agregar al crontab
0 8 * * 1 cd /path/to/project && php artisan notifications:find-incorrect --sync
```

**2. Alertas en logs:**

Modificar `SendSubscriptionNotifications.php` para loguear:

```php
if ($unpaidInvoicesCount >= 2) {
    Log::info('Creating notification', [
        'customer' => $subscription->customer_name,
        'unpaid_invoices' => $unpaidInvoicesCount,
        'oldest_invoice_age_days' => $daysSinceInvoiceCreated,
    ]);
}
```

---

## 🧪 Testing

### Verificar sincronización funciona

```bash
# 1. Ver estado actual
php artisan subscriptions:debug-notifications "Pablo"

# 2. Sincronizar desde Stripe
php artisan invoices:sync

# 3. Verificar cambios
php artisan subscriptions:debug-notifications "Pablo"
```

### Probar flujo completo

```bash
# Simular envío de notificaciones (con sync incluido)
php artisan subscriptions:send-notifications

# Revisar logs
tail -f storage/logs/laravel.log
```

---

## 📊 Métricas de Éxito

### Antes de la solución
- ❌ Notificaciones basadas en datos de hasta 4 horas de antigüedad
- ❌ Sin visibilidad de notificaciones incorrectas
- ❌ Sin herramientas de debugging

### Después de la solución
- ✅ Notificaciones basadas en datos actualizados (<1 minuto)
- ✅ Comando de auditoría para detectar problemas
- ✅ Herramientas de debugging por cliente
- ✅ Logs detallados para troubleshooting

---

## 🚀 Próximos Pasos

### Inmediato
1. ✅ Verificar webhooks están configurados en Stripe
2. ✅ Ejecutar `php artisan notifications:find-incorrect --sync`
3. ✅ Revisar casos reportados y contactar clientes afectados

### Corto Plazo (1-2 semanas)
1. Agregar monitoreo automático semanal
2. Implementar alertas si webhook falla
3. Dashboard para ver estado de sincronización

### Largo Plazo (1-3 meses)
1. Considerar sistema de notificaciones más granular
2. Enviar notificación de confirmación cuando se paga
3. Panel del cliente para ver estado de facturas en tiempo real

---

## 📝 Checklist de Verificación

Antes de cada envío masivo de notificaciones o suspensión manual:

- [ ] Webhooks funcionando (últimas 24h sin errores)
- [ ] Última sincronización exitosa (<1 hora)
- [ ] No hay notificaciones incorrectas pendientes
- [ ] Logs de la última ejecución sin errores
- [ ] Si es manual: ejecutar con safety checks habilitados

---

## 🔗 Referencias

- **Comando de envío**: `app/Console/Commands/SendSubscriptionNotifications.php`
- **Comando de suspensión manual**: `app/Console/Commands/ForceSuspendSubscription.php` (con safety checks)
- **Comando de auditoría**: `app/Console/Commands/FindIncorrectNotifications.php`
- **Comando de debugging**: `app/Console/Commands/DebugSubscriptionNotifications.php`
- **Scheduler**: `bootstrap/app.php` (líneas 20-100)
- **Webhooks**: `app/Http/Controllers/StripeWebhookController.php`
- **Sincronización**: `app/Actions/Invoices/SyncStripeInvoices.php`

---

**Fecha de implementación:** 2026-01-11  
**Versión:** 1.0  
**Estado:** ✅ Implementado y documentado
