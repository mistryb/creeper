---
paths:
  - config/queue.php
---

# Config

## retry_after must exceed the longest job timeout
`DB_QUEUE_RETRY_AFTER` (330) has to stay above `RunCreep::$timeout` (300). A job still running when its reservation lapses is handed to a second worker, which for a creep means fetching the page and paying for inference twice. `ShouldBeUnique` does not protect against this — its lock is released when the job finishes, and a re-reserved copy of the same job never re-checks uniqueness.

This was previously wrong: retry_after was 90 while the job timeout was 300, so any http-driver run past 91s was silently duplicated. The `llm` driver additionally bounds itself with `creeping.drivers.llm.budget`.
