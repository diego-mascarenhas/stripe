# 🌐 DNS Validation System

Configurable DNS validation system to verify that customer domains correctly point to Revision Alpha services.

## 📋 Features

- ✅ Nameserver validation
- ✅ Domain IP validation
- ✅ Mail server IP validation (MX records)
- ✅ SPF record validation
- ✅ Per-service configuration (WHM, VPS, Mailer, etc.)
- ✅ Flexible configuration via .env

## ⚙️ Configuration

### 1. Environment Variables

Add these variables to your `.env` file:

```env
# Default configuration (applies to all services)
DNS_NAMESERVERS=ns1.revisionalpha.com,ns2.revisionalpha.com
DNS_VALID_IPS=51.83.76.40,51.195.217.63,66.70.189.5
DNS_SPF_INCLUDE=spf.revisionalpha.com

# Service-specific (optional, only if you need different configurations)
# DNS_VPS_NAMESERVERS=ns1.vpsservice.com,ns2.vpsservice.com
# DNS_VPS_VALID_IPS=192.0.2.1,192.0.2.2
# DNS_VPS_SPF_INCLUDE=spf.vpsservice.com

# Default service
DNS_DEFAULT_SERVICE=default
```

**Important:**
- Separate values with **commas** (no spaces)
- Don't use quotes
- IPs must be valid IPv4 addresses
- Use generic variables (`DNS_NAMESERVERS`, etc.) for main configuration
- Create specific variables (`DNS_VPS_NAMESERVERS`, etc.) only if you need different per-service configurations

### 2. Configuration File

The `config/dns.php` file processes these variables automatically. You don't need to modify it manually.

## 🚀 Usage

### From Filament Panel

1. Go to a subscription view
2. Click the **"Sync"** button in the Metadata section
3. The **"DNS Configuration Validation"** section will show 4 badges:
   - 🟢 **Green**: Correctly configured
   - 🟡 **Yellow**: Warning
   - 🔴 **Red**: Configuration error

### From Command Line

Test DNS validation for any domain:

```bash
php artisan dns:test example.com
```

With a specific service:

```bash
php artisan dns:test example.com --service=vps
```

### From Code

```php
use App\Services\DNS\DNSLookupService;

$dns = app(DNSLookupService::class);

// Validate with default service (uses DNS_NAMESERVERS, DNS_VALID_IPS, etc.)
$validation = $dns->validateRevisionAlphaConfiguration('example.com');
// or
$validation = $dns->validateServiceConfiguration('example.com');

// Validate with specific service
$validation = $dns->validateServiceConfiguration('example.com', 'vps');

// Result:
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

## 🔧 Adding New Services

If you need different configurations for different service types (e.g., VPS with different IPs than Hosting):

### 1. Add variables to .env:

```env
# Default configuration (most services)
DNS_NAMESERVERS=ns1.revisionalpha.com,ns2.revisionalpha.com
DNS_VALID_IPS=51.83.76.40,51.195.217.63,66.70.189.5
DNS_SPF_INCLUDE=spf.revisionalpha.com

# VPS-specific configuration
DNS_VPS_NAMESERVERS=ns1.vpshost.com,ns2.vpshost.com
DNS_VPS_VALID_IPS=203.0.113.1,203.0.113.2
DNS_VPS_SPF_INCLUDE=spf.vpshost.com
```

### 2. Update config/dns.php:

```php
'services' => [
    'default' => [...], // Uses DNS_NAMESERVERS, DNS_VALID_IPS, etc.
    
    'vps' => [
        'nameservers' => array_filter(explode(',', env('DNS_VPS_NAMESERVERS', ''))),
        'valid_ips' => array_filter(explode(',', env('DNS_VPS_VALID_IPS', ''))),
        'spf_include' => env('DNS_VPS_SPF_INCLUDE', ''),
    ],
],
```

### 3. Use the new service:

```php
// Use default configuration
$validation = $dns->validateServiceConfiguration('example.com');

// Use VPS-specific configuration
$validation = $dns->validateServiceConfiguration('example.com', 'vps');
```

## 📊 Result Interpretation

### Nameservers

- ✅ **Green**: Domain uses expected nameservers
- ⚠️ **Yellow**: Domain doesn't use configured nameservers

### Domain IP

- ✅ **Green**: A record points to one of the valid IPs
- ❌ **Red**: Domain doesn't point to any valid IP

### Mail Server

- ✅ **Green**: MX records point to one of the valid IPs
- ❌ **Red**: MX records don't point to any valid IP

### SPF Record

- ✅ **Green**: SPF record includes expected domain
- ⚠️ **Yellow**: Expected include not found in SPF

## 🐛 Troubleshooting

### Nameservers not detected correctly

The system tries to use `dig` first (more reliable). If not available, it uses `dns_get_record()`.

To install `dig`:
- **macOS**: Pre-installed
- **Ubuntu/Debian**: `sudo apt-get install dnsutils`
- **CentOS/RHEL**: `sudo yum install bind-utils`

### Validations are cached

The system queries authoritative DNS servers directly, but if there's cache:

```bash
# Clear system DNS cache
sudo dscacheutil -flushcache  # macOS
sudo systemd-resolve --flush-caches  # Linux
```

### Errors in logs

Check logs for debugging:

```bash
tail -f storage/logs/laravel.log | grep "DNS"
```

## 📝 Technical Notes

- Uses PHP native `dns_get_record()` (no extensions required)
- Fallback to `dig` command for nameservers (more reliable)
- Cache-free: Queries authoritative servers directly
- Validates IPv4 (IPv6 available but not yet implemented)
- Compatible with internationalized domains (IDN)
