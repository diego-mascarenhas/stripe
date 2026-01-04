# WHM/cPanel Integration

## 📋 Configuration

### Environment Variables

Add the following variables to your `.env` file:

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

### Server Configuration

1. **WHM Access**: Make sure you have **reseller** credentials with permissions for:
   - Suspend accounts (`suspendacct`)
   - Unsuspend accounts (`unsuspendacct`)
   - List accounts (`listaccts`)
   - View account information (`accountsummary`)

2. **Firewall**: Server must allow HTTPS connections to WHM port **2087**.

3. **SSL**: If your WHM servers use self-signed certificates, configure `WHM_VERIFY_SSL=false` (not recommended in production).

## 🚀 Usage

### Automatic Synchronization

The system automatically synchronizes accounts when a subscription status changes, **only if**:
- Subscription is type **"sell"**
- Has **`auto_suspend`** enabled in metadata
- Has **`server`** and **`user`** configured in metadata

**States that suspend:**
- `canceled`
- `past_due`
- `unpaid`
- `incomplete_expired`

**States that reactivate:**
- `active`

### Manual Synchronization

#### Sync all subscriptions:
```bash
php artisan subscriptions:sync-whm
```

#### Sync specific subscription:
```bash
php artisan subscriptions:sync-whm --subscription=123
```

### Programmatic Usage

```php
use App\Actions\Subscriptions\SyncSubscriptionWithWHM;

$subscription = Subscription::find(123);

app(SyncSubscriptionWithWHM::class)->handle($subscription);
```

## 📊 Required Metadata

For synchronization to work, the subscription must have in `data`:

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

## 🔍 Monitoring

All events are logged in Laravel logs:

```bash
# View real-time logs
tail -f storage/logs/laravel.log | grep WHM
```

**Logged events:**
- ✅ Successful suspensions
- ✅ Successful reactivations
- ⚠️ Connection errors
- ⚠️ Accounts without complete metadata
- ℹ️ Subscription status changes

## 🛠️ Available Methods

### WHMServerManager

```php
use App\Services\WHM\WHMServerManager;

$whm = app(WHMServerManager::class);

// Suspend account
$whm->suspendAccount('server.example.com', 'username', 'Payment overdue');

// Unsuspend account
$whm->unsuspendAccount('server.example.com', 'username');

// Get account info
$info = $whm->getAccountInfo('server.example.com', 'username');

// List all accounts on a server
$accounts = $whm->listAccounts('server.example.com');

// Create new account
$whm->createAccount([
    'server' => 'server.example.com',
    'username' => 'newuser',
    'domain' => 'example.com',
    'email' => 'user@example.com',
    'plan' => 'beginner',
    'password' => 'secure_password',
]);
```

## 🔐 Security

1. **Credentials**: WHM credentials are stored in `.env` and are not committed to the repository.

2. **SSL/TLS**: By default, all connections use HTTPS with SSL verification.

3. **Logs**: All errors and actions are logged with complete information for auditing.

4. **Permissions**: The reseller user should have **only** the necessary permissions for required operations.

## 🐛 Troubleshooting

### Error: "Connection timeout"
- Verify that the WHM server is accessible from your application
- Review firewall rules
- Increase `WHM_TIMEOUT` in `.env`

### Error: "Authentication failed"
- Verify that `WHM_USERNAME` and `WHM_PASSWORD` are correct
- Confirm that the user has reseller permissions

### Not automatically suspending
- Verify that `auto_suspend` is set to `true` in metadata
- Confirm that subscription is type `sell`
- Check logs: `tail -f storage/logs/laravel.log`

## 📚 WHM API Documentation

- [WHM API 1 - suspendacct](https://api.docs.cpanel.net/openapi/whm/operation/suspendacct/)
- [WHM API 1 - unsuspendacct](https://api.docs.cpanel.net/openapi/whm/operation/unsuspendacct/)
- [WHM API 1 - accountsummary](https://api.docs.cpanel.net/openapi/whm/operation/accountsummary/)
- [WHM API Authentication](https://docs.cpanel.net/knowledge-base/web-services/how-to-use-cpanel-api-tokens/)
