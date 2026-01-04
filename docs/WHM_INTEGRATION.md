# WHM/cPanel Integration

## 📋 Configuración

### Variables de Entorno

Agrega las siguientes variables a tu archivo `.env`:

```bash
# WHM API Credentials
WHM_USERNAME=your_reseller_username
WHM_PASSWORD=your_reseller_password
WHM_DEFAULT_SERVER=muninn.revisionalpha.cloud

# Optional settings
WHM_API_PORT=2087
WHM_VERIFY_SSL=true
WHM_TIMEOUT=30
```

### Configuración en el Servidor

1. **Acceso WHM**: Asegúrate de tener credenciales de **reseller** con permisos para:
   - Suspender cuentas (`suspendacct`)
   - Reactivar cuentas (`unsuspendacct`)
   - Listar cuentas (`listaccts`)
   - Ver información de cuentas (`accountsummary`)

2. **Firewall**: El servidor debe permitir conexiones HTTPS al puerto **2087** del WHM.

3. **SSL**: Si tus servidores WHM usan certificados autofirmados, configura `WHM_VERIFY_SSL=false` (no recomendado en producción).

## 🚀 Uso

### Sincronización Automática

El sistema sincroniza automáticamente las cuentas cuando cambia el estado de una suscripción, **solo si**:
- La suscripción es tipo **"sell"**
- Tiene **`auto_suspend`** activado en metadata
- Tiene **`server`** y **`user`** configurados en metadata

**Estados que suspenden:**
- `canceled`
- `past_due`
- `unpaid`
- `incomplete_expired`

**Estados que reactivan:**
- `active`

### Sincronización Manual

#### Sincronizar todas las suscripciones:
```bash
php artisan subscriptions:sync-whm
```

#### Sincronizar una suscripción específica:
```bash
php artisan subscriptions:sync-whm --subscription=123
```

### Uso Programático

```php
use App\Actions\Subscriptions\SyncSubscriptionWithWHM;

$subscription = Subscription::find(123);

app(SyncSubscriptionWithWHM::class)->handle($subscription);
```

## 📊 Metadata Requerida

Para que funcione la sincronización, la suscripción debe tener en `data`:

```json
{
  "type": "hosting",
  "plan": "beginner",
  "server": "muninn.revisionalpha.cloud",
  "user": "zumcatering",
  "domain": "zumcatering.com.ar",
  "email": "info@zumcatering.com.ar",
  "auto_suspend": true
}
```

## 🔍 Monitoreo

Todos los eventos se registran en los logs de Laravel:

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log | grep WHM
```

**Eventos registrados:**
- ✅ Suspensiones exitosas
- ✅ Reactivaciones exitosas
- ⚠️ Errores de conexión
- ⚠️ Cuentas sin metadata completa
- ℹ️ Cambios de estado de suscripciones

## 🛠️ Métodos Disponibles

### WHMServerManager

```php
use App\Services\WHM\WHMServerManager;

$whm = app(WHMServerManager::class);

// Suspender cuenta
$whm->suspendAccount('server.example.com', 'username', 'Payment overdue');

// Reactivar cuenta
$whm->unsuspendAccount('server.example.com', 'username');

// Obtener info de cuenta
$info = $whm->getAccountInfo('server.example.com', 'username');

// Listar todas las cuentas de un servidor
$accounts = $whm->listAccounts('server.example.com');

// Crear nueva cuenta
$whm->createAccount([
    'server' => 'server.example.com',
    'username' => 'newuser',
    'domain' => 'example.com',
    'email' => 'user@example.com',
    'plan' => 'beginner',
    'password' => 'secure_password',
]);
```

## 🔐 Seguridad

1. **Credenciales**: Las credenciales de WHM se almacenan en `.env` y no se commitean al repositorio.

2. **SSL/TLS**: Por defecto, todas las conexiones usan HTTPS con verificación SSL.

3. **Logs**: Todos los errores y acciones se registran con información completa para auditoría.

4. **Permisos**: El usuario reseller debe tener **solo** los permisos necesarios para las operaciones requeridas.

## 🐛 Troubleshooting

### Error: "Connection timeout"
- Verifica que el servidor WHM sea accesible desde tu aplicación
- Revisa las reglas del firewall
- Aumenta `WHM_TIMEOUT` en `.env`

### Error: "Authentication failed"
- Verifica que `WHM_USERNAME` y `WHM_PASSWORD` sean correctos
- Confirma que el usuario tiene permisos de reseller

### No se suspende automáticamente
- Verifica que `auto_suspend` esté en `true` en la metadata
- Confirma que la suscripción sea tipo `sell`
- Revisa los logs: `tail -f storage/logs/laravel.log`

## 📚 Documentación API WHM

- [WHM API 1 - suspendacct](https://api.docs.cpanel.net/openapi/whm/operation/suspendacct/)
- [WHM API 1 - unsuspendacct](https://api.docs.cpanel.net/openapi/whm/operation/unsuspendacct/)
- [WHM API 1 - accountsummary](https://api.docs.cpanel.net/openapi/whm/operation/accountsummary/)
- [WHM API Authentication](https://docs.cpanel.net/knowledge-base/web-services/how-to-use-cpanel-api-tokens/)

