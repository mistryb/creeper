# Creeper

Put a URL into Creeper and it creeps it for you.

Right now that means **product creeping**: Creeper watches a product page,
records what it finds every time it looks, and tells you when the price moves
or the thing comes back in stock.

Creeper is a paid service *and* an app you can run on your own box for free.
It's the same codebase either way — billing is a switch, and it ships off.

---

## How it works

```
       you paste a URL
              │
              ▼
     ┌─────────────────┐   schedule   ┌──────────────────────┐
     │   creep target  │─────────────▶│ creeper:dispatch-due │
     └─────────────────┘              └──────────┬───────────┘
              ▲                                  │ queues
              │ reschedules                      ▼
              │                          ┌───────────────┐
              │                          │   RunCreep    │
              │                          └───────┬───────┘
              │                                  │ asks
              │                                  ▼
              │                          ┌───────────────┐
              │                          │  CreepDriver  │◀── your agent
              │                          └───────┬───────┘
              │                                  │ product data
              │                                  ▼
              │                        ┌────────────────────┐
              └────────────────────────│  CompleteCreepRun  │
                                       └─────────┬──────────┘
                                                 │
                          snapshot ──────────────┼────────────── changes
                                                 │
                                                 ▼
                                            you get an email
```

| Term | What it is |
| --- | --- |
| **Creep target** | A URL you want crept, plus its schedule and settings |
| **Creep run** | One attempt to creep a target — status, timing, errors |
| **Product snapshot** | The structured product data a successful run produced |
| **Creep change** | A field that moved between two consecutive snapshots |
| **Creep driver** | The pluggable thing that actually does the creeping |

Creeper owns everything except the last one. **The creeping itself is yours to
write.**

---

## Running it yourself

Requires PHP 8.3+, Composer, and Node 20+.

```bash
git clone https://github.com/creeper-app/creeper.git
cd creeper
composer setup      # installs, copies .env, generates a key, migrates, builds
php artisan db:seed # optional: three demo targets with price history
composer run dev    # server + queue worker + vite
```

Sign up at <http://localhost:8000/register> and add a target. Out of the box
`CREEP_DRIVER=fake` invents plausible product data, so you can see the whole
thing work before writing an agent.

For the schedule to fire, run Laravel's scheduler:

```
* * * * * cd /path/to/creeper && php artisan schedule:run >> /dev/null 2>&1
```

Everything defaults to SQLite and a database queue, so there's nothing else to
stand up.

---

## Plugging in your creeping agent

An agent only has to turn a URL into product data. There are two ways in.

### The easy way: point Creeper at an HTTP endpoint

```env
CREEP_DRIVER=http
CREEP_AGENT_ENDPOINT=https://my-agent.example.com/creep
CREEP_AGENT_TOKEN=a-secret-you-check
```

Creeper POSTs, with `Authorization: Bearer <token>`:

```json
{
  "run_id": 42,
  "target_id": 7,
  "type": "product",
  "url": "https://shop.example.com/products/kettle",
  "settings": {},
  "callback_url": "https://creeper.example.com/webhooks/creep/42?signature=…"
}
```

Answer in whichever style suits your agent:

**Synchronously** — reply `200` with the product:

```json
{
  "title": "Stainless Kettle",
  "brand": "Acme",
  "sku": "SKU-000123",
  "price": "£24.99",
  "availability": "in stock",
  "rating": 4.5,
  "review_count": 812,
  "image_url": "https://shop.example.com/kettle.jpg"
}
```

**Asynchronously** — reply `202 Accepted` and POST that same body to
`callback_url` when you're done. The signature is the only credential, and it
expires after `CREEP_CALLBACK_TTL` minutes. To report a failure instead:

```json
{ "status": "failed", "error": "The page was a login wall." }
```

Most real agents want the async path — they take minutes, not milliseconds.

#### What Creeper accepts

Creeper is deliberately relaxed about shape, because agents vary:

- **Price** — `price_amount` in minor units, or `price` as a number
  (`24.99`) or a string (`"£24.99"`, `"$1,234.56"`, `"€1.234,56"`).
- **Currency** — a `currency` code, or inferred from the price's symbol.
- **Availability** — `in stock`, `InStock`, `sold out`, `preorder`, `true`,
  `false`, and friends all map onto the four states Creeper knows.
- **Aliases** — `name` for `title`, `manufacturer` for `brand`, `reviews` for
  `review_count`, `image` for `image_url`.
- **Envelopes** — a payload wrapped in `product`, `data`, or `result` is
  unwrapped for you.
- **Anything else** you send is kept verbatim under `extra`.

A payload needs at least a title or a price. Anything less is treated as a
failed run rather than an empty product card.

### The thorough way: write a driver

Implement `App\Creeping\Contracts\CreepDriver`:

```php
interface CreepDriver
{
    public function name(): string;

    public function creep(CreepRun $run): CreepResult;
}
```

Return `CreepResult::succeeded($payload)`, `CreepResult::pending()` if you'll
call the callback later, or `CreepResult::failed($why)` when the target simply
can't be crept. **Throw** for transient problems — the queue retries those.

Register it in `App\Creeping\CreepManager`:

```php
public function createBrowserDriver(): CreepDriver
{
    return new BrowserCreepDriver(config('creeping.drivers.browser'));
}
```

Then set `CREEP_DRIVER=browser`. Nothing else in the application changes.

---

## Running it as a service

```env
BILLING_ENABLED=true
STRIPE_KEY=pk_live_…
STRIPE_SECRET=sk_live_…
STRIPE_WEBHOOK_SECRET=whsec_…
STRIPE_PRICE_PRO=price_…
```

That switch turns on the billing pages, the Stripe checkout flow, and the plan
limits in `config/billing.php`:

```php
'free' => ['targets' => 3,  'min_frequency' => 'daily'],
'pro'  => ['targets' => 50, 'min_frequency' => 'hourly'],
```

Point a Stripe webhook at `/stripe/webhook`. With `BILLING_ENABLED=false` all
of that disappears — the routes 404, the nav item is gone, and nobody has a
limit.

---

## Environment reference

| Variable | Default | What it does |
| --- | --- | --- |
| `CREEP_DRIVER` | `fake` | `fake` or `http`, or one you've registered |
| `CREEP_AGENT_ENDPOINT` | — | Where the `http` driver POSTs |
| `CREEP_AGENT_TOKEN` | — | Sent as a bearer token |
| `CREEP_AGENT_TIMEOUT` | `120` | Seconds to wait for a synchronous answer |
| `CREEP_CALLBACK_TTL` | `180` | Minutes a run's signed callback stays valid |
| `CREEP_RETRIES` | `3` | Attempts before a run is marked failed |
| `BILLING_ENABLED` | `false` | Turns the whole SaaS side on |

---

## A note on URLs

Users paste arbitrary URLs, and a self-hosted agent usually sits inside a
private network. Creeper rejects anything that isn't on the public internet —
loopback, private ranges, link-local, and non-HTTP schemes — before a target is
ever saved. **Your agent should check too.** Creeper can only see the URL at
the moment it's submitted.

---

## Development

```bash
composer test         # pint, phpstan, and the full suite
php artisan test      # just the tests
composer run dev      # server, queue worker, logs, and vite
```

## Licence

MIT.
