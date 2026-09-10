---
paths:
  - 'app/Models/ApiKey.php,app/Http/Controllers/Settings/ApiKeyController.php'
---

# Settings

## API keys live on a user keyring, never in the environment
Every model API key is a row in `api_keys` (encrypted `key`, plus a four-character `hint` and the provider it belongs to). There is deliberately no `CREEP_LLM_API_KEY` / `CREEP_LLM_PROVIDER` config or env fallback — do not add one back, and do not put a key in `config/creeping.php`, because config is dumped by error pages.

A user may keep several keys, and each `creep_target` names the one it spends (`creep_targets.api_key_id`). Creating a target requires a key; updating one is `sometimes`, so a request that omits it leaves the target where it is.

Deleting a key nulls `api_key_id` on its targets (`nullOnDelete`) and `ApiKeyController::destroy` pauses the Active ones — otherwise every run would fail until a new key was picked, and three failures park a target for good. Already-parked targets are left alone.
