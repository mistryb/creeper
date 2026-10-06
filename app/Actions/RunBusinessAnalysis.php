<?php

namespace App\Actions;

use App\Ai\Agents\BusinessAnalysisAgent;
use App\Ai\Contracts\Analyzable;
use App\Ai\ProviderFailure;
use App\Ai\UserKeyProvider;
use App\Creeping\Exceptions\PageFetchFailed;
use App\Creeping\Fetching\DigestProfile;
use App\Creeping\Fetching\PageDigest;
use App\Creeping\Fetching\PageFetcher;
use App\Models\BusinessAnalysis;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Runs one queued business analysis to the end, successfully or not.
 *
 * Every way this can go wrong ends with the analysis marked Failed and a
 * sentence saying why, because the person who asked is looking at the screen
 * with a "Run again" button. Retrying behind their back would spend their key
 * on an answer they may already have given up on.
 */
class RunBusinessAnalysis
{
    public function __construct(private UserKeyProvider $keys) {}

    public function handle(BusinessAnalysis $analysis): void
    {
        $subject = $analysis->analyzable;

        if (! $subject instanceof Analyzable) {
            $analysis->fail('What was being analysed no longer exists.');

            return;
        }

        $apiKey = $analysis->apiKey;

        if ($apiKey === null) {
            $analysis->fail('The API key this analysis was to spend has been removed. Run it again with another key.');

            return;
        }

        $analysis->markRunning();

        [$website, $sourceUrl] = $this->readWebsite($subject->analysisUrl());

        /** @var array<string, mixed> $config */
        $config = config('creeping.analysis', []);
        $model = is_string($config['model'] ?? null) && $config['model'] !== '' ? $config['model'] : null;

        try {
            $response = $this->keys->using($apiKey, fn (string $instance) => (new BusinessAnalysisAgent)->prompt(
                $this->prompt($subject, $website),
                provider: $instance,
                model: $model,
                timeout: (int) ($config['timeout'] ?? 120),
            ));
        } catch (Throwable $exception) {
            $analysis->fail(ProviderFailure::explain($exception, $apiKey->provider) ?? throw $exception);

            return;
        }

        if (! $response instanceof StructuredAgentResponse) {
            $analysis->fail('The model did not return a structured report.');

            return;
        }

        $report = $this->validated($response->toArray());

        if ($report === null) {
            $analysis->fail('The model returned a report that was not in the expected shape. Running it again usually fixes this.');

            return;
        }

        $analysis->succeed($report, [
            'source_url' => $sourceUrl,
            'provider' => $apiKey->provider->value,
            'model' => $model,
            'prompt_tokens' => $response->usage->promptTokens,
            'completion_tokens' => $response->usage->completionTokens,
        ]);
    }

    /**
     * Read the website, if there is one worth reading.
     *
     * A website that cannot be read is not a reason to fail: the owner's
     * description is enough for an analysis, and the prompt says the site was
     * missing so the model does not pretend otherwise.
     *
     * @return array{0: PageDigest|null, 1: string|null}
     */
    private function readWebsite(?string $url): array
    {
        if ($url === null) {
            return [null, null];
        }

        /** @var array<string, mixed> $fetching */
        $fetching = config('creeping.drivers.llm', []);

        try {
            $page = (new PageFetcher($fetching))->fetch($url);
        } catch (PageFetchFailed) {
            return [null, null];
        }

        $digest = PageDigest::fromHtml(
            $page->html,
            (int) config('creeping.analysis.max_characters', 12000),
            DigestProfile::homepage(),
        );

        return $digest->isThin() ? [null, null] : [$digest, $page->url];
    }

    private function prompt(Analyzable $subject, ?PageDigest $website): string
    {
        return implode("\n\n", [
            "# Brief\n".$subject->analysisBrief(),
            $website === null
                ? "# Website\nNo website was read for this business."
                : "# Website digest\n".$website->toPrompt($subject->analysisUrl() ?? ''),
        ]);
    }

    /**
     * The report, if it is the shape the screen expects. Nothing from the
     * model is trusted until it has been through this.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>|null
     */
    private function validated(array $report): ?array
    {
        $points = ['present', 'array', 'max:'.BusinessAnalysisAgent::MAX_POINTS];

        $validator = Validator::make($report, [
            'summary' => ['required', 'string', 'max:2000'],
            'offering' => ['required', 'string', 'max:2000'],
            'audience' => ['required', 'string', 'max:2000'],
            'positioning' => ['required', 'string', 'max:2000'],
            'pricing' => ['nullable', 'string', 'max:2000'],
            'strengths' => $points,
            'strengths.*' => ['string', 'max:500'],
            'weaknesses' => $points,
            'weaknesses.*' => ['string', 'max:500'],
            'opportunities' => $points,
            'opportunities.*' => ['string', 'max:500'],
            'threats' => $points,
            'threats.*' => ['string', 'max:500'],
            'confidence' => ['required', 'in:high,medium,low'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        return [
            ...array_fill_keys(['pricing', 'notes'], null),
            ...$validator->validated(),
        ];
    }
}
