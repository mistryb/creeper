<?php

namespace Database\Factories;

use App\Creeping\Data\ChangelogPayload;
use App\Models\ChangelogSnapshot;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ChangelogSnapshot>
 */
class ChangelogSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $releases = [
            self::release('v2.1.0', Carbon::now()->subWeek()->toDateString()),
            self::release('v2.0.0', Carbon::now()->subMonth()->toDateString()),
        ];

        return [
            'creep_run_id' => CreepRun::factory(),
            'creep_target_id' => CreepTarget::factory(),
            'product' => fake()->words(2, true),
            'latest_version' => $releases[0]['version'],
            'latest_released_on' => $releases[0]['released_on'],
            'release_count' => count($releases),
            'feature_count' => 2,
            'releases' => $releases,
            'extra' => null,
            'captured_at' => Carbon::now(),
        ];
    }

    /**
     * Attach the snapshot to a target and to a run belonging to that target.
     */
    public function forTarget(CreepTarget $target): static
    {
        return $this->state(fn (): array => [
            'creep_target_id' => $target->id,
            'creep_run_id' => CreepRun::factory()->state(['creep_target_id' => $target->id]),
        ]);
    }

    /**
     * Say exactly which releases the page listed, newest first.
     *
     * @param  array<int, array<string, mixed>>  $releases
     */
    public function listing(array $releases): static
    {
        return $this->state(fn (): array => [
            'latest_version' => $releases[0]['version'] ?? null,
            'latest_released_on' => $releases[0]['released_on'] ?? null,
            'release_count' => count($releases),
            'feature_count' => array_sum(array_map(
                fn (array $release): int => count($release['features'] ?? []),
                $releases,
            )),
            'releases' => $releases,
        ]);
    }

    /**
     * One release, shaped the way {@see ChangelogPayload} shapes them.
     *
     * @return array<string, mixed>
     */
    public static function release(string $version, ?string $releasedOn = null): array
    {
        return [
            'version' => $version,
            'released_on' => $releasedOn,
            'title' => null,
            'summary' => null,
            'features' => [
                ['title' => 'Bulk export', 'description' => null, 'kind' => 'feature'],
            ],
        ];
    }
}
