---
paths:
  - 'app/Creeping/**'
---

# Creeping

## There is one reader, steered by the user's own words
There are no creep types. Every watched page is read by `WatchedPageAgent` through `App\Creeping\WatchInstructions`, which owns the digest (`DigestProfile::page()`), the prompt, and filing the reading. What differs per page is `watched_pages.watch_for` — the user's description of what to watch, chosen from presets in `resources/js/lib/watch-presets.ts` or written freely — and it travels in the prompt, never in the agent's system instructions. Do not reintroduce a type enum or per-type snapshot tables to get a richer view of one kind of page; make the reader's facts better instead.

A reading is a `page_snapshots` row: a `summary` and `facts`, a list of `{label, value}`. Every payload, from the model or an HTTP agent, goes through `PagePayload::fromArray()` first.

## Changes are facts compared by label, so labels must stay stable
`FactDetector` lines two readings up by label (`PageSnapshot::key()`: case, spacing and trailing punctuation ignored) and reports added, removed or changed (`ChangeKind`). Values are compared ignoring case and spacing. A model that renames a label between runs produces a spurious removed + added pair, which is why `WatchInstructions::prompt()` hands it the labels from the latest reading and the agent is told to reuse them. Keep that section in the prompt.
