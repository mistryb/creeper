---
paths:
  - app/Models/User.php
---

# Models

## Billing is on only when Stripe is configured; self-hosted must stay limitless
`config('billing.enabled')` is true only when STRIPE_SECRET, STRIPE_PRICE_SINGLE and STRIPE_PRICE_UNLIMITED are all set. Otherwise the install is self-hosted: `User::businessLimit()` returns null, no trial starts, the shared `billing` prop is null, and every BillingController action 404s (checked per request, never around route registration, because of route:cache).

Entitlement lives in one place: `User::businessLimit()` (null = unlimited, 0 = must pick a plan). Order: self-hosted → comped (`comped_at`) → plan (Plan enum, by Stripe price) → generic trial (1) → 0. Use `canAddBusiness()` for creating and `covers($business)` before spending anything (discovery, digests). Over-limit accounts keep their companies; only the earliest-created ones stay covered, and uncovered due companies are left due, not advanced, so the missed digest goes out once paid. Plan prices shown in the app live in `App\Enums\Plan` and must match the Stripe prices. phpunit.xml blanks the STRIPE_* vars so a local .env never turns billing on in tests; use `enableBilling()` / `subscribe()` from tests/Pest.php.
