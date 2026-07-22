# 🔄 How Stripe Handles Subscription Pauses

## ❌ **What Does NOT Exist:**

Stripe does **NOT have** these events:
- ❌ `customer.subscription.paused`
- ❌ `customer.subscription.resumed`

---

## ✅ **What DOES Exist:**

### **Single Event: `customer.subscription.updated`**

When you pause or resume a subscription, Stripe sends `customer.subscription.updated` with the `pause_collection` field:

### **Paused Subscription:**
```json
{
  "type": "customer.subscription.updated",
  "data": {
    "object": {
      "id": "sub_xxx",
      "status": "active",  // ⚠️ Status remains "active"
      "pause_collection": {
        "behavior": "mark_uncollectible"
      }
    }
  }
}
```

### **Resumed Subscription:**
```json
{
  "type": "customer.subscription.updated",
  "data": {
    "object": {
      "id": "sub_xxx",
      "status": "active",
      "pause_collection": null  // ✅ No longer has pause_collection
    }
  }
}
```

---

## 🔍 **How We Detect Pauses:**

In `StripeWebhookController.php`:

```php
protected function handleCustomerSubscriptionUpdated(array $subscription)
{
    $pauseCollection = $subscription['pause_collection'] ?? null;
    
    if ($pauseCollection !== null) {
        // 🚨 IS PAUSED
        $actualStatus = 'paused';
        Log::info('Stripe webhook: Subscription is PAUSED');
        
    } else {
        // ✅ NOT PAUSED (active or resumed)
        if ($previousStatus === 'paused' && $status === 'active') {
            Log::info('Stripe webhook: Subscription RESUMED from pause');
            // Check if it should be reactivated
        }
    }
}
```

---

## 📊 **Stripe States vs Our Database:**

| Campo | Uso |
|-------|-----|
| `stripe_status` | Espejo de Stripe (`active`, `past_due`, `unpaid`, `canceled`, …). Se actualiza por sync y webhooks. |
| `status` | Operativo / servicio: `active` (cPanel activo) o `paused` (suspendido día 45). |

| Stripe `status` | Stripe `pause_collection` | `stripe_status` | `status` (servicio) |
|-----------------|---------------------------|-----------------|---------------------|
| `active` | `null` | `active` | `active` o `paused` |
| `active` | `{ behavior: "mark_uncollectible" }` | `active` | Suele ser `paused` tras día 45 |
| `past_due` / `unpaid` | `null` | `past_due` / `unpaid` | Puede seguir `active` o `paused` |
| `canceled` | `null` | `canceled` | Independiente del servicio |

---

## 🎯 **Pause Behaviors:**

Stripe offers 3 options when pausing:

1. **`keep_as_draft`** - Invoices are saved as drafts
2. **`mark_uncollectible`** - Invoices are marked as uncollectible (we use this)
3. **`void`** - Invoices are voided

We use **`mark_uncollectible`** because:
- ✅ Doesn't attempt to charge while paused
- ✅ Invoices still exist
- ✅ Can be easily resumed

---

## 🧪 **Testing the System:**

### **1. Pause subscription manually:**

```php
$stripe->subscriptions->update(
    'sub_xxx',
    [
        'pause_collection' => [
            'behavior' => 'mark_uncollectible',
        ],
    ]
);
```

**Webhook received:**
```
Stripe webhook: Subscription updated
  subscription_id: sub_xxx
  status: active
  pause_collection: paused
  
Stripe webhook: Subscription is PAUSED
  subscription_id: 123
  pause_behavior: mark_uncollectible
```

### **2. Resume subscription:**

```php
$stripe->subscriptions->update(
    'sub_xxx',
    ['pause_collection' => null]
);
```

**Webhook received:**
```
Stripe webhook: Subscription updated
  subscription_id: sub_xxx
  status: active
  pause_collection: active
  
Stripe webhook: Subscription RESUMED from pause
  subscription_id: 123
```

---

## 📝 **Configured Events:**

```
✅ invoice.payment_succeeded
   └─ Detects payments and automatically reactivates

✅ customer.subscription.updated
   └─ Detects pauses (pause_collection !== null)
   └─ Detects reactivations (pause_collection === null)

✅ customer.subscription.deleted
   └─ Marks as canceled
```

---

## 🔗 **References:**

- [Stripe: Pause subscriptions](https://stripe.com/docs/billing/subscriptions/pause)
- [Stripe: Subscription object](https://stripe.com/docs/api/subscriptions/object)
- [Stripe: Webhooks](https://stripe.com/docs/webhooks)

---

## ✅ **Summary:**

- ❌ There are NO `paused` or `resumed` events in Stripe
- ✅ Everything is handled in `customer.subscription.updated`
- ✅ We detect pauses via the `pause_collection` field
- ✅ Our webhook is already updated to handle this correctly
