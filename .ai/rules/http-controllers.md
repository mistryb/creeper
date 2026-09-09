---
paths:
  - 'app/Http/Controllers/CreepTargetPauseController.php,app/Http/Controllers/CreepTargetController.php,app/Http/Controllers/CreepRunController.php'
---

# Http Controllers

## Pausing is its own endpoint, not a field on the settings form
Stopping a target without deleting it goes through CreepTargetPauseController (POST/DELETE `creep-targets/{creep_target}/pause`), which calls `CreepTarget::pause()` / `resume()`. Keep the transition arithmetic in those two model methods so the one-click path and the settings form cannot drift.

`status` on UpdateCreepTargetRequest is `sometimes`, not `required`: the settings form no longer submits it, and an omitted status must leave the target where it is. Do not make it required again without giving the form a status field back.

`update()` deliberately does not reuse `pause()`: a parked (Failed) target saving its settings must stay Failed, and `pause()` would flatten it to Paused.

`resume()` clears `consecutive_failures`, so it doubles as the revive path for a parked target — that is why the UI shows one button for both.

Paused blocks on-demand creeps too (CreepRunController), otherwise pausing would be a no-op for a target whose frequency is already Manual.
