<?php

namespace App\Actions;

use App\Ai\Agents\CompetitorDiscoveryAgent;
use App\Ai\ProviderFailure;
use App\Ai\UserKeyProvider;
use App\Fetching\DigestProfile;
use App\Fetching\PageDigest;
use App\Fetching\PageFetcher;
use App\Fetching\PageFetchFailed;
use App\Models\Business;
use App\Models\Competitor;
use App\Models\CompetitorDiscovery;
use App\Rules\PublicUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Runs one queued discovery to the end, successfully or not.
 *
 * Every way this can go wrong ends with the run marked Failed and a sentence
 * saying why, because the person who asked is looking at a screen with a
 * "Find competitors" button. Retrying behind their back would spend their key
 * on an answer they may already have given up on.
 *
 * Whatever the model proposes is added as a competitor unless it is the
 * company itself or a competitor already on the list, matched by name or by
 * website host. The report records every proposal and whether it was added.
 */
class DiscoverCompetitors
{
    public function __construct(private UserKeyProvider $keys) {}

    public function handle(CompetitorDiscovery $discovery): void
    {
        $business = $discovery->business;
        $apiKey = $discovery->apiKey;

        if ($apiKey === null) {
            $discovery->fail('The API key this run was to spend has been removed. Pick another key for the company and try again.');

            return;
        }

        $discovery->markRunning();

        $website = $this->readWebsite($business->url);
        $model = $this->model();

        try {
            $response = $this->keys->using($apiKey, fn (string $instance) => (new CompetitorDiscoveryAgent)->prompt(
                $this->prompt($business, $website),
                provider: $instance,
                model: $model,
                timeout: (int) config('digest.timeout', 180),
            ));
        } catch (Throwable $exception) {
            $discovery->fail(ProviderFailure::explain($exception, $apiKey->provider) ?? throw $exception);

            return;
        }

        $proposals = $response instanceof StructuredAgentResponse
            ? $this->validated($response->toArray())
            : null;

        if ($proposals === null) {
            $discovery->fail('The model returned something that was not a list of competitors. Running it again usually fixes this.');

            return;
        }

        $discovery->succeed([
            'proposals' => $this->add($business, $proposals['competitors']),
            'notes' => $proposals['notes'],
        ], [
            'provider' => $apiKey->provider->value,
            'model' => $model,
            'prompt_tokens' => $response->usage->promptTokens,
            'completion_tokens' => $response->usage->completionTokens,
        ]);
    }

    /**
     * Add every proposal that is new, and say which were.
     *
     * @param  list<array{name: string, url: string|null, reason: string}>  $proposals
     * @return list<array{name: string, url: string|null, reason: string, added: bool}>
     */
    private function add(Business $business, array $proposals): array
    {
        $existing = $business->competitors()->get();
        $names = $existing->map(fn (Competitor $competitor): string => mb_strtolower($competitor->name))->all();
        $hosts = $existing->map(fn (Competitor $competitor): ?string => $competitor->host())->filter()->all();
        $own = $business->host();

        if ($own !== null) {
            $hosts[] = $own;
        }

        $cap = max(0, (int) config('digest.discovery.max_competitors', 8) - $existing->count());
        $recorded = [];

        foreach ($proposals as $proposal) {
            $name = mb_strtolower($proposal['name']);
            $host = Competitor::hostOf($proposal['url']);

            $duplicate = in_array($name, $names, true)
                || $name === mb_strtolower($business->name)
                || ($host !== null && in_array($host, $hosts, true));

            $added = ! $duplicate && $cap > 0;

            if ($added) {
                $business->competitors()->create([
                    'name' => $proposal['name'],
                    'url' => $proposal['url'],
                    'discovered_at' => Carbon::now(),
                    'reason' => $proposal['reason'],
                ]);

                $names[] = $name;

                if ($host !== null) {
                    $hosts[] = $host;
                }

                $cap--;
            }

            $recorded[] = [...$proposal, 'added' => $added];
        }

        return $recorded;
    }

    /**
     * Read the company's website, if there is one worth reading.
     *
     * A website that cannot be read is not a reason to fail: the owner's
     * description is enough to search from, and the prompt says the site was
     * missing so the model does not pretend otherwise.
     */
    private function readWebsite(?string $url): ?PageDigest
    {
        if ($url === null) {
            return null;
        }

        /** @var array<string, mixed> $fetching */
        $fetching = config('digest.fetch', []);

        try {
            $page = (new PageFetcher($fetching))->fetch($url);
        } catch (PageFetchFailed) {
            return null;
        }

        $digest = PageDigest::fromHtml(
            $page->html,
            (int) ($fetching['max_characters'] ?? 8000),
            DigestProfile::homepage(),
        );

        return $digest->isThin() ? null : $digest;
    }

    private function prompt(Business $business, ?PageDigest $website): string
    {
        $sections = [
            "# The company\n".$business->brief(),
            $website === null
                ? "# Website\nNo website could be read for this company."
                : "# Website digest\n".$website->toPrompt($business->url ?? ''),
        ];

        $existing = $business->competitors()->pluck('name');

        if ($existing->isNotEmpty()) {
            $sections[] = "# Already on the list\nDo not propose these again:\n- ".$existing->implode("\n- ");
        }

        return implode("\n\n", $sections);
    }

    /**
     * The proposals, if they are the shape expected. Nothing from the model
     * is trusted until it has been through this, and a URL that is not a
     * public address is dropped rather than stored.
     *
     * @param  array<string, mixed>  $raw
     * @return array{competitors: list<array{name: string, url: string|null, reason: string}>, notes: string|null}|null
     */
    private function validated(array $raw): ?array
    {
        $validator = Validator::make($raw, [
            'competitors' => ['present', 'array', 'max:'.(int) config('digest.discovery.max_competitors', 8)],
            'competitors.*.name' => ['required', 'string', 'max:255'],
            'competitors.*.url' => ['nullable', 'string', 'max:2048'],
            'competitors.*.reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        /** @var array{competitors: list<array{name: string, url?: string|null, reason: string}>, notes?: string|null} $validated */
        $validated = $validator->validated();

        $competitors = [];
        $seen = [];

        foreach ($validated['competitors'] as $proposal) {
            $name = trim($proposal['name']);
            $url = trim($proposal['url'] ?? '');

            if ($name === '' || isset($seen[mb_strtolower($name)])) {
                continue;
            }

            $seen[mb_strtolower($name)] = true;

            $competitors[] = [
                'name' => $name,
                'url' => $url !== '' && PublicUrl::permits($url) ? $url : null,
                'reason' => trim($proposal['reason']),
            ];
        }

        return ['competitors' => $competitors, 'notes' => $validated['notes'] ?? null];
    }

    private function model(): ?string
    {
        $model = config('digest.model');

        return is_string($model) && $model !== '' ? $model : null;
    }
}
