# 🔄 Automatic Subscription Reactivation System

## 📋 Description

This system allows **automatic reactivation** of suspended subscriptions when the customer pays their pending invoices.

### **Complete Flow:**

1. **Suspension (Day 45)**:
   - Customer has 2 unpaid invoices
   - WHM account is suspended
   - Subscription is paused in Stripe (`pause_collection`)
   - Database status: `paused`

2. **Invoice Payment**:
   - Customer pays 1 or both invoices
   - **Webhook** receives `invoice.payment_succeeded` event
   - System checks if they still have 2+ unpaid invoices

3. **Automatic Reactivation** (if < 2 unpaid invoices):
   - WHM account is reactivated (unsuspend)
   - Subscription is resumed in Stripe
   - Database status: `active`
   - Reactivation email is sent

---

## ⚙️ Configuration

### **1. Configure Webhook Secret in Stripe**

1. Go to Stripe Dashboard: https://dashboard.stripe.com/webhooks
2. Create a new webhook endpoint with this URL:
   ```
   https://gestion.revisionalpha.com/stripe/webhook
   ```

3. Select the following events:
   - ✅ `invoice.payment_succeeded` - **Detects payments and reactivates**
   - ✅ `invoice.payment_failed` - Updates invoice status
   - ✅ `customer.subscription.created` - New subscriptions
   - ✅ `customer.subscription.updated` - **Detects pauses/resumptions** (via `pause_collection`)
   - ✅ `customer.subscription.deleted` - Cancellations

> **Note:** Stripe does NOT have `paused` or `resumed` events. Pauses/resumptions are detected in `subscription.updated` via the `pause_collection` field.

4. Copy the **Signing secret** (starts with `whsec_`)

### **2. Add to `.env` file:**

```env
STRIPE_KEY=pk_live_xxxxx
STRIPE_SECRET=sk_live_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxxx
```

### **3. Verify configuration in `config/services.php`:**

```php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

---

## 🧪 Testing the Webhook

### **Option 1: Use Stripe CLI (development)**

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Forward events to local webhook
stripe listen --forward-to https://stripe.test/stripe/webhook

# Simulate invoice payment
stripe trigger invoice.payment_succeeded
```

### **Option 2: Test in production**

```bash
# View webhook logs
tail -f storage/logs/laravel.log | grep "Stripe webhook"
```

---

## 📅 Available Commands

### **Send notifications and suspend services**
```bash
php artisan subscriptions:send-notifications
```
- Runs daily at 9:00 AM
- Sends 5-day and 2-day warnings
- Suspends services on day 45

### **Check and reactivate subscriptions**
```bash
php artisan subscriptions:check-reactivations
```
- Runs every 2 hours (webhook backup)
- Searches for suspended subscriptions that paid
- Automatically reactivates if they have < 2 unpaid invoices

### **Test notification logic**
```bash
php artisan test:notification-logic {subscription_id}
```

### **Create test invoices**
```bash
php artisan test:create-invoices {subscription_id}
```

### **Clean test invoices**
```bash
php artisan test:clean-invoices
```

### **Test webhook configuration**
```bash
php artisan stripe:test-webhook --check
```

---

## 🔍 Logs and Monitoring

### **View real-time logs:**

```bash
# All subscription events
tail -f storage/logs/laravel.log | grep -E "subscription|webhook|reactivat"

# Webhooks only
tail -f storage/logs/laravel.log | grep "Stripe webhook"

# Reactivations only
tail -f storage/logs/laravel.log | grep "reactivat"
```

### **Logged events:**

- ✅ Webhook received
- ✅ Invoice paid
- ✅ Unpaid invoice count
- ✅ Reactivation decision
- ✅ WHM reactivated
- ✅ Stripe resumed
- ✅ Email sent

---

## 🚨 Troubleshooting

### **Webhook not executing:**

1. Verify webhook is active in Stripe
2. Verify `STRIPE_WEBHOOK_SECRET` is in `.env`
3. Check logs: `tail -f storage/logs/laravel.log | grep webhook`

### **Not automatically reactivating:**

1. Verify subscription status is `paused`
2. Verify it has < 2 unpaid invoices:
   ```bash
   php artisan test:notification-logic {subscription_id}
   ```
3. Run manually:
   ```bash
   php artisan subscriptions:check-reactivations
   ```

### **Error pausing/resuming in Stripe:**

- Verify `STRIPE_SECRET` has permissions
- Check logs for specific error
- Test manually in Stripe Dashboard

---

## 📊 Subscription States

| Status | Description | Actions |
|--------|-------------|---------|
| `active` | Active and up to date | None |
| `past_due` | With overdue invoices | 5-day and 2-day warnings |
| `paused` | Suspended (2 unpaid invoices) | WHM suspended, Stripe paused |
| `canceled` | Manually canceled | Not reactivated |

---

## 🔐 Security

- ✅ Webhook validates Stripe signature
- ✅ Excluded from CSRF protection
- ✅ Only accepts Stripe-signed events
- ✅ Detailed logs of all events

---

## 📝 Important Notes

1. **Webhook backup**: The `check-reactivations` command runs every 2 hours as a backup in case the webhook fails.

2. **Immediate reactivation**: With the webhook, reactivation is **immediate** upon payment.

3. **Stripe synchronization**: Status is automatically updated in both Stripe and WHM.

4. **Notifications**: Customer receives an email confirming reactivation.

5. **Auto-suspension required**: Only subscriptions with `auto_suspend: true` in metadata are automatically suspended/reactivated.
