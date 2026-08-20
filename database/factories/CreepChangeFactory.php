<?php

namespace Database\Factories;

use App\Enums\ChangeDirection;
use App\Models\CreepChange;
use App\Models\CreepTarget;
use App\Models\ProductSnapshot;
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
            'creep_target_id' => CreepTarget::factory(),
            'from_snapshot_id' => ProductSnapshot::factory(),
            'to_snapshot_id' => ProductSnapshot::factory(),
            'field' => 'price',
            'old_value' => '£24.99',
            'new_value' => '£19.99',
            'direction' => ChangeDirection::Down,
            'detected_at' => Carbon::now(),
        ];
    }
}
