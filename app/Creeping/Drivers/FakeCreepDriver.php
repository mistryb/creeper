<?php

namespace App\Creeping\Drivers;

use App\Creeping\Contracts\CreepDriver;
use App\Creeping\Data\CreepResult;
use App\Enums\CreepType;
use App\Models\CreepRun;
use Illuminate\Support\Carbon;

/**
 * A driver that invents plausible data without leaving the machine.
 *
 * This is what a fresh clone runs on, and what the test suite uses. It answers
 * in whatever shape the target's type expects, and it moves between runs —
 * prices drift, changelogs ship — so change detection has something to find.
 */
class FakeCreepDriver implements CreepDriver
{
    /**
     * How many releases an invented changelog lists.
     */
    private const CHANGELOG_DEPTH = 4;

    /**
     * Payloads queued up by tests, returned in order before falling back to
     * generated data.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $queue = [];

    protected ?string $failure = null;

    public function name(): string
    {
        return 'fake';
    }

    public function creep(CreepRun $run): CreepResult
    {
        if ($this->failure !== null) {
            return CreepResult::failed($this->failure);
        }

        if ($this->queue !== []) {
            return CreepResult::succeeded(array_shift($this->queue));
        }

        return CreepResult::succeeded($this->invent($run));
    }

    /**
     * Queue an exact payload for the next creep.
     *
     * @param  array<string, mixed>  $payload
     */
    public function willReturn(array $payload): self
    {
        $this->queue[] = $payload;

        return $this;
    }

    /**
     * Make every subsequent creep report a definite failure.
     */
    public function willFail(string $error = 'The fake driver was told to fail.'): self
    {
        $this->failure = $error;

        return $this;
    }

    /**
     * Invent a reading of whatever kind this target is.
     *
     * @return array<string, mixed>
     */
    protected function invent(CreepRun $run): array
    {
        return match ($run->target->type) {
            CreepType::Product => $this->inventProduct($run),
            CreepType::Changelog => $this->inventChangelog($run),
        };
    }

    /**
     * Invent a product for this target.
     *
     * The base price is derived from the URL so a target keeps its identity
     * across runs; the jitter is what makes a price history interesting.
     *
     * @return array<string, mixed>
     */
    protected function inventProduct(CreepRun $run): array
    {
        $seed = crc32($run->target->url);
        $basePrice = 1000 + ($seed % 25000);
        $jitter = random_int(-500, 500);

        return [
            'title' => $run->target->name ?? 'Product at '.(parse_url($run->target->url, PHP_URL_HOST) ?: 'unknown host'),
            'brand' => ['Acme', 'Globex', 'Initech', 'Umbrella'][$seed % 4],
            'sku' => 'SKU-'.str_pad((string) ($seed % 100000), 6, '0', STR_PAD_LEFT),
            'price_amount' => max(100, $basePrice + $jitter),
            'currency' => 'GBP',
            'availability' => random_int(1, 10) > 2 ? 'in_stock' : 'out_of_stock',
            'rating' => round(3 + (($seed % 200) / 100), 2),
            'review_count' => $seed % 5000,
            'image_url' => 'https://placehold.co/600x400?text=Creeper',
        ];
    }

    /**
     * Invent a changelog for this target.
     *
     * The version numbers walk forward with the number of runs, so a second
     * creep finds exactly one release that wasn't there before — which is what
     * a changelog watcher is for.
     *
     * @return array<string, mixed>
     */
    protected function inventChangelog(CreepRun $run): array
    {
        $seed = crc32($run->target->url);
        $latest = 2 + $run->target->runs()->count();

        $releases = [];

        // Newest first, the way a changelog page reads. The oldest release
        // invented is v2.0.0, which is why the walk starts at 2.
        foreach (range(0, min(self::CHANGELOG_DEPTH, $latest + 1) - 1) as $index) {
            $minor = $latest - $index;

            $releases[] = [
                'version' => "v2.{$minor}.0",
                'released_on' => Carbon::now()->subWeeks($index)->toDateString(),
                'title' => null,
                'summary' => 'A release invented by the fake driver.',
                'features' => $this->inventFeatures($seed + $minor),
            ];
        }

        return [
            'product' => $run->target->name ?? (parse_url($run->target->url, PHP_URL_HOST) ?: 'Unknown product'),
            'latest_version' => $releases[0]['version'],
            'releases' => $releases,
        ];
    }

    /**
     * A couple of stable features for one release.
     *
     * @return array<int, array<string, string>>
     */
    protected function inventFeatures(int $seed): array
    {
        $pool = [
            ['title' => 'Bulk export', 'kind' => 'feature'],
            ['title' => 'Webhook retries', 'kind' => 'improvement'],
            ['title' => 'Single sign-on', 'kind' => 'feature'],
            ['title' => 'Faster search', 'kind' => 'improvement'],
            ['title' => 'Timezone handling on invoices', 'kind' => 'fix'],
            ['title' => 'Legacy v1 API removed', 'kind' => 'breaking'],
        ];

        return [
            $pool[$seed % count($pool)],
            $pool[($seed + 3) % count($pool)],
        ];
    }
}
