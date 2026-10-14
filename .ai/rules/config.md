---
paths:
  - config/queue.php
---

# Config

## retry_after must exceed the longest job timeout
`DB_QUEUE_RETRY_AFTER` (330) has to stay above `RunDigest::$timeout` and `RunCompetitorDiscovery::$timeout` (both 300). A job still running when its reservation lapses is handed to a second worker, which for a digest means paying for the research twice and emailing the owner twice. `ShouldBeUnique` does not protect against this — its lock is released when the job finishes, and a re-reserved copy of the same job never re-checks uniqueness.

`digest.timeout` (180) bounds the model call itself and has to stay well inside the job timeout, since the fetch of the company's homepage and the web searches the provider runs come on top of it.
