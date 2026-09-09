---
paths:
  - app/Http/Controllers/HomeController.php
  - app/Http/Controllers/DeployController.php
---

# Controllers

## Read marketing mode per request, not at route registration
`MARKETING_MODE` (config `marketing.enabled`, off by default) decides what "/" serves: the `welcome` landing page when on, a redirect to `route('login')` when off.

Branch on it inside `HomeController`, never around the `Route::get('/')` definition. `route:cache` runs at deploy time, so a config check during route registration is baked into the cached route table and the environment variable silently stops meaning anything.

Signed-in visitors are not special-cased: the `guest` middleware on the sign-in page forwards them to the dashboard.

## Public marketing pages are gated on marketing mode per request
`/deploy` is marketing collateral, so it checks `config('marketing.enabled')` inside the controller — `abort_unless(..., 404)` — the same per-request way HomeController does. Never branch on the flag around the `Route::get()` definition: `route:cache` bakes it into the cached route table at deploy time and `MARKETING_MODE` silently stops meaning anything.

Home redirects to sign-in when the flag is off; deploy 404s instead. A private install has users, so it still needs a front door, but it has nobody to pitch a deployment to.
