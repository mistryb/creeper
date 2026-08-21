---
paths:
  - app/Creeping/Drivers/LlmCreepDriver.php
---

# Drivers

## Per-user API keys go through a runtime ai.providers entry
laravel/ai has no `->withApiKey()`. To spend an end user's own key, register a named provider in runtime config and pass its name to `prompt(provider: $name)`:

    config(["ai.providers.{$name}" => ['driver' => $lab->value, 'key' => $key]]);
    Ai::forgetInstance($name);   // MultipleInstanceManager memoises by name

This is an undocumented seam in a pre-1.0 package, so it lives in exactly one method (`LlmCreepDriver::register()`) and is torn down in a `finally` — `config()` is dumped by error pages, so a key left there is a leak. `Illuminate\Config\Repository` has no `forget()`; set the key to `null`.

`LlmCreepDriverTest` asserts the key is present during the call and null after. If that test breaks after a package bump, the seam moved — do not weaken the test.
