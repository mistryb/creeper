---
paths:
  - 'tests/Feature/*.php'
---

# Feature

## Agent::fake() closures receive a string, not an AgentPrompt
The two laravel/ai test hooks take different arguments, which is easy to get wrong:

- `MyAgent::fake(fn (string $prompt) => ...)` — the raw prompt text. Extra args are `$attachments, $provider, $model`.
- `MyAgent::assertPrompted(fn (AgentPrompt $prompt) => ...)` — the object, with `->prompt` and `->contains()`.

Typing the `fake()` closure as `AgentPrompt` gives a TypeError from inside `FakeTextGateway`, not a helpful failure. A throwing `fake()` closure does propagate, which is how provider failures (RateLimitedException, RequestException) are tested.
