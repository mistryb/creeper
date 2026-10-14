---
paths:
  - 'app/Ai/Agents/**'
  - 'app/Actions/BuildDigest.php'
  - 'app/Actions/DiscoverCompetitors.php'
---

# Agents

## Research happens inside the provider, on the user's key
Both agents hand the model a `Laravel\Ai\Providers\Tools\WebSearch` provider tool, capped by `digest.max_searches` / `digest.discovery.max_searches`. The searches run on the provider's side and are billed to the key the company spends, so the caps are the cost dial — do not raise them without a reason. The only page Creeper fetches itself is the company's own homepage, through `App\Fetching\PageFetcher`, which treats every URL as hostile.

Structured output and tools work together in laravel/ai (`GeneratesText` passes the schema and the tools into one loop), which is what makes a `HasTools + HasStructuredOutput` agent possible. Nothing a model returns is trusted until it has been through `DiscoverCompetitors::validated()` or `BuildDigest::normalise()`.

## Competitors are addressed by subject id, never by name
Prompts and reports refer to a competitor as `competitor:{id}` (`Competitor::subject()`), so a digest section survives a rename and is dropped on screen and in mail when its competitor is deleted. `BuildDigest::normalise()` produces exactly one section per competitor in the order given, fills a quiet placeholder where the model said nothing, and discards sections about subjects that were not asked for. Keep that contract: the mail and the screen both look sections up by subject.
