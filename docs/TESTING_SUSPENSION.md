# Testing Subscription Suspension

This guide explains how to test the complete suspension workflow.

## Overview

The suspension system consists of 4 main actions:

1. **Suspend WHM Account** - Suspends hosting service
2. **Pause Stripe Subscription** - Stops billing attempts
3. **Update Database Status** - Changes status to 'paused'
4. **Send Email Notification** - Notifies customer

## Testing Commands

### 1. Check Subscription Status

First, verify the subscription's current state and what actions should be triggered:

```bash
php artisan subscription:check {id}
```

**Example Output:**
```
📋 Subscription Details:
  Customer: example.com
  Status: active
  Stripe ID: sub_xxxxx
  Auto-suspend: YES

💰 Unpaid Invoices: 2
  - INV-001
    Created: 2025-11-22 10:00
    Days since created: 44
    Amount: 6625.12 ARS

⚠️  Notification Logic (2+ unpaid invoices):
  Oldest invoice: INV-001
  Days since oldest: 44
  → Should send: WARNING 2 DAYS
```

### 2. Force Suspension (Testing)

To manually test the suspension workflow:

```bash
php artisan subscription:force-suspend {id}
```

**Interactive Process:**
1. Shows subscription details
2. Asks for confirmation
3. Executes all 4 suspension steps
4. Shows detailed progress and results

**Example:**
```bash
php artisan subscription:force-suspend 73
```

**Skip Email (for testing):**
```bash
php artisan subscription:force-suspend 73 --skip-email
```

### 3. Automatic Process

The automatic suspension runs via scheduled command:

```bash
php artisan subscriptions:send-notifications
```

**Schedule:** Runs every hour (configured in `bootstrap/app.php`)

## Suspension Logic

### Requirements

A subscription will be suspended when ALL conditions are met:

1. ✅ Status is `active`
2. ✅ Has `auto_suspend` enabled in metadata
3. ✅ Has **2 or more unpaid invoices**
4. ✅ **45+ days** since the oldest invoice was created

### Timeline Example

| Day | Event |
|-----|-------|
| 0 | First invoice created |
| 10 | First invoice due |
| 30 | Second invoice created |
| 40 | Second invoice due |
| 40 | ⚠️ Warning email: "5 days until suspension" |
| 43 | ⚠️ Warning email: "2 days until suspension" |
| 45 | 🚫 **SUSPENSION EXECUTED** |

## What Happens on Suspension?

### 1. WHM Account Suspension
- Account is suspended via WHM API
- Reason: "Automatically suspended: 2 unpaid invoices (45 days since oldest)"
- Website becomes inaccessible
- Email services may be affected (depending on WHM config)

### 2. Stripe Subscription Pause
- Subscription is paused in Stripe
- Behavior: `mark_uncollectible`
- No further billing attempts
- Customer can still view invoices

### 3. Database Update
- Status changes: `active` → `paused`
- Timestamp recorded

### 4. Email Notification
- Customer receives suspension notice
- Includes:
  - Reason for suspension
  - Unpaid invoice details
  - Payment instructions
  - Reactivation process

## Monitoring

### Real-time Logs

```bash
# Watch all suspension events
tail -f storage/logs/laravel.log | grep suspend

# Watch webhook events
tail -f storage/logs/laravel.log | grep webhook

# Watch reactivation events
tail -f storage/logs/laravel.log | grep reactivat
```

### Check Notifications

```bash
# View all notifications for a subscription
php artisan db:table subscription_notifications --where="subscription_id=73"
```

## Reactivation Testing

After suspension, test reactivation:

1. **Pay invoices in Stripe**
2. **Webhook triggers automatic reactivation**
   - Or run manually: `php artisan subscriptions:check-reactivations`

3. **Verify reactivation:**
   ```bash
   php artisan subscription:check 73
   ```

## Troubleshooting

### Suspension Not Triggering?

Check:
```bash
php artisan subscription:check {id}
```

Common issues:
- ❌ `auto_suspend` is disabled
- ❌ Status is not `active`
- ❌ Less than 2 unpaid invoices
- ❌ Not enough days since oldest invoice

### WHM Suspension Failed?

Check logs:
```bash
tail -f storage/logs/laravel.log | grep WHM
```

Common issues:
- ❌ WHM credentials invalid
- ❌ Server unreachable
- ❌ Account doesn't exist

### Stripe Pause Failed?

Check logs:
```bash
tail -f storage/logs/laravel.log | grep Stripe
```

Common issues:
- ❌ Stripe API key invalid
- ❌ Subscription already cancelled
- ❌ Network error

### Email Not Sent?

Check:
```bash
# View notification status
SELECT * FROM subscription_notifications WHERE subscription_id = 73;
```

Status values:
- `pending` - Waiting to be sent
- `sent` - Successfully delivered
- `failed` - Delivery error (check `error_message`)

## Production Use

### Before Running in Production

1. ✅ Test with a real subscription first
2. ✅ Verify email delivery works
3. ✅ Check WHM credentials
4. ✅ Verify Stripe webhook is configured
5. ✅ Monitor logs for first few executions

### Production Command

```bash
# On production server
cd /home/forge/gestion.revisionalpha.com
php artisan subscription:force-suspend {id}
```

### Undo Suspension (Emergency)

If you need to manually reactivate:

```bash
# 1. Update database
php artisan tinker
$sub = Subscription::find({id});
$sub->update(['status' => 'active']);

# 2. Unsuspend WHM manually via cPanel/WHM

# 3. Resume Stripe subscription manually via Dashboard
```

## Safety Notes

⚠️ **Important:**
- `force-suspend` is for **testing only**
- Always verify subscription details before suspension
- Email notifications cannot be unsent
- WHM suspension is immediate
- Stripe pause is immediate

💡 **Best Practice:**
- Test with `--skip-email` flag first
- Verify all systems (WHM, Stripe, Email) before production
- Monitor logs during first executions
- Keep backups of subscription data

