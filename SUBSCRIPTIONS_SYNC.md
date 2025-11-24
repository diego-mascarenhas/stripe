# Sincronización de Suscripciones

## Protección de Suscripciones Manuales

El sistema ahora protege las suscripciones de compra (`type = 'buy'`) que se ingresan manualmente, evitando que sean sobrescritas por la sincronización de Stripe.

### Tipos de Suscripciones

1. **`type = 'sell'`**: Suscripciones de venta que vienen de Stripe
   - Se sincronizan automáticamente desde Stripe
   - Se actualizan con cada sincronización
   - Fuente: API de Stripe

2. **`type = 'buy'`**: Suscripciones de compra ingresadas manualmente
   - Se crean manualmente desde Filament
   - **NO se modifican** durante la sincronización de Stripe
   - Fuente: Entrada manual del usuario

### Lógica de Protección

**Archivo**: `app/Actions/Subscriptions/SyncStripeSubscriptions.php`

```php
if ($subscription) {
    // PROTECCIÓN: No actualizar suscripciones de tipo 'buy' (son manuales)
    if ($subscription->type === 'buy') {
        continue; // Omitir esta suscripción
    }
    $this->updateSubscription($subscription, $mapped);
}
```

### Comportamiento del Sistema

#### Antes (❌ Problema)
1. Usuario crea suscripción manual con `type = 'buy'`
2. Se ejecuta `php artisan subscriptions:sync`
3. Si existía un `stripe_id` coincidente, se sobrescribía con `type = 'sell'`
4. Se perdía la información manual

#### Ahora (✅ Solucionado)
1. Usuario crea suscripción manual con `type = 'buy'`
2. Se ejecuta `php artisan subscriptions:sync`
3. El sistema detecta `type = 'buy'` y **omite la actualización**
4. La información manual se mantiene intacta

### Comandos de Sincronización

```bash
# Sincronizar tipos de cambio (ejecuta cada hora, solo si han pasado 23h)
php artisan currency:sync

# Sincronizar suscripciones de Stripe (NO afecta las de tipo 'buy')
php artisan subscriptions:sync

# Sincronizar facturas de Stripe
php artisan invoices:sync

# Sincronizar notas de crédito de Stripe
php artisan creditnotes:sync
```

### Schedule Automático

El sistema ejecuta automáticamente:

- **Currency rates (Tipos de cambio)**: Cada 1 hora
  - Verifica cada hora si ha pasado ≥1 hora desde la última actualización
  - Garantiza tasas de cambio siempre actualizadas para cálculos de pagos

- **Subscriptions (Suscripciones)**: Cada 4 horas (00:00, 04:00, 08:00, 12:00, 16:00, 20:00)
  - Protege suscripciones manuales (`type = 'buy'`)
  - Solo actualiza suscripciones de Stripe (`type = 'sell'`)

- **Invoices (Facturas)**: Cada 4 horas con offset de 15 min (00:15, 04:15, 08:15, 12:15, 16:15, 20:15)

- **Credit Notes (Notas de crédito)**: Cada 4 horas con offset de 30 min (00:30, 04:30, 08:30, 12:30, 16:30, 20:30)

### Verificación

Para verificar que tus suscripciones manuales están protegidas:

```bash
php artisan tinker
>>> \App\Models\Subscription::where('type', 'buy')->count()
```

Este comando te mostrará cuántas suscripciones de compra tienes protegidas.

### Registro de Cambios

Todos los cambios en las suscripciones se registran en la tabla `subscription_changes`:

```php
// Ver cambios de una suscripción
$subscription = \App\Models\Subscription::find(1);
$subscription->changes()->get();
```

Esto te permite auditar qué cambios se hicieron y cuándo.

### Restauración (Si se perdieron datos)

Si algunas suscripciones manuales fueron sobrescritas antes de implementar esta protección, puedes verificar el historial en `subscription_changes` para recuperar los valores anteriores.

```bash
php artisan tinker
>>> $changes = \App\Models\SubscriptionChange::where('source', 'stripe')
    ->whereJsonContains('changed_fields', 'type')
    ->get();
>>> $changes->each(function($change) {
    if ($change->previous_values['type'] === 'buy') {
        echo "Subscription ID: {$change->subscription_id} fue cambiada de buy a sell\n";
    }
});
```

