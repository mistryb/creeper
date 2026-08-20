<?php

namespace Database\Factories;

use App\Enums\Availability;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use App\Models\ProductSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ProductSnapshot>
 */
class ProductSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creep_run_id' => CreepRun::factory(),
            'creep_target_id' => CreepTarget::factory(),
            'title' => fake()->words(4, true),
            'brand' => fake()->company(),
            'sku' => strtoupper(fake()->bothify('SKU-####??')),
            'price_amount' => fake()->numberBetween(500, 50_000),
            'currency' => 'GBP',
            'availability' => Availability::InStock,
            'rating' => fake()->randomFloat(2, 1, 5),
            'review_count' => fake()->numberBetween(0, 5_000),
            'image_url' => 'https://placehold.co/600x400',
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

    public function pricedAt(int $minorUnits): static
    {
        return $this->state(fn (): array => ['price_amount' => $minorUnits]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['availability' => Availability::OutOfStock]);
    }
}
