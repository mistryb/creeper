<?php

namespace Database\Factories;

use App\Enums\ChangeKind;
use App\Models\CreepChange;
use App\Models\PageSnapshot;
use App\Models\WatchedPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CreepChange>
 */
class CreepChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'watched_page_id' => WatchedPage::factory(),
            'from_snapshot_id' => fn (array $attributes): int => PageSnapshot::factory()->of(WatchedPage::query()->findOrFail($attributes['watched_page_id']))->create()->id,
            'to_snapshot_id' => fn (array $attributes): int => PageSnapshot::factory()->of(WatchedPage::query()->findOrFail($attributes['watched_page_id']))->create()->id,
            'label' => 'Pro plan',
            'old_value' => '$20/user/month',
            'new_value' => '$25/user/month',
            'kind' => ChangeKind::Changed,
            'detected_at' => Carbon::now(),
        ];
    }
}
