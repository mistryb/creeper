# Stripe setup for Creeper

Instructions for setting up Creeper's billing in a Stripe account using the
Stripe CLI. Follow the steps in order — step 4 depends on the ID produced by
step 3, and getting that pair wrong is the one mistake that fails silently.

Every command uses `stripe post` / `stripe get`, which map one-to-one onto the
[Stripe API reference](https://docs.stripe.com/api). If a command is rejected,
check the parameter against the reference rather than guessing a CLI flag.

## What you are building

Creeper charges a flat $7 a month, includes 1,500 checks in that fee, and bills
$0.01 for each check beyond it. That is **one subscription with two items**:

| # | Stripe object | Purpose | Lands in |
|---|---|---|---|
| 1 | Product `Creeper` | The subscription itself | — |
| 2 | Price, $7/month recurring | The flat fee | `STRIPE_PRICE_CREEPER` |
| 3 | Billing meter, event `creep_run` | Counts checks | `STRIPE_METER_EVENT` |
| 4 | Product `Creeper extra checks` | Keeps the overage on its own invoice line | — |
| 5 | Price, metered + graduated tiers | First 1,500 free, then $0.01 each | `STRIPE_PRICE_CREEP_RUN` |
| 6 | Webhook endpoint | Keeps subscription state in sync | `STRIPE_WEBHOOK_SECRET` |
| 7 | Billing portal configuration | Powers "Manage subscription" | — |

**The allowance lives in Stripe, not in the app.** `app/Billing/RunMeter.php`
reports every successful run to the meter and lets the tiered price decide what
is free. That is deliberate: it means a customer's 1,500 always tracks their
real billing period. If you build the tiers wrong, customers get billed from
the first check.

## Before you start

```bash
stripe --version          # install from https://docs.stripe.com/stripe-cli if missing
stripe login              # authorises the CLI against your account
stripe config --list      # confirm which account you are pointed at
```

Everything below runs in **test mode** by default. Do not add `--live` until
you have completed the verification section and are deliberately going to
production. Then repeat every step with `--live` — test and live mode share no
objects, so live needs its own products, prices, meter and webhook.

Keep a scratch file for the IDs you collect. Each step says what to record.

---

## Step 1 — The Creeper product

```bash
stripe post /v1/products \
  -d "name=Creeper" \
  -d "description=Watch any page and get told when something changes."
```

**Expect** a JSON object with `"id": "prod_..."`.
**Record** it as `CREEPER_PRODUCT`.

---

## Step 2 — The $7 monthly price

```bash
stripe post /v1/prices \
  -d "product=$CREEPER_PRODUCT" \
  -d "currency=usd" \
  -d "unit_amount=700" \
  -d "nickname=Creeper monthly" \
  -d "recurring[interval]=month"
```

**Expect** `"id": "price_..."`, `"unit_amount": 700`, `"recurring": {"interval": "month", "usage_type": "licensed"}`.
**Record** it — this is `STRIPE_PRICE_CREEPER`.

---

## Step 3 — The run meter

This must exist before step 4, because the metered price points at it.

```bash
stripe post /v1/billing/meters \
  -d "display_name=Creep runs" \
  -d "event_name=creep_run" \
  -d "default_aggregation[formula]=sum" \
  -d "value_settings[event_payload_key]=value" \
  -d "customer_mapping[type]=by_id" \
  -d "customer_mapping[event_payload_key]=stripe_customer_id"
```

**Expect** `"id": "mtr_..."` and `"status": "active"`.
**Record** it as `CREEPER_METER`.

Three things here are not cosmetic, because Cashier's `reportMeterEvent` sends
exactly this shape and Stripe silently drops events that do not match it:

- `event_name` **must** equal `STRIPE_METER_EVENT` in the app's `.env`
  (`creep_run` by default, see `config/billing.php`).
- `value_settings[event_payload_key]` must be `value`.
- `customer_mapping[event_payload_key]` must be `stripe_customer_id`.

If you change the event name here, change it in `.env` in the same breath.

---

## Step 4 — The overage product

A separate product so the invoice reads "Creeper $7.00" and "Creeper extra
checks $2.30" rather than two lines with the same name.

```bash
stripe post /v1/products \
  -d "name=Creeper extra checks" \
  -d "description=Checks beyond the 1,500 included each month."
```

**Record** the `prod_...` as `OVERAGE_PRODUCT`.

---

## Step 5 — The metered, tiered price

The important step. Graduated tiers: the first 1,500 units of each billing
period cost nothing, everything after costs 1 cent.

```bash
stripe post /v1/prices \
  -d "product=$OVERAGE_PRODUCT" \
  -d "currency=usd" \
  -d "nickname=Creeper extra checks" \
  -d "billing_scheme=tiered" \
  -d "tiers_mode=graduated" \
  -d "recurring[interval]=month" \
  -d "recurring[usage_type]=metered" \
  -d "recurring[meter]=$CREEPER_METER" \
  -d "tiers[0][up_to]=1500" \
  -d "tiers[0][unit_amount]=0" \
  -d "tiers[1][up_to]=inf" \
  -d "tiers[1][unit_amount]=1"
```

**Expect** `"id": "price_..."`, `"billing_scheme": "tiered"`, `"tiers_mode": "graduated"`,
and `recurring.meter` equal to your `mtr_...`.
**Record** it — this is `STRIPE_PRICE_CREEP_RUN`.

Verify the tiers came out right, because a wrong tier bills every customer from
their first check:

```bash
stripe get /v1/prices/$STRIPE_PRICE_CREEP_RUN -d "expand[]=tiers"
```

You want exactly two tiers: `up_to: 1500, unit_amount: 0` then
`up_to: null, unit_amount: 1`. If `tiers` comes back empty, `billing_scheme`
did not take — delete the price and recreate it.

Prices are immutable. To change the allowance or the rate later, create a new
price and point `STRIPE_PRICE_CREEP_RUN` at it; existing subscribers stay on
the old one until you migrate them.

---

## Step 6 — The webhook endpoint

Do **not** hand-roll this. Cashier ships a command that registers the endpoint
with exactly the events it needs:

```bash
php artisan cashier:webhook --url="https://your-domain.com/stripe/webhook"
```

Then read the signing secret, which the command does not print:

```bash
stripe get /v1/webhook_endpoints
```

**Record** the `secret` (`whsec_...`) for the endpoint you just created — this
is `STRIPE_WEBHOOK_SECRET`.

The events Cashier requires, if you ever need to check them by hand:
`customer.subscription.created`, `customer.subscription.updated`,
`customer.subscription.deleted`, `customer.updated`, `customer.deleted`,
`payment_method.automatically_updated`, `invoice.payment_action_required`,
`invoice.payment_succeeded`.

CSRF is already excluded for `stripe/*` in `bootstrap/app.php`, so no change is
needed there.

---

## Step 7 — The billing portal

The "Manage subscription" button calls `redirectToBillingPortal`, which fails
until a portal configuration exists.

```bash
stripe post /v1/billing_portal/configurations \
  -d "business_profile[headline]=Manage your Creeper subscription" \
  -d "features[customer_update][enabled]=true" \
  -d "features[customer_update][allowed_updates][]=email" \
  -d "features[customer_update][allowed_updates][]=address" \
  -d "features[payment_method_update][enabled]=true" \
  -d "features[invoice_history][enabled]=true" \
  -d "features[subscription_cancel][enabled]=true" \
  -d "features[subscription_cancel][mode]=at_period_end"
```

`at_period_end` matters: cancelling immediately would strand a partial month of
metered usage. Cashier's `onGracePeriod` handling assumes period-end anyway.

---

## Step 8 — Wire up the application

Add to `.env`:

```dotenv
BILLING_ENABLED=true

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

STRIPE_PRICE_CREEPER=price_...      # step 2
STRIPE_PRICE_CREEP_RUN=price_...    # step 5
STRIPE_METER_EVENT=creep_run        # must match step 3 exactly

CASHIER_CURRENCY=usd
```

Optional, and worth knowing about:

```dotenv
BILLING_RUN_CEILING=25000       # hard stop on runs per month; 0 disables it
BILLING_REQUIRES_API_KEY=true   # refuse to creep for users with no model key
```

Then confirm the app agrees:

```bash
php artisan config:show billing.plans.creeper
php artisan config:show billing.meter
```

`stripe_price`, `metered_price` and `meter.event` must all be populated and
must match what you recorded. An empty `stripe_price` means the billing page
shows no offer and checkout 404s.

---

## Verification

Work through all five. Steps 1 and 2 catch configuration mistakes; 3 to 5 catch
the wiring mistakes that only show up on an invoice.

**1. The offer appears.** Sign in as a user with no subscription and open
`/settings/billing`. You should see the Creeper plan at $7/month with "1,500
checks included, then $0.01 each".

**2. Checkout builds both items.** Click Subscribe and complete Checkout with
card `4242 4242 4242 4242`. Then:

```bash
stripe get /v1/subscriptions -d "limit=1" -d "expand[][]=data.items"
```

The subscription must have **two** items — your flat price and your metered
price. One item means `metered_price` was empty in config when checkout ran.

**3. Webhooks land.** Locally, run this in a second terminal before checking
out and watch for `customer.subscription.created` returning 200:

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
```

`stripe listen` prints its own `whsec_...`; use that as `STRIPE_WEBHOOK_SECRET`
while developing locally.

**4. Meter events arrive.** Trigger a real creep in the app, then:

```bash
stripe get /v1/billing/meters/$CREEPER_METER/event_summaries \
  -d "customer=cus_..." \
  -d "start_time=$(date -u -v-1d +%s)" \
  -d "end_time=$(date -u +%s)"
```

An `aggregated_value` of at least 1 means the whole chain works:
`CompleteCreepRun` → `ReportCreepRunUsage` → `reportMeterEvent` → Stripe.

Zero means either the queue worker is not running (the job is queued, not
synchronous) or `event_name` does not match `STRIPE_METER_EVENT`. Check the
queue first — it is the more common cause.

**5. The allowance actually holds.** This is the one worth the effort, because
it is the failure that costs customers money. Push the meter past 1,500 for a
test customer:

```bash
for i in $(seq 1 1600); do
  stripe post /v2/billing/meter_events \
    -d "event_name=creep_run" \
    -d "payload[stripe_customer_id]=cus_..." \
    -d "payload[value]=1" \
    -d "identifier=verify-$i"
done
```

Then preview what they would be charged:

```bash
stripe get /v1/invoices/upcoming -d "customer=cus_..."
```

Expect $7.00 plus roughly $1.00 for the ~100 checks past the allowance — not
$16.00. If you see the full 1,600 billed, tier 0 is wrong; go back to step 5.

---

## Going live

Repeat steps 1 to 7 with `--live` on every command, then swap the `.env` values
for the live product's IDs and `sk_live_`/`pk_live_` keys. Test-mode IDs do not
resolve in live mode and will 404 at checkout.

Before you flip it on, confirm in the Dashboard that the live account has:
business details completed, a statement descriptor set (customers dispute
charges they do not recognise), and tax behaviour decided. If you want Stripe
Tax, set `tax_behavior` on both prices at creation — it cannot be changed
afterwards.

---

## When something looks wrong

| Symptom | Cause |
|---|---|
| Billing page shows no plan to buy | `STRIPE_PRICE_CREEPER` empty, or `BILLING_ENABLED=false` |
| Checkout 404s | `STRIPE_PRICE_CREEPER` set to an ID from the other mode |
| Subscription has one item, not two | `STRIPE_PRICE_CREEP_RUN` was empty at checkout time |
| Usage never appears on the meter | Queue worker not running, or `event_name` mismatch |
| `InvalidCustomer` in the logs | A meter event fired for a user with no Stripe customer; `RunMeter::report` guards on `subscribed()`, so investigate rather than patch |
| Every check billed, not just the overage | Tier 0 missing or `billing_scheme` not `tiered` |
| Subscription state stale after checkout | Webhook not reaching the app, or wrong signing secret |
