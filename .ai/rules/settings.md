---
paths:
  - 'app/Models/ApiKey.php,app/Http/Controllers/Settings/ApiKeyController.php'
---

# Settings

## API keys live on a user keyring, never in the environment
Every model API key is a row in `api_keys` (encrypted `key`, plus a four-character `hint` and the provider it belongs to). There is deliberately no `DIGEST_API_KEY` / provider config or env fallback — do not add one back, and do not put a key in `config/digest.php`, because config is dumped by error pages.

A user may keep several keys, and each company names the one it spends (`businesses.api_key_id`). Setting a company up requires a key, because discovery runs straight away; updating one is `sometimes`, so a form that omits it leaves the company on the key it has.

Deleting a key nulls `api_key_id` on its companies (`nullOnDelete`). `ApiKeyController::destroy` only counts them for the toast: a keyless company's digest is still queued on schedule and fails once, saying so, and the schedule moves on — so the owner finds out by email rather than by nothing arriving.
