# 📧 Copias de Emails de Suspensión al Admin

## ✅ Implementación Completada

Cuando se suspende un servicio (automática o manualmente), el sistema ahora envía **dos emails**:

### 1. Email al Cliente (CON tracking)
- Destinatario: Cliente (`customer_email`)
- Subject: `❌ Tu servicio ha sido suspendido - Reactívalo ahora`
- Tracking: ✅ **SÍ** incluye pixel de seguimiento
- Propósito: Notificar al cliente y trackear si abre el email

### 2. Copia al Admin (SIN tracking)
- Destinatario: Email del `.env` (`MAIL_FROM_ADDRESS`)
- Subject: `[COPIA] ❌ Tu servicio ha sido suspendido - {Nombre del Cliente}`
- Tracking: ❌ **NO** incluye pixel de seguimiento
- Propósito: Mantener al admin informado sin contaminar estadísticas

---

## 🔧 Configuración

Asegúrate de tener configurado en tu `.env`:

```env
MAIL_FROM_ADDRESS=tu-email@revisionalpha.com
MAIL_FROM_NAME="Revision Alpha Admin"
```

---

## 📊 Comportamiento

### Suspensión Automática (día 45)

```bash
php artisan subscriptions:send-notifications
```

**Resultado:**
```
✓ Enviado: Servicio suspendido a cliente@ejemplo.com
  ↳ Copia enviada a admin: admin@revisionalpha.com
```

### Suspensión Manual

```bash
php artisan subscription:force-suspend cus_XXX
```

**Resultado:**
```
✅ Email sent to: cliente@ejemplo.com
   ↳ Copia enviada a admin: admin@revisionalpha.com
```

---

## 🎯 Ventajas

### Para el Cliente:
- Email con tracking → Sabes si lo abrió
- HTML completo con botones de pago

### Para el Admin:
- Email SIN tracking → No interfiere con estadísticas
- Subject con `[COPIA]` + nombre del cliente para fácil identificación
- HTML idéntico al del cliente (sin el pixel invisible)

---

## 🔍 Diferencias Técnicas

### Email al Cliente (con tracking):
```html
<html>
  <body>
    <!-- Contenido del email -->
    <img src="https://tu-app.com/track/abc123" width="1" height="1" style="display:block;width:1px;height:1px;" />
  </body>
</html>
```

### Email al Admin (sin tracking):
```html
<html>
  <body>
    <!-- Mismo contenido del email -->
    <!-- ❌ NO incluye el pixel de tracking -->
  </body>
</html>
```

---

## 📝 Archivos Modificados

1. ✅ `app/Console/Commands/SendSubscriptionNotifications.php`
   - Agregada lógica para enviar copia al admin sin tracking
   - Solo aplica para `notification_type === 'suspended'`

2. ✅ `app/Console/Commands/ForceSuspendSubscription.php`
   - Agregada lógica para enviar copia al admin sin tracking
   - Aplica en suspensiones manuales

---

## 🧪 Testing

### Probar en desarrollo:

```bash
# 1. Asegúrate de tener MAIL_FROM_ADDRESS en .env
echo $MAIL_FROM_ADDRESS

# 2. Ejecutar suspensión manual de prueba
php artisan subscription:force-suspend {subscription_id}

# 3. Verificar que llegaron 2 emails:
#    - Uno al cliente (con pixel)
#    - Uno a ti (sin pixel)
```

### Verificar logs:

```bash
tail -f storage/logs/laravel.log | grep -E "Copia enviada|No se pudo enviar copia"
```

---

## ⚠️ Notas Importantes

1. **Solo para suspensiones**: Las notificaciones de warning (5 días, 2 días) NO envían copia al admin, solo las de suspensión.

2. **Manejo de errores**: Si falla el envío de la copia al admin, NO afecta el envío al cliente. El proceso continúa normalmente.

3. **Subject modificado**: La copia al admin tiene `[COPIA]` al inicio y el nombre del cliente al final para fácil identificación.

---

## 🔄 Flujo Completo

```
Cliente tiene factura de 45+ días
         ↓
Sistema suspende automáticamente
         ↓
┌────────────────────────────────┐
│ Renderiza email HTML           │
└────────────────────────────────┘
         ↓
┌────────────────────────────────┐
│ Agrega pixel de tracking       │
└────────────────────────────────┘
         ↓
┌────────────────────────────────┐
│ Envía a CLIENTE (con pixel)    │ ← Tracking habilitado
└────────────────────────────────┘
         ↓
┌────────────────────────────────┐
│ Envía a ADMIN (sin pixel)      │ ← Sin tracking
└────────────────────────────────┘
         ↓
✅ Ambos emails enviados
```

---

**Fecha de implementación:** 2026-01-11  
**Versión:** 1.0  
**Estado:** ✅ Implementado y documentado
