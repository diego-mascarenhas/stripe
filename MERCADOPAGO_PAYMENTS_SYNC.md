# Sincronización de Pagos de MercadoPago

Este documento describe la funcionalidad de sincronización de pagos desde MercadoPago a la base de datos local.

## 🚀 Inicio Rápido

**¿Primera vez? Sigue estos pasos:**

1. 📝 **Obtén tus credenciales** → [Ver guía detallada](#cómo-obtener-tus-credenciales-de-mercadopago)
2. ⚙️ **Configura el .env** → Agrega `MERCADOPAGO_ACCESS_TOKEN`
3. 🗄️ **Ejecuta la migración** → `php artisan migrate`
4. 🧪 **Prueba las credenciales** → `php artisan mercadopago:test-credentials`
5. 🔄 **Sincroniza** → `php artisan payments:sync-mercadopago`
6. ✅ **Verifica** → Revisa en **Facturación > Pagos MP** en Filament

## Configuración

### Cómo Obtener tus Credenciales de MercadoPago

#### Opción 1: Credenciales Simples (Recomendado para empezar)

Para sincronizar pagos de tu propia cuenta, usa las credenciales de tu aplicación:

1. **Accede a Mercado Pago Developers**
   - Ve a [https://www.mercadopago.com.ar/developers/panel](https://www.mercadopago.com.ar/developers/panel)
   - Inicia sesión con tu cuenta de Mercado Pago

2. **Crea o selecciona una aplicación**
   - Si no tienes una aplicación, haz clic en **"Crear aplicación"**
   - Ingresa un nombre descriptivo (ej: "Sincronización de Pagos")
   - Selecciona el modelo de integración (ej: "Pagos online")

3. **Obtén tus credenciales**
   - En el panel de tu aplicación, ve a la sección **"Credenciales"**
   - Verás dos tipos de credenciales:
     - **Credenciales de prueba** (para desarrollo)
     - **Credenciales de producción** (para producción)
   
4. **Copia el Access Token**
   - Copia el **"Access Token"** (comienza con `APP_USR-` o `TEST-`)
   - Este es el token que necesitas para `MERCADOPAGO_ACCESS_TOKEN`
   - La **Public Key** es opcional para este caso de uso

#### Opción 2: OAuth (Para acceder a cuentas de terceros)

Si necesitas acceder a pagos de cuentas de otros vendedores, usa el flujo OAuth "Authorization Code":

1. **Configura tu aplicación**
   - En el panel de desarrolladores, edita tu aplicación
   - Agrega una **"URL de redireccionamiento"** (ej: `https://tu-sitio.test/callback`)

2. **Genera el enlace de autorización**
   ```
   https://auth.mercadopago.com/authorization?client_id=TU_CLIENT_ID&response_type=code&platform_id=mp&redirect_uri=TU_REDIRECT_URI
   ```

3. **Obtén el código de autorización**
   - Envía el enlace al vendedor
   - El vendedor autoriza el acceso
   - Recibirás un `code` en tu URL de redireccionamiento

4. **Intercambia el código por un Access Token**
   ```bash
   curl -X POST https://api.mercadopago.com/oauth/token \
     -H 'Content-Type: application/json' \
     -d '{
       "client_id": "TU_CLIENT_ID",
       "client_secret": "TU_CLIENT_SECRET",
       "code": "CODIGO_RECIBIDO",
       "grant_type": "authorization_code",
       "redirect_uri": "TU_REDIRECT_URI"
     }'
   ```

### Variables de Entorno

Agrega las siguientes variables a tu archivo `.env`:

#### 🔧 Configuración de Producción

```env
# Access Token de Mercado Pago (REQUERIDO)
MERCADOPAGO_ACCESS_TOKEN=APP_USR-1234567890-123456-abc123def456-789012345

# Public Key (opcional para sincronización)
MERCADOPAGO_PUBLIC_KEY=APP_USR-abc123-123456-def789
```

**Dónde obtenerlas:**
1. Ve a [https://www.mercadopago.com.ar/developers/panel/app](https://www.mercadopago.com.ar/developers/panel/app)
2. Selecciona tu aplicación
3. Clic en **"Credenciales de producción"**
4. Copia el **"Access Token"** → Pégalo en `MERCADOPAGO_ACCESS_TOKEN`

#### 🧪 Configuración de Pruebas (Testing)

```env
# Access Token de TEST
MERCADOPAGO_ACCESS_TOKEN=TEST-1234567890-123456-abc123def456-789012345

# Public Key de TEST
MERCADOPAGO_PUBLIC_KEY=TEST-abc123-123456-def789
```

**Dónde obtenerlas:**
1. Mismo panel de desarrolladores
2. Clic en **"Credenciales de prueba"**
3. Copia el **"Access Token"** de prueba

#### 🔍 ¿Cómo sé si mi token es correcto?

- ✅ Token de **Producción**: Comienza con `APP_USR-`
- ✅ Token de **Prueba**: Comienza con `TEST-`
- ✅ Longitud típica: 60-80 caracteres
- ❌ Si está incompleto o tiene espacios, no funcionará

### ⚠️ Seguridad

- **NUNCA** compartas tu Access Token públicamente
- **NUNCA** lo incluyas en código frontend o repositorios públicos
- Usa credenciales de prueba para desarrollo
- Cambia a credenciales de producción solo cuando estés listo
- Regenera tus credenciales si sospechas que fueron comprometidas

## Base de Datos

### Migración

La tabla `payments` almacena toda la información de los pagos de MercadoPago:

```bash
php artisan migrate
```

### Estructura de la Tabla

La tabla incluye:

- **Identificadores**: `mercadopago_id`, `external_reference`
- **Información del pagador**: email, nombre, identificación
- **Montos**: transaction_amount, net_amount, fees, shipping
- **Fechas**: payment_created_at, payment_approved_at, money_release_date
- **Estado**: status, status_detail
- **Método de pago**: payment_type, payment_method, installments
- **Metadata**: raw_payload (JSON completo de MercadoPago)

## Sincronización

### Verificar Credenciales

**¡IMPORTANTE!** Antes de sincronizar por primera vez, verifica que tus credenciales funcionen:

```bash
php artisan mercadopago:test-credentials
```

Este comando:
- ✅ Verifica que el Access Token esté configurado
- ✅ Valida el formato del token (TEST vs APP_USR)
- ✅ Prueba la conexión con la API de MercadoPago
- ✅ Intenta obtener un pago de muestra
- ✅ Muestra información útil para debugging

**Ejemplo de salida exitosa:**

```
🔍 Verificando credenciales de MercadoPago...

Access Token encontrado: APP_USR-1234567890...

✓ Estás usando credenciales de PRODUCCIÓN (APP_USR)
  Verás pagos reales de tu cuenta.

🔄 Probando conexión con la API de MercadoPago...

✅ ¡Credenciales válidas!

Se encontraron pagos en tu cuenta:
  • Total de pagos consultados: 1
  • Último pago ID: 123456789
  • Fecha: 2025-12-05T10:30:00Z
  • Monto: 1500.00 ARS
  • Estado: approved

🚀 Puedes ejecutar la sincronización con:
   php artisan payments:sync-mercadopago
```

### Comando de Sincronización

Sincronizar pagos de los últimos 30 días:

```bash
php artisan payments:sync-mercadopago
```

Sincronizar un rango de fechas específico:

```bash
php artisan payments:sync-mercadopago --begin-date="2025-11-01T00:00:00Z" --end-date="2025-12-01T00:00:00Z"
```

Sincronizar los últimos N días:

```bash
php artisan payments:sync-mercadopago --days=7
```

### Desde el Panel Filament

1. Accede al panel de administración
2. Ve a **Facturación** > **Pagos MP**
3. Haz clic en el botón **Sincronizar**
4. Confirma la sincronización

### Programar Sincronización Automática

Puedes agregar el comando al scheduler en `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sincronizar pagos cada hora
    $schedule->command('payments:sync-mercadopago --days=1')
        ->hourly();
    
    // O sincronizar una vez al día
    $schedule->command('payments:sync-mercadopago --days=7')
        ->daily();
}
```

### 📝 Resumen de Comandos

| Comando | Descripción | Uso |
|---------|-------------|-----|
| `mercadopago:test-credentials` | Verifica que las credenciales funcionen | `php artisan mercadopago:test-credentials` |
| `payments:sync-mercadopago` | Sincroniza pagos (últimos 30 días) | `php artisan payments:sync-mercadopago` |
| `payments:sync-mercadopago --days=N` | Sincroniza últimos N días | `php artisan payments:sync-mercadopago --days=7` |
| `payments:sync-mercadopago --begin-date=X --end-date=Y` | Sincroniza rango específico | `php artisan payments:sync-mercadopago --begin-date="2025-11-01T00:00:00Z" --end-date="2025-12-01T00:00:00Z"` |

## Uso Programático

### Sincronizar desde Código

```php
use App\Actions\Payments\SyncMercadoPagoPayments;

$sync = app(SyncMercadoPagoPayments::class);

// Sincronizar últimos 30 días
$count = $sync->handle();

// Sincronizar rango específico
$count = $sync->handle(
    beginDate: '2025-11-01T00:00:00Z',
    endDate: '2025-12-01T00:00:00Z'
);

// Sincronizar un pago específico
$payment = $sync->syncPaymentById('1234567890');
```

### Usar el Servicio de MercadoPago

```php
use App\Services\MercadoPago\MercadoPagoService;

$service = app(MercadoPagoService::class);

// Obtener pagos aprobados
$approvedPayments = $service->getApprovedPayments();

// Obtener pagos pendientes
$pendingPayments = $service->getPendingPayments();

// Buscar por referencia externa
$payments = $service->getPaymentsByExternalReference('ORDER-123');

// Buscar por email del pagador
$payments = $service->getPaymentsByPayerEmail('customer@example.com');

// Obtener un pago específico
$payment = $service->getPayment('1234567890');
```

## Modelo Payment

### Propiedades Calculadas

```php
// Obtener etiqueta del estado en español
$payment->status_label; // "Aprobado", "Pendiente", etc.

// Obtener color para badges
$payment->status_color; // "success", "warning", "danger", etc.

// Nombre completo del pagador
$payment->payer_full_name;

// Etiqueta del método de pago
$payment->payment_method_label; // "Tarjeta de crédito", etc.
```

### Métodos de Verificación

```php
// Verificar si está aprobado
if ($payment->isApproved()) {
    // Lógica para pago aprobado
}

// Verificar si está pendiente
if ($payment->isPending()) {
    // Lógica para pago pendiente
}

// Verificar si fue rechazado
if ($payment->isRejected()) {
    // Lógica para pago rechazado
}
```

## Estados de Pagos

Los estados posibles de un pago en MercadoPago son:

- **approved**: Pago aprobado
- **pending**: Pendiente de procesamiento
- **in_process**: En proceso
- **rejected**: Rechazado
- **cancelled**: Cancelado
- **refunded**: Reembolsado
- **charged_back**: Contracargo

## API de MercadoPago

### Filtros Disponibles

El servicio de MercadoPago permite filtrar por:

- Rango de fechas (`begin_date`, `end_date`)
- Estado del pago (`status`)
- Email del pagador (`payer.email`)
- Referencia externa (`external_reference`)
- ID del pagador (`payer_id`)

### Límites y Paginación

- La API de MercadoPago tiene un límite de 50 resultados por página
- La sincronización automáticamente pagina todos los resultados
- Se respetan los rate limits de la API

## Consideraciones

### Sincronización Incremental

- Los pagos existentes se actualizan si cambian en MercadoPago
- La sincronización usa `mercadopago_id` como identificador único
- Se almacena `last_synced_at` para tracking

### Modo de Producción vs Test

- El campo `live_mode` indica si el pago es de producción o test
- Puedes filtrar en el panel por modo de producción
- Usa credenciales de test para desarrollo

### Raw Payload

- El JSON completo de MercadoPago se guarda en `raw_payload`
- Útil para debugging y acceder a campos no mapeados
- Se puede consultar con `$payment->raw_payload['campo']`

## Webhooks (Futuro)

Para implementar webhooks de MercadoPago en el futuro:

1. Crear una ruta para recibir notificaciones
2. Validar la firma del webhook
3. Usar `SyncMercadoPagoPayments::syncPaymentById()` para sincronizar

## Troubleshooting

### ❌ Error de Autenticación (401 Unauthorized)

**Problema:** La API de MercadoPago rechaza tu Access Token.

**Soluciones:**

1. **Verifica que el token sea correcto**
   - Revisa que copiaste el Access Token completo (sin espacios extra)
   - Debe comenzar con `APP_USR-` (producción) o `TEST-` (pruebas)

2. **Confirma que el token no haya expirado**
   - Los tokens de OAuth tienen validez de 180 días
   - Los tokens simples no expiran, pero pueden ser revocados
   - Regenera el token desde el panel de desarrolladores si es necesario

3. **Verifica el entorno**
   - Si usas credenciales de TEST, solo verás pagos de prueba
   - Si usas credenciales de producción (APP_USR), verás pagos reales

4. **Revisa los permisos de la aplicación**
   - En el panel de MercadoPago, verifica que tu aplicación tenga permisos para leer pagos
   - Ve a "Configuración de la aplicación" > "Permisos"

### ❌ Sin Resultados en la Sincronización

**Problema:** La sincronización se ejecuta pero no trae pagos.

**Soluciones:**

- **Verifica el rango de fechas**: Asegúrate de que haya pagos en el período consultado
- **Confirma el modo**: Los tokens de TEST solo traen pagos de prueba, no reales
- **Revisa los logs**: Consulta `storage/logs/laravel.log` para ver detalles
- **Prueba con un pago específico**: 
  ```php
  $service = app(MercadoPagoService::class);
  $payment = $service->getPayment('TU_PAYMENT_ID');
  dd($payment);
  ```

### ❌ Error 429 (Rate Limiting)

**Problema:** MercadoPago está limitando las peticiones.

**Soluciones:**

- Espera 1-2 minutos antes de volver a sincronizar
- Reduce la frecuencia de sincronización
- Sincroniza rangos de fechas más pequeños

### ❌ Token Inválido o Expirado

**Problema:** El Access Token dejó de funcionar.

**Soluciones:**

1. **Si usas credenciales simples**:
   - Ve al [panel de desarrolladores](https://www.mercadopago.com.ar/developers/panel)
   - Selecciona tu aplicación
   - Copia nuevamente el Access Token
   - Actualiza tu `.env`

2. **Si usas OAuth**:
   - Ejecuta nuevamente el flujo de autorización
   - O usa el Refresh Token para renovar (ver documentación de MercadoPago)

### ❌ Error "Application not found" o "Invalid client_id"

**Problema:** La aplicación no existe o fue eliminada.

**Soluciones:**

- Verifica que la aplicación aún existe en el panel de desarrolladores
- Confirma que estás usando el `client_id` correcto
- Si la aplicación fue eliminada, crea una nueva

### 🔍 Verificar Credenciales

Para probar si tus credenciales funcionan correctamente:

```bash
curl -X GET \
  'https://api.mercadopago.com/v1/payments/search?limit=1' \
  -H 'Authorization: Bearer TU_ACCESS_TOKEN'
```

Si responde con datos, tus credenciales están correctas. Si responde con error 401, hay un problema con el token.

### 📝 Logs y Debugging

Todos los errores se registran en:

```
storage/logs/laravel.log
```

Para debugging detallado, puedes habilitar el log de peticiones HTTP en el servicio de MercadoPago agregando logs en `app/Services/MercadoPago/MercadoPagoService.php`.

