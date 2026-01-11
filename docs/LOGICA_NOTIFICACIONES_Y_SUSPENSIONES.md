# 📋 Lógica de Notificaciones y Suspensiones

## ⚙️ Resumen de la Lógica

### 🎯 Principio General

**TODO se basa ÚNICAMENTE en los días transcurridos de la factura más antigua impaga.**

La cantidad de facturas impagas **NO importa** para nada.

### 📅 Timeline Único

| Días desde creación | Acción | Descripción |
|---------------------|--------|-------------|
| 0 | Factura generada | Stripe crea la factura |
| 10 | Factura vence | Plazo de pago finaliza |
| 40-42 | **🔔 Aviso 1** | "Faltan 5 días para suspender" |
| 43-44 | **🔔 Aviso 2** | "Faltan 2 días para suspender" |
| 45+ | **🚫 Suspensión** | Servicio suspendido automáticamente |

**Condiciones adicionales solo para suspensión:**
- ✅ `auto_suspend = true` en metadata de la suscripción
- ✅ `status = 'active'` (no suspende si ya está pausada/cancelada)

---

## 📊 Ejemplos Prácticos

### Ejemplo 1: Cliente con 1 factura impaga (35 días)

```
Cliente: Juan Pérez
Facturas impagas: 1
Factura más antigua: 35 días
auto_suspend: true

Resultado:
❌ NO recibe notificaciones (35 < 40 días)
❌ NO se suspende (35 < 45 días)
```

### Ejemplo 2: Cliente con 1 factura impaga (41 días)

```
Cliente: María López
Facturas impagas: 1
Factura más antigua: 41 días

Resultado:
✅ SÍ recibe "Aviso 5 días" (40 ≤ 41 < 43)
❌ NO se suspende aún (41 < 45 días)
```

### Ejemplo 3: Cliente con 1 factura impaga (50 días)

```
Cliente: Carlos Rodríguez
Facturas impagas: 1
Factura más antigua: 50 días
auto_suspend: true

Resultado:
✅ Ya recibió ambas notificaciones (días 40 y 43)
✅ SÍ se suspende (50 ≥ 45 días)
```

### Ejemplo 4: Cliente con 3 facturas impagas (46 días la más antigua)

```
Cliente: Ana Martínez
Facturas impagas: 3 (46, 16, 5 días)
Factura más antigua: 46 días
auto_suspend: true

Resultado:
✅ Ya recibió ambas notificaciones (días 40 y 43)
✅ SÍ se suspende (46 ≥ 45 días)

NOTA: Las otras 2 facturas NO importan, solo la más antigua.
```

### Ejemplo 5: Cliente con 5 facturas impagas (30 días la más antigua)

```
Cliente: Pedro Gómez
Facturas impagas: 5 (30, 20, 15, 10, 5 días)
Factura más antigua: 30 días

Resultado:
❌ NO recibe notificaciones (30 < 40 días)
❌ NO se suspende (30 < 45 días)

NOTA: Aunque tiene 5 facturas, ninguna llega a 40 días.
```

---

## 🔍 Comando de Debugging

Para verificar el estado de un cliente específico:

```bash
php artisan subscriptions:debug-notifications "nombre@email.com"
```

**Salida de ejemplo:**

```
═══════════════════════════════════════════════════════
ANÁLISIS DE NOTIFICACIONES - Juan Pérez
═══════════════════════════════════════════════════════

📋 DATOS DE LA SUSCRIPCIÓN
ID: 123
Status: active

💰 FACTURAS IMPAGAS (1)
  • 0005-0100
    Creada: 2025-11-01 10:00:00
    Días desde creación: 71 días
    Monto: 10.000,00 ARS

⚠️  Tiene 1 factura impaga (no cumple condición para notificaciones)
  Las notificaciones de warning requieren 2+ facturas impagas

⚙️  SUSPENSIÓN AUTOMÁTICA:
Auto-suspend habilitado: SÍ
  • ACTIVA: Suspensión automática (45+ días) ← ESTÁ AQUÍ
  • ⚠️  Este servicio DEBERÍA estar suspendido
```

---

## 🛡️ Safety Checks en Comando Manual

Al usar `subscription:force-suspend`, el sistema verifica:

### Con 0 facturas impagas:
```bash
$ php artisan subscription:force-suspend cus_XXX

⚠️  WARNING: This subscription has NO unpaid invoices!
   The customer is up to date with payments.

Are you SURE you want to suspend? (yes/no) [no]:
```

### Con 1 factura impaga (menos de 45 días):
```bash
$ php artisan subscription:force-suspend cus_XXX

Unpaid invoices: 1
Oldest unpaid invoice: 0005-0100
Created: 2026-01-01 (30 days ago)
⚠️  Does NOT meet automatic suspension criteria yet (30/45 days)

Do you want to proceed with suspension? (yes/no) [yes]:
```

### Con 1 factura impaga (45+ días):
```bash
$ php artisan subscription:force-suspend cus_XXX

Unpaid invoices: 1
Oldest unpaid invoice: 0005-0100
Created: 2025-11-15 (57 days ago)
✅ Meets automatic suspension criteria (45+ days)

Do you want to proceed with suspension? (yes/no) [yes]:
```

---

## 📝 Resumen Visual

```
┌─────────────────────────────────────────────────────────────┐
│              TIMELINE SIMPLIFICADO                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Día 0  ──► Factura creada                                  │
│ Día 10 ──► Factura vence                                   │
│            │                                                │
│            │ [Evaluación basada SOLO en días]              │
│            ↓                                                │
│ Día 40 ──► 📧 Aviso "Faltan 5 días"                       │
│ Día 43 ──► 📧 Aviso "Faltan 2 días"                       │
│ Día 45 ──► 🚫 SUSPENSIÓN (si auto_suspend = true)         │
│                                                             │
│ ⚠️  LA CANTIDAD DE FACTURAS NO IMPORTA                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## 🎯 Casos de Uso Simplificados

| Días Factura Antigua | ¿Notificación Día 40? | ¿Notificación Día 43? | ¿Suspensión? |
|----------------------|-----------------------|-----------------------|--------------|
| 30 días | ❌ | ❌ | ❌ |
| 41 días | ✅ | ❌ | ❌ |
| 43 días | ✅ (ya pasó) | ✅ | ❌ |
| 46 días | ✅ (ya pasó) | ✅ (ya pasó) | ✅ |
| 60 días | ✅ (ya pasó) | ✅ (ya pasó) | ✅ |

**NOTA:** La cantidad de facturas (1, 2, 5, 10...) NO afecta NINGUNA decisión.

---

## 🔧 Código Relevante

### Lógica completa (simplificada)

```php
// Si no tiene facturas, skip
if ($unpaidInvoicesCount === 0) {
    continue;
}

// Obtener factura más antigua
$oldestUnpaidInvoice = Invoice::where('stripe_subscription_id', $subscription->stripe_id)
    ->where('status', 'open')
    ->where('paid', false)
    ->orderBy('invoice_created_at', 'asc')
    ->first();

// Calcular días
$daysSinceInvoiceCreated = $oldestUnpaidInvoice->invoice_created_at->diffInDays(now());

// ═══════════════════════════════════════════════
// NOTIFICACIONES
// ═══════════════════════════════════════════════
if ($daysSinceInvoiceCreated >= 40 && $daysSinceInvoiceCreated < 43) {
    // Enviar "Aviso 5 días"
}

if ($daysSinceInvoiceCreated >= 43 && $daysSinceInvoiceCreated < 45) {
    // Enviar "Aviso 2 días"
}

// ═══════════════════════════════════════════════
// SUSPENSIÓN
// ═══════════════════════════════════════════════
if ($daysSinceInvoiceCreated >= 45) {
    $autoSuspend = data_get($subscription->data, 'auto_suspend', false);
    
    if ($autoSuspend && $subscription->status === 'active') {
        $this->suspendSubscription($subscription);
    }
}
```

**Clave:** Todo gira alrededor de `$daysSinceInvoiceCreated`. La cantidad de facturas no se evalúa nunca.

---

**Última actualización:** 2026-01-11  
**Versión:** 2.0 (corregida - lógica de suspensión independiente)
