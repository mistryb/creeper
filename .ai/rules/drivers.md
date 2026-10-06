---
paths:
  - app/Creeping/Drivers/LlmCreepDriver.php
  - app/Ai/UserKeyProvider.php
  - app/Actions/RunBusinessAnalysis.php
---

# Drivers

## User API keys go through a runtime ai.providers entry
Every model call spends a key from the user's keyring, never anything from config or the environment: a creep spends `$run->watchedPage->apiKey`, a business analysis spends the key picked when it was started (`business_analyses.api_key_id`). A run with no key fails before anything is fetched.

laravel/ai has no `->withApiKey()`. To spend an end user's own key, register a named provider in runtime config and pass its name to `prompt(provider: $name)`:

    config(["ai.providers.{$name}" => ['driver' => $lab->value, 'key' => $key]]);
    Ai::forgetInstance($name);   // MultipleInstanceManager memoises by name

This is an undocumented seam in a pre-1.0 package, so it lives in exactly one place — `App\Ai\UserKeyProvider::using()`, which every billed call goes through — and is torn down in a `finally` — `config()` is dumped by error pages, so a key left there is a leak. `Illuminate\Config\Repository` has no `forget()`; set the key to `null`.

The instance is named `creep_key_{$apiKey->id}`, so two calls on two different keys cannot share a memoised provider. `LlmCreepDriverTest` and `BusinessAnalysisTest` assert the key is present during the call and null after. If that test breaks after a package bump, the seam moved — do not weaken the test.
