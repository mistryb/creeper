# Creeper

Creeper watches your competitors for you.

Describe your business, add the competitors you are up against, and point
Creeper at their pages — a pricing page, a changelog, a home page — and describe
in your own words what to watch for on each. It reads each one on a schedule,
records what it finds as a summary and a list of named facts, and tells you when
a fact appears, disappears or changes value. It can also analyse your business and each competitor: what
they sell, who to, how they stand apart, and where they are exposed.

Creeper is an app you run yourself — on your own box, or on Laravel Cloud.
There are no plans, no limits and no billing: you bring a model API key and it
costs you whatever your provider charges.

---

## How it works

```
  a competitor's page URL
              │
              ▼
     ┌─────────────────┐   schedule   ┌──────────────────────┐
     │  watched page   │─────────────▶│ creeper:dispatch-due │
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
              │                                  │ summary + facts
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
| **Business** | Yours, described in your own words — what everything is watched for |
| **Competitor** | A company your business is up against |
| **Watched page** | One of a competitor's URLs, plus its schedule and settings |
| **Business analysis** | A model's write-up of a business or competitor, one row per run |
| **Creep run** | One attempt to creep a watched page — status, timing, errors |
| **Page snapshot** | One reading: a summary and a list of named facts (`label` → `value`) |
| **Creep change** | A fact that appeared, disappeared or changed value between two readings |
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
php artisan db:seed # optional: a demo business with two competitors and their history
composer run dev    # server + queue worker + vite
```

Sign in at <http://localhost:8000/login>, set up your business, and add a
competitor. There are no
passwords: you enter an email address, Creeper emails a six digit code, and
typing it back signs you in — creating the account if the address is new.

`composer run dev` starts [Mailpit](https://mailpit.axllent.org) alongside the
server if it is installed (`brew install mailpit`), so the code is waiting at
<http://localhost:8025>. Without it, set `MAIL_MAILER=log` and read the code out
of `storage/logs/laravel.log`.

Out of the box `CREEP_DRIVER=llm` reads pages with a model, so it needs a key.
Keys live in the app, never in your environment: add one under Settings → API
keys, and pick which key a watched page spends when you create it. Keep as many as
you like. Without one, runs fail and say so.

To look around before committing to a provider, set `CREEP_DRIVER=fake`. It
invents plausible readings locally and needs no key at all.

For the schedule to fire, run Laravel's scheduler:

```
* * * * * cd /path/to/creeper && php artisan schedule:run >> /dev/null 2>&1
```

Everything defaults to SQLite and a database queue, so there's nothing else to
stand up.

---

## Running it on Laravel Cloud

Creeper is an ordinary Laravel app, so [Laravel Cloud](https://cloud.laravel.com)
runs it as it is — no container to build, and nothing to keep alive yourself.
If you keep a terminal open for an agent, the `/deploy` page on a marketing-mode
install hands it a prompt that does all of this. By hand it is six decisions.

### Ship it

```bash
composer global require laravel/cloud-cli
cloud auth -n
cloud ship -n      # read `cloud ship -h` first and pass every option explicitly
```

Build with `npm ci && npm run build`, and deploy with
`php artisan migrate --force`. If you turn on the App cluster's *Use Inertia
SSR* toggle, build with `npm run build:ssr` instead.

### A Postgres database

Cloud doesn't run SQLite, so attach a Serverless Postgres database. Attaching
it injects `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`; set
`DB_CONNECTION=pgsql` yourself. Sessions, the cache and the queue all live in
that same database, so it stays the only thing you stand up.

### The scheduler

Click the App compute cluster on the environment's canvas, enable the
**Scheduler** toggle, then save and redeploy. That is the crontab line above:
Cloud runs `schedule:run` every minute for you. Without it nothing ever comes
due and nothing is ever crept.

Cloud wakes a sleeping environment to run scheduled tasks, and it then stays
awake for the length of the sleep timeout. Creeper looks for due watched pages every
minute, so don't expect Scale to Zero to save you anything here — it will
essentially never get to sleep.

### A queue worker

The creeping itself happens on the queue, so something has to be running
`queue:work` or watched pages come due and nobody picks them up. Two ways:

**A background process on the app cluster.** Under the App cluster's
**Background processes**, add a `queue:work` process. Keep
`QUEUE_CONNECTION=database`, and keep `DB_QUEUE_RETRY_AFTER` above
`RunCreep::$timeout` — a run whose reservation lapses while it is still going
is handed to a second worker, which means creeping the page and paying for the
inference twice.

**A managed queue.** Deploying one sets `QUEUE_CONNECTION=cloud`, after which
`DB_QUEUE_RETRY_AFTER` stops meaning anything: Cloud extends a job's visibility
for as long as it runs. It wants `aws/aws-sdk-php` required in `composer.json`,
which today it only is indirectly. Mind the compute class — a Flex worker gives
a job 90 seconds, which the `llm` driver fits inside with
`CREEP_LLM_BUDGET=70`, but a synchronous `http` agent on the default
`CREEP_AGENT_TIMEOUT=120` does not. Answer `202` and use the callback, or put
the queue on Pro.

### Mail, or nobody can sign in

Signing in means receiving a six digit code, so an install whose mail doesn't
work is an install nobody can get into — and there is no Mailpit on Cloud:

```env
MAIL_MAILER=resend
RESEND_API_KEY=re_...
MAIL_FROM_ADDRESS=creeper@your-domain.example
```

### The rest of the environment

Set the rest with `cloud environment:variables -n --force`:

```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
AUTHORIZED_EMAILS=you@example.com   # who is allowed an account, comma separated
MARKETING_MODE=false                # "/" is the sign-in page
CREEP_DRIVER=llm
```

Adding somebody later is an edit to `AUTHORIZED_EMAILS` and another deploy;
there is no invite flow, deliberately. Note what isn't there: no model API key.
Keys are added in the app, so the first thing to do once you're signed in is
put one on your keyring under Settings → API keys. Nothing can be crept
without one.

---

## Plugging in your creeping agent

An agent only has to read a URL for what the user asked to watch, and report
it as a summary and a list of facts. There are two ways in.

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
  "watched_page_id": 7,
  "target_id": 7,
  "url": "https://competitor.example.com/pricing",
  "watch_for": "Each plan's name and price, and any plan added or removed.",
  "settings": {},
  "callback_url": "https://creeper.example.com/webhooks/creep/42?signature=…"
}
```

`target_id` is the same value as `watched_page_id`, kept for agents written
before watched pages replaced creep targets. Read `watched_page_id` in anything
new.

Answer in whichever style suits your agent:

**Synchronously** — reply `200` with the reading:

```json
{
  "summary": "Three plans; Pro went up to $25 a month.",
  "facts": [
    { "label": "Hobby plan", "value": "Free" },
    { "label": "Pro plan", "value": "$25/user/month" },
    { "label": "Enterprise plan", "value": "Contact sales" }
  ]
}
```

Labels are how changes are found: Creeper lines up two readings by label, so
use the same label for the same thing every time.

**Asynchronously** — reply `202 Accepted` and POST that same body to
`callback_url` when you're done. The signature is the only credential, and it
expires after `CREEP_CALLBACK_TTL` minutes. To report a failure instead:

```json
{ "status": "failed", "error": "The page was a login wall." }
```

Most real agents want the async path — they take minutes, not milliseconds.

#### What Creeper accepts

- **`summary`** — required, a sentence or three.
- **`facts`** — a list of `{ "label", "value" }`, or a plain
  `{ "label": "value" }` map. Up to 40. Blank ones are dropped, and a label
  that appears twice is kept once. An empty list is a valid reading: it means
  nothing on the page matched what was asked.
- **Envelopes** — a payload wrapped in `reading`, `data`, or `result` is
  unwrapped for you.
- **Anything else** you send is kept verbatim under `extra`.

Labels are compared ignoring case, spacing and trailing punctuation, and values
ignoring case and spacing, so reformatting is not reported as a change.

### The middle way: let Creeper read the page itself

This is the default. Add a key under Settings → API keys, choose it when you
create a watched page, and there is nothing else to do:

```env
CREEP_DRIVER=llm
```

Three steps per run, all in-process:

1. **Fetch** the page — re-checking the address, and every redirect, against
   the same public-internet rule that guarded it at submission.
2. **Reduce** it to the parts worth paying for: any schema.org data about what
   the company sells, the page's metadata, and the visible text, in that order.
3. **Read** it once with a structured-output call, given the user's own
   description of what to watch for and the labels it used last time, which
   returns a summary and a list of facts and nothing else.

Every key is one a user added in the app, and each watched page names the key it
spends, so people pay their model provider directly and can keep separate keys
for separate budgets. The provider travels with the key. There is deliberately
no key in the environment: nothing to paste into a deployment, and nothing for
an error page's config dump to leak. The key is put into the AI SDK's
configuration for exactly the length of one call and removed again.

Removing a key pauses the watched pages that were being crept with it.

**It never runs a browser.** A page that assembles itself in JavaScript will
come back thin, and Creeper will record the run as failed rather than store an
empty reading. Most marketing, pricing and changelog pages render on the server
and work well. If yours doesn't, write a driver.

`CREEP_LLM_MAX_CHARACTERS` is the cost dial: it caps how much page the model is
asked to read, and so what each run costs. An hourly watched page is roughly 720 runs
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
call the callback later, or `CreepResult::failed($why)` when the watched page simply
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

## Environment reference

| Variable | Default | What it does |
| --- | --- | --- |
| `CREEP_DRIVER` | `llm` | `llm`, `fake` or `http`, or one you've registered |
| `CREEP_AGENT_ENDPOINT` | — | Where the `http` driver POSTs |
| `CREEP_AGENT_TOKEN` | — | Sent as a bearer token |
| `CREEP_AGENT_TIMEOUT` | `120` | Seconds to wait for a synchronous answer |
| `CREEP_LLM_MODEL` | — | Blank takes the provider's own default |
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

---

## A note on URLs

Users paste arbitrary URLs, and a self-hosted agent usually sits inside a
private network. Creeper rejects anything that isn't on the public internet —
loopback, private ranges, link-local, and non-HTTP schemes — before a watched page is
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
