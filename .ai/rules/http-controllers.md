---
paths:
  - 'app/Http/Controllers/WatchedPagePauseController.php,app/Http/Controllers/WatchedPageController.php,app/Http/Controllers/CreepRunController.php'
---

# Http Controllers

## Pausing is its own endpoint, not a field on the settings form
Stopping a watched page without deleting it goes through WatchedPagePauseController (POST/DELETE `watched-pages/{watched_page}/pause`), which calls `WatchedPage::pause()` / `resume()`. Keep the transition arithmetic in those two model methods so the one-click path and the settings form cannot drift.

`status` on UpdateWatchedPageRequest is `sometimes`, not `required`: the settings form no longer submits it, and an omitted status must leave the page where it is. Do not make it required again without giving the form a status field back.

`update()` deliberately does not reuse `pause()`: a parked (Failed) page saving its settings must stay Failed, and `pause()` would flatten it to Paused.

`resume()` clears `consecutive_failures`, so it doubles as the revive path for a parked page — that is why the UI shows one button for both.

Paused blocks on-demand creeps too (CreepRunController), otherwise pausing would be a no-op for a page whose frequency is already Manual.
