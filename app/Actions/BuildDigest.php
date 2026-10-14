<?php

namespace App\Actions;

use App\Ai\Agents\DigestAgent;
use App\Ai\ProviderFailure;
use App\Ai\UserKeyProvider;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\Competitor;
use App\Models\Digest;
use App\Notifications\DigestReady;
use App\Rules\PublicUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Runs one queued digest to the end: researches every competitor, stores
 * the report, emails it, and puts the next one on the calendar.
 *
 * Every way this can go wrong ends with the digest marked Failed and a
 * sentence saying why. The schedule still moves on — a company whose key was
 * removed should not be retried every minute — but nothing is sent.
 *
 * A report is {headline, competitors, notes}. Sections name competitors by
 * subject id — `competitor:{id}` — and nothing the model says about a company
 * it was not asked about survives.
 */
class BuildDigest
{
    public function __construct(private UserKeyProvider $keys) {}

    public function handle(Digest $digest): void
    {
        $business = $digest->business;
        $apiKey = $digest->apiKey;

        try {
            $this->build($digest, $business, $apiKey);
        } finally {
            // Whatever happened, the next digest goes on the calendar: a
            // company whose key was removed must not be retried every minute.
            $business->scheduleDigestFrom($digest->period_end);
        }
    }

    private function build(Digest $digest, Business $business, ?ApiKey $apiKey): void
    {
        if ($apiKey === null) {
            $digest->fail('The API key this digest was to spend has been removed. Pick another key for the company.');

            return;
        }

        $competitors = $business->competitors()->get();

        if ($competitors->isEmpty()) {
            $digest->fail('There is nobody to write about yet. Add a competitor, or find some, then send again.');

            return;
        }

        $digest->markRunning();

        $model = $this->model();

        try {
            $response = $this->keys->using($apiKey, fn (string $instance) => (new DigestAgent)->prompt(
                $this->prompt($business, $competitors->all(), $digest->period_start, $digest->period_end),
                provider: $instance,
                model: $model,
                timeout: (int) config('digest.timeout', 180),
            ));
        } catch (Throwable $exception) {
            $digest->fail(ProviderFailure::explain($exception, $apiKey->provider) ?? throw $exception);

            return;
        }

        $report = $response instanceof StructuredAgentResponse
            ? $this->normalise($response->toArray(), $competitors->all())
            : null;

        if ($report === null) {
            $digest->fail('The model returned a digest that was not in the expected shape. Sending again usually fixes this.');

            return;
        }

        $digest->succeed($report, [
            'provider' => $apiKey->provider->value,
            'model' => $model,
            'prompt_tokens' => $response->usage->promptTokens,
            'completion_tokens' => $response->usage->completionTokens,
        ]);

        $business->user->notify(new DigestReady($digest));

        $now = Carbon::now();
        $digest->forceFill(['sent_at' => $now])->save();
        $business->markDigestSent($now);
    }

    /**
     * The company, every competitor with its subject id, and the period.
     *
     * @param  array<int, Competitor>  $competitors
     */
    public function prompt(Business $business, array $competitors, CarbonInterface $from, CarbonInterface $until): string
    {
        $sections = [
            "# The company\n".$business->brief(),
            "# Period to cover\nFrom {$from->toFormattedDateString()} to {$until->toFormattedDateString()}.",
        ];

        foreach ($competitors as $competitor) {
            $sections[] = implode("\n", array_filter([
                "# Competitor: {$competitor->name} (subject: {$competitor->subject()})",
                $competitor->url === null ? null : "Website: {$competitor->url}",
                filled($competitor->reason) ? "Why they compete: {$competitor->reason}" : null,
                filled($competitor->description) ? "What the owner knows: {$competitor->description}" : null,
            ]));
        }

        return implode("\n\n", $sections);
    }

    /**
     * The report, cleaned up and lined up, or null if it is unusable.
     *
     * Every competitor gets exactly one section, in the order they were
     * given, with a quiet placeholder where the model said nothing. Sections
     * for companies that were not asked about are dropped. Items are capped
     * and their URLs kept only when they point somewhere public.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<int, Competitor>  $competitors
     * @return array{headline: string, competitors: list<array{subject: string, summary: string, items: list<array{title: string, detail: string, url: string|null, date: string|null}>}>, notes: string|null}|null
     */
    public function normalise(array $raw, array $competitors): ?array
    {
        $headline = $this->text($raw['headline'] ?? null, 500);

        if ($headline === null) {
            return null;
        }

        $given = [];

        foreach (is_array($raw['competitors'] ?? null) ? $raw['competitors'] : [] as $section) {
            $subject = is_array($section) ? ($section['subject'] ?? null) : null;

            if (is_string($subject) && ! isset($given[$subject])) {
                $given[$subject] = $section;
            }
        }

        $sections = array_map(function (Competitor $competitor) use ($given): array {
            $section = $given[$competitor->subject()] ?? [];

            return [
                'subject' => $competitor->subject(),
                'summary' => $this->text($section['summary'] ?? null, 1000) ?? 'Nothing new was found in this period.',
                'items' => $this->items($section['items'] ?? null),
            ];
        }, array_values($competitors));

        return [
            'headline' => $headline,
            'competitors' => $sections,
            'notes' => $this->text($raw['notes'] ?? null, 1000),
        ];
    }

    /**
     * @return list<array{title: string, detail: string, url: string|null, date: string|null}>
     */
    private function items(mixed $raw): array
    {
        $items = [];

        foreach (is_array($raw) ? $raw : [] as $item) {
            $title = is_array($item) ? $this->text($item['title'] ?? null, 300) : null;

            if ($title === null) {
                continue;
            }

            $url = $this->text($item['url'] ?? null, 2048);

            $items[] = [
                'title' => $title,
                'detail' => $this->text($item['detail'] ?? null, 1000) ?? '',
                'url' => $url !== null && PublicUrl::permits($url) ? $url : null,
                'date' => $this->text($item['date'] ?? null, 40),
            ];

            if (count($items) === DigestAgent::MAX_ITEMS) {
                break;
            }
        }

        return $items;
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
        $model = config('digest.model');

        return is_string($model) && $model !== '' ? $model : null;
    }
}
