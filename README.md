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

Sign in at <http://localhost:8000/login> and add a target. There are no
passwords: you enter an email address, Creeper emails a six digit code, and
typing it back signs you in — creating the account if the address is new.

`composer run dev` starts [Mailpit](https://mailpit.axllent.org) alongside the
server if it is installed (`brew install mailpit`), so the code is waiting at
<http://localhost:8025>. Without it, set `MAIL_MAILER=log` and read the code out
of `storage/logs/laravel.log`.

Out of the box `CREEP_DRIVER=llm` reads pages with a model, so it needs a key —
either `CREEP_LLM_API_KEY` in your `.env`, or one you add under Settings → API
key. Without one, runs fail and say so.

To look around before committing to a provider, set `CREEP_DRIVER=fake`. It
invents plausible product data locally and needs no key at all.

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

### The middle way: let Creeper read the page itself

This is the default. Set a key and there is nothing else to do:

```env
CREEP_DRIVER=llm
CREEP_LLM_PROVIDER=anthropic
CREEP_LLM_API_KEY=sk-ant-…
```

Three steps per run, all in-process:

1. **Fetch** the page — re-checking the address, and every redirect, against
   the same public-internet rule that guarded it at submission.
2. **Reduce** it to the parts worth paying for: any schema.org `Product` block,
   the `og:`/`product:` metadata, and the visible text, in that order.
3. **Read** it once with a structured-output call, which returns the product
   fields and nothing else.

Each user's own key is used when they have added one, along with the provider
they picked with it, so they pay their model provider directly.
`CREEP_LLM_API_KEY` is the fallback for a self-hosted install.

**It never runs a browser.** A shop that assembles its price in JavaScript will
come back thin, and Creeper will record the run as failed rather than store an
empty product card. Pages that publish schema.org data — most of them — work
well. If yours doesn't, write a driver.

`CREEP_LLM_MAX_CHARACTERS` is the cost dial: it caps how much page the model is
asked to read, and so what each run costs. An hourly target is roughly 720 runs
a month, so it is worth setting deliberately.

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
| `CREEP_DRIVER` | `llm` | `llm`, `fake` or `http`, or one you've registered |
| `CREEP_AGENT_ENDPOINT` | — | Where the `http` driver POSTs |
| `CREEP_AGENT_TOKEN` | — | Sent as a bearer token |
| `CREEP_AGENT_TIMEOUT` | `120` | Seconds to wait for a synchronous answer |
| `CREEP_LLM_PROVIDER` | `anthropic` | `anthropic`, `openai`, `gemini`, `groq` or `openrouter` |
| `CREEP_LLM_MODEL` | — | Blank takes the provider's own default |
| `CREEP_LLM_API_KEY` | — | Fallback key, for when a user hasn't added their own |
| `CREEP_LLM_MAX_CHARACTERS` | `12000` | How much page the model reads — the cost dial |
| `CREEP_LLM_BUDGET` | `70` | Seconds for the whole driver, fetch and inference |
| `CREEP_LLM_TIMEOUT` | `45` | Seconds for the model call alone |
| `CREEP_LLM_FETCH_TIMEOUT` | `15` | Seconds to fetch the page, across all redirects |
| `CREEP_LLM_MAX_BYTES` | `2097152` | Most of a response Creeper will read |
| `CREEP_LLM_MAX_REDIRECTS` | `3` | Redirects followed before giving up |
| `CREEP_LLM_PIN_ADDRESS` | `true` | Connect only to the address that was checked |
| `CREEP_CALLBACK_TTL` | `180` | Minutes a run's signed callback stays valid |
| `CREEP_RETRIES` | `3` | Attempts before a run is marked failed |
| `DB_QUEUE_RETRY_AFTER` | `330` | Must exceed the longest job timeout |
| `BILLING_ENABLED` | `false` | Turns the whole SaaS side on |

---

## A note on URLs

Users paste arbitrary URLs, and a self-hosted agent usually sits inside a
private network. Creeper rejects anything that isn't on the public internet —
loopback, private ranges, link-local, and non-HTTP schemes — before a target is
ever saved.

That check alone isn't enough, because a hostname can resolve somewhere else
later. So the `llm` driver, which fetches pages itself, checks again at fetch
time: it re-runs the rule, resolves the host over both IPv4 and IPv6, and then
pins the connection to the exact address it validated, closing the window where
DNS could change underneath it. Redirects are followed by hand, one hop at a
time, through that same check — a `302` pointing at `169.254.169.254` gets no
further than the guard.

**If you write your own driver, do the same.** `App\Rules\PublicUrl::permits()`
is the one definition of somewhere Creeper is willing to go.

---

## Development

```bash
composer test         # pint, phpstan, and the full suite
php artisan test      # just the tests
composer run dev      # server, queue worker, logs, and vite
```

## Licence

MIT.
