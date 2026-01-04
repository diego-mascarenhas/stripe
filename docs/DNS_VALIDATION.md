# 🌐 DNS Validation System

Sistema de validación DNS configurable para verificar que los dominios de clientes apunten correctamente a los servicios de Revision Alpha.

## 📋 Características

- ✅ Validación de Nameservers
- ✅ Validación de IPs del dominio
- ✅ Validación de IPs del mail server (MX records)
- ✅ Validación de registros SPF
- ✅ Configuración por servicio (WHM, VPS, Mailer, etc.)
- ✅ Configuración flexible mediante .env

## ⚙️ Configuración

### 1. Variables de Entorno

Agrega estas variables a tu archivo `.env`:

```env
# Configuración por defecto (aplica a todos los servicios)
DNS_NAMESERVERS=ns1.revisionalpha.com,ns2.revisionalpha.com
DNS_VALID_IPS=51.83.76.40,51.195.217.63,66.70.189.5
DNS_SPF_INCLUDE=spf.revisionalpha.com

# Servicios específicos (opcional, solo si necesitas configuraciones diferentes)
# DNS_VPS_NAMESERVERS=ns1.vpsservice.com,ns2.vpsservice.com
# DNS_VPS_VALID_IPS=192.0.2.1,192.0.2.2
# DNS_VPS_SPF_INCLUDE=spf.vpsservice.com

# Servicio por defecto
DNS_DEFAULT_SERVICE=default
```

**Importante:**
- Separa valores con **comas** (sin espacios)
- No uses comillas
- Las IPs deben ser IPv4 válidas
- Usa las variables genéricas (`DNS_NAMESERVERS`, etc.) para la configuración principal
- Crea variables específicas (`DNS_VPS_NAMESERVERS`, etc.) solo si necesitas configuraciones diferentes por servicio

### 2. Archivo de Configuración

El archivo `config/dns.php` procesa estas variables automáticamente. No necesitas modificarlo manualmente.

## 🚀 Uso

### Desde el Panel Filament

1. Ve a la vista de una suscripción
2. Haz clic en el botón **"Sincronizar"** en la sección de Metadatos
3. Se mostrará la sección **"Validación de Configuración DNS"** con 4 badges:
   - 🟢 **Verde**: Configurado correctamente
   - 🟡 **Amarillo**: Advertencia
   - 🔴 **Rojo**: Error de configuración

### Desde la Línea de Comandos

Prueba la validación DNS de cualquier dominio:

```bash
php artisan dns:test example.com
```

Con un servicio específico:

```bash
php artisan dns:test example.com --service=vps
```

### Desde el Código

```php
use App\Services\DNS\DNSLookupService;

$dns = app(DNSLookupService::class);

// Validar con el servicio por defecto (usa variables DNS_NAMESERVERS, DNS_VALID_IPS, etc.)
$validation = $dns->validateRevisionAlphaConfiguration('example.com');
// o
$validation = $dns->validateServiceConfiguration('example.com');

// Validar con un servicio específico
$validation = $dns->validateServiceConfiguration('example.com', 'vps');

// Resultado:
[
    'has_own_ns' => true,
    'current_nameservers' => ['ns1.revisionalpha.com', 'ns2.revisionalpha.com'],
    'expected_nameservers' => ['ns1.revisionalpha.com', 'ns2.revisionalpha.com'],
    
    'domain_points_to_service' => true,
    'domain_ips' => ['51.195.217.63'],
    'matching_domain_ip' => '51.195.217.63',
    'expected_ips' => ['51.83.76.40', '51.195.217.63', '66.70.189.5'],
    
    'mail_points_to_service' => true,
    'matching_mail_ip' => '51.195.217.63',
    'mx_records' => [...],
    
    'has_spf_include' => true,
    'spf_record' => 'v=spf1 include:spf.revisionalpha.com ~all',
    'expected_spf_include' => 'include:spf.revisionalpha.com',
]
```

## 🔧 Agregar Nuevos Servicios

Si necesitas configuraciones diferentes para distintos tipos de servicios (por ejemplo, VPS con diferentes IPs que Hosting):

### 1. Agregar variables al .env:

```env
# Configuración por defecto (la mayoría de servicios)
DNS_NAMESERVERS=ns1.revisionalpha.com,ns2.revisionalpha.com
DNS_VALID_IPS=51.83.76.40,51.195.217.63,66.70.189.5
DNS_SPF_INCLUDE=spf.revisionalpha.com

# Configuración específica para VPS
DNS_VPS_NAMESERVERS=ns1.vpshost.com,ns2.vpshost.com
DNS_VPS_VALID_IPS=203.0.113.1,203.0.113.2
DNS_VPS_SPF_INCLUDE=spf.vpshost.com
```

### 2. Actualizar config/dns.php:

```php
'services' => [
    'default' => [...], // Usa DNS_NAMESERVERS, DNS_VALID_IPS, etc.
    
    'vps' => [
        'nameservers' => array_filter(explode(',', env('DNS_VPS_NAMESERVERS', ''))),
        'valid_ips' => array_filter(explode(',', env('DNS_VPS_VALID_IPS', ''))),
        'spf_include' => env('DNS_VPS_SPF_INCLUDE', ''),
    ],
],
```

### 3. Usar el nuevo servicio:

```php
// Usa la configuración por defecto
$validation = $dns->validateServiceConfiguration('example.com');

// Usa configuración específica de VPS
$validation = $dns->validateServiceConfiguration('example.com', 'vps');
```

## 📊 Interpretación de Resultados

### Nameservers

- ✅ **Verde**: El dominio usa los nameservers esperados
- ⚠️ **Amarillo**: El dominio no usa los nameservers configurados

### IP del Dominio

- ✅ **Verde**: El registro A apunta a una de las IPs válidas
- ❌ **Rojo**: El dominio no apunta a ninguna IP válida

### Mail Server

- ✅ **Verde**: Los registros MX apuntan a una de las IPs válidas
- ❌ **Rojo**: Los MX no apuntan a ninguna IP válida

### SPF Record

- ✅ **Verde**: El registro SPF incluye el dominio esperado
- ⚠️ **Amarillo**: No se encontró el include esperado en el SPF

## 🐛 Troubleshooting

### Los nameservers no se detectan correctamente

El sistema intenta usar `dig` primero (más confiable). Si no está disponible, usa `dns_get_record()`.

Para instalar `dig`:
- **macOS**: Viene preinstalado
- **Ubuntu/Debian**: `sudo apt-get install dnsutils`
- **CentOS/RHEL**: `sudo yum install bind-utils`

### Las validaciones están en caché

El sistema consulta directamente los servidores DNS autoritativos, pero si hay caché:

```bash
# Limpiar caché DNS del sistema
sudo dscacheutil -flushcache  # macOS
sudo systemd-resolve --flush-caches  # Linux
```

### Errores en los logs

Revisa los logs para debugging:

```bash
tail -f storage/logs/laravel.log | grep "DNS"
```

## 📝 Notas Técnicas

- Usa `dns_get_record()` nativo de PHP (no requiere extensiones)
- Fallback a `dig` command para nameservers (más confiable)
- Cache-free: Consulta directo a servidores autoritativos
- Valida IPv4 (IPv6 disponible pero no implementado aún)
- Compatible con dominios internacionales (IDN)

