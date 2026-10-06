<?php

namespace Database\Factories;

use App\Models\CreepRun;
use App\Models\PageSnapshot;
use App\Models\WatchedPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PageSnapshot>
 */
class PageSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creep_run_id' => CreepRun::factory(),
            'watched_page_id' => fn (array $attributes): int => CreepRun::query()->findOrFail($attributes['creep_run_id'])->watched_page_id,
            'summary' => 'Three plans: a free tier, Pro at $20 a month, and Enterprise on request.',
            'facts' => [
                ['label' => 'Hobby plan', 'value' => 'Free'],
                ['label' => 'Pro plan', 'value' => '$20/user/month'],
                ['label' => 'Enterprise plan', 'value' => 'Contact sales'],
            ],
            'extra' => null,
            'captured_at' => Carbon::now(),
        ];
    }

    /**
     * A reading of this page in particular, with these facts.
     *
     * @param  array<string, string>  $facts  Label => value.
     */
    public function of(WatchedPage $watchedPage, array $facts = []): static
    {
        return $this->state(fn (): array => [
            'creep_run_id' => CreepRun::factory()->for($watchedPage, 'watchedPage'),
            'watched_page_id' => $watchedPage->id,
            ...($facts === [] ? [] : ['facts' => array_map(
                fn (string $label, string $value): array => ['label' => $label, 'value' => $value],
                array_keys($facts),
                array_values($facts),
            )]),
        ]);
    }
}
