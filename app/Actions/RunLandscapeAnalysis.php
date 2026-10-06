<?php

namespace App\Actions;

use App\Ai\Agents\LandscapeAgent;
use App\Ai\ProviderFailure;
use App\Ai\UserKeyProvider;
use App\Enums\RunStatus;
use App\Models\Business;
use App\Models\BusinessAnalysis;
use App\Models\Competitor;
use App\Models\LandscapeAnalysis;
use App\Models\PageSnapshot;
use App\Models\WatchedPage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Runs one queued landscape to the end, successfully or not.
 *
 * Like the business analysis, every way this can go wrong ends with the run
 * marked Failed and a sentence saying why, and it is never retried behind the
 * user's back.
 *
 * A report is {summary, actions, dimensions, rows, map}. Rows and map points
 * name companies by subject id — "you", or "competitor:{id}" — and nothing the
 * model says about a company it was not asked about survives.
 */
class RunLandscapeAnalysis
{
    public function __construct(private UserKeyProvider $keys) {}

    public function handle(LandscapeAnalysis $landscape): void
    {
        $business = $landscape->business;
        $apiKey = $landscape->apiKey;

        if ($apiKey === null) {
            $landscape->fail('The API key this run was to spend has been removed. Run it again with another key.');

            return;
        }

        $competitors = $business->competitors()->with('watchedPages.latestSnapshot')->get();

        if ($competitors->isEmpty()) {
            $landscape->fail('There is nobody to compare against yet. Add a competitor, then run it again.');

            return;
        }

        $landscape->markRunning();

        $subjects = [LandscapeAnalysis::YOU, ...$competitors->map(LandscapeAnalysis::subjectFor(...))->all()];

        try {
            $response = $this->keys->using($apiKey, fn (string $instance) => (new LandscapeAgent)->prompt(
                $this->prompt($business, $competitors->all()),
                provider: $instance,
                model: $this->model(),
                timeout: (int) config('creeping.analysis.timeout', 120),
            ));
        } catch (Throwable $exception) {
            $landscape->fail(ProviderFailure::explain($exception, $apiKey->provider) ?? throw $exception);

            return;
        }

        $report = $response instanceof StructuredAgentResponse
            ? $this->normalise($response->toArray(), $subjects)
            : null;

        if ($report === null) {
            $landscape->fail('The model returned a comparison that was not in the expected shape. Running it again usually fixes this.');

            return;
        }

        $landscape->succeed($report, [
            'provider' => $apiKey->provider->value,
            'model' => $this->model(),
            'prompt_tokens' => $response->usage->promptTokens,
            'completion_tokens' => $response->usage->completionTokens,
        ]);
    }

    /**
     * Everything known about the business and every competitor, in one prompt,
     * with the dimensions used last time so they can be kept.
     *
     * @param  list<Competitor>  $competitors
     */
    public function prompt(Business $business, array $competitors): string
    {
        $sections = [implode("\n", array_filter([
            '# You (subject: '.LandscapeAnalysis::YOU.')',
            "Name: {$business->name}",
            $business->url === null ? null : "Website: {$business->url}",
            "How the owner describes it: {$business->description}",
            $this->analysis($business),
        ]))];

        foreach ($competitors as $competitor) {
            $sections[] = implode("\n", array_filter([
                "# Competitor: {$competitor->name} (subject: ".LandscapeAnalysis::subjectFor($competitor).')',
                $competitor->url === null ? null : "Website: {$competitor->url}",
                filled($competitor->description) ? "What the user knows: {$competitor->description}" : null,
                $this->analysis($competitor),
                $this->pages($competitor),
            ]));
        }

        $previous = $business->landscapes()->reorder()->where('status', RunStatus::Succeeded)->latest('id')->first();
        $dimensions = $previous?->report['dimensions'] ?? [];

        if ($dimensions !== []) {
            $sections[] = "# Dimensions used last time\nReuse these names exactly where they still matter:\n- "
                .implode("\n- ", array_map(fn (array $dimension): string => "{$dimension['name']} ({$dimension['kind']})", $dimensions));
        }

        return implode("\n\n", $sections);
    }

    /**
     * The latest successful analysis of a company, boiled down to the parts a
     * comparison needs.
     */
    private function analysis(Business|Competitor $subject): ?string
    {
        /** @var BusinessAnalysis|null $analysis */
        $analysis = $subject->analyses()->reorder()->where('status', RunStatus::Succeeded)->latest('id')->first();
        $report = $analysis?->report;

        if ($report === null) {
            return null;
        }

        return implode("\n", array_filter([
            'Latest analysis:',
            "- Summary: {$report['summary']}",
            "- Positioning: {$report['positioning']}",
            $report['pricing'] === null ? null : "- Pricing: {$report['pricing']}",
            $report['strengths'] === [] ? null : '- Strengths: '.implode('; ', $report['strengths']),
            $report['weaknesses'] === [] ? null : '- Weaknesses: '.implode('; ', $report['weaknesses']),
        ]));
    }

    /**
     * The latest facts off every page watched on a competitor.
     */
    private function pages(Competitor $competitor): ?string
    {
        $lines = $competitor->watchedPages
            ->filter(fn (WatchedPage $page): bool => $page->latestSnapshot instanceof PageSnapshot)
            ->map(function (WatchedPage $page): string {
                $facts = implode('; ', array_map(
                    fn (array $fact): string => "{$fact['label']}: {$fact['value']}",
                    $page->latestSnapshot->facts,
                ));

                return "- [{$page->category->value}] {$page->displayName()} — ".($facts === '' ? $page->latestSnapshot->summary : $facts);
            });

        return $lines->isEmpty() ? null : "Read off their pages:\n".$lines->implode("\n");
    }

    /**
     * The report, cleaned up and lined up, or null if it is unusable.
     *
     * Dimensions are de-duplicated and capped. Every row gets one cell per
     * dimension, in the dimensions' order, with "Unknown" where the model said
     * nothing. Rows and points for companies that were not asked about are
     * dropped, and a report without a row for the business itself is no
     * comparison at all.
     *
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $subjects
     * @return array<string, mixed>|null
     */
    public function normalise(array $raw, array $subjects): ?array
    {
        $summary = $this->text($raw['summary'] ?? null, 2000);
        $dimensions = $this->dimensions($raw['dimensions'] ?? null);

        if ($summary === null || $dimensions === []) {
            return null;
        }

        $known = array_flip($subjects);
        $rows = [];

        foreach (is_array($raw['rows'] ?? null) ? $raw['rows'] : [] as $row) {
            $subject = is_array($row) ? ($row['subject'] ?? null) : null;

            if (! is_string($subject) || ! isset($known[$subject]) || isset($rows[$subject])) {
                continue;
            }

            $rows[$subject] = [
                'subject' => $subject,
                'confidence' => in_array($row['confidence'] ?? null, ['high', 'medium', 'low'], true) ? $row['confidence'] : 'low',
                'cells' => $this->cells($row['cells'] ?? null, $dimensions),
            ];
        }

        if (! isset($rows[LandscapeAnalysis::YOU])) {
            return null;
        }

        return [
            'summary' => $summary,
            'actions' => array_slice(array_values(array_filter(array_map(
                fn (mixed $action): ?string => $this->text($action, 500),
                is_array($raw['actions'] ?? null) ? $raw['actions'] : [],
            ))), 0, LandscapeAgent::MAX_ACTIONS),
            'dimensions' => $dimensions,
            // In the order the subjects were given: the business first.
            'rows' => array_values(array_filter(array_map(fn (string $subject): ?array => $rows[$subject] ?? null, $subjects))),
            'map' => $this->map($raw['map'] ?? null, $known),
        ];
    }

    /**
     * @return list<array{name: string, kind: string, description: string}>
     */
    private function dimensions(mixed $raw): array
    {
        $dimensions = [];

        foreach (is_array($raw) ? $raw : [] as $dimension) {
            $name = is_array($dimension) ? $this->text($dimension['name'] ?? null, 60) : null;

            if ($name === null || isset($dimensions[mb_strtolower($name)])) {
                continue;
            }

            $dimensions[mb_strtolower($name)] = [
                'name' => $name,
                'kind' => ($dimension['kind'] ?? null) === 'fact' ? 'fact' : 'judgement',
                'description' => $this->text($dimension['description'] ?? null, 300) ?? '',
            ];
        }

        return array_slice(array_values($dimensions), 0, LandscapeAgent::MAX_DIMENSIONS);
    }

    /**
     * @param  list<array{name: string, kind: string, description: string}>  $dimensions
     * @return list<array{dimension: string, value: string, score: int|null}>
     */
    private function cells(mixed $raw, array $dimensions): array
    {
        $given = [];

        foreach (is_array($raw) ? $raw : [] as $cell) {
            $dimension = is_array($cell) ? $this->text($cell['dimension'] ?? null, 60) : null;

            if ($dimension !== null) {
                $given[mb_strtolower($dimension)] ??= $cell;
            }
        }

        return array_map(function (array $dimension) use ($given): array {
            $cell = $given[mb_strtolower($dimension['name'])] ?? [];
            $score = $cell['score'] ?? null;

            return [
                'dimension' => $dimension['name'],
                'value' => $this->text($cell['value'] ?? null, 200) ?? 'Unknown',
                'score' => is_numeric($score) && (int) $score >= 1 && (int) $score <= 5 ? (int) $score : null,
            ];
        }, $dimensions);
    }

    /**
     * @param  array<string, int>  $known
     * @return array{x_axis: string, y_axis: string, points: list<array{subject: string, x: float, y: float}>}|null
     */
    private function map(mixed $raw, array $known): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $xAxis = $this->text($raw['x_axis'] ?? null, 60);
        $yAxis = $this->text($raw['y_axis'] ?? null, 60);

        if ($xAxis === null || $yAxis === null) {
            return null;
        }

        $points = [];

        foreach (is_array($raw['points'] ?? null) ? $raw['points'] : [] as $point) {
            $subject = is_array($point) ? ($point['subject'] ?? null) : null;

            if (! is_string($subject) || ! isset($known[$subject]) || isset($points[$subject])
                || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) {
                continue;
            }

            $points[$subject] = [
                'subject' => $subject,
                'x' => max(0.0, min(10.0, (float) $point['x'])),
                'y' => max(0.0, min(10.0, (float) $point['y'])),
            ];
        }

        return ['x_axis' => $xAxis, 'y_axis' => $yAxis, 'points' => array_values($points)];
    }

    private function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function model(): ?string
    {
        $model = config('creeping.analysis.model');

        return is_string($model) && $model !== '' ? $model : null;
    }
}
