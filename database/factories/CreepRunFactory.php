<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CreepRun>
 */
class CreepRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = Carbon::now()->subMinutes(fake()->numberBetween(1, 10_000));

        return [
            'creep_target_id' => CreepTarget::factory(),
            'status' => RunStatus::Succeeded,
            'driver' => 'fake',
            'started_at' => $startedAt,
            'finished_at' => $startedAt->addSeconds(3),
            'duration_ms' => 3000,
            'error' => null,
            'raw_payload' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Running,
            'finished_at' => null,
            'duration_ms' => null,
        ]);
    }

    public function failed(string $error = 'Something went wrong.'): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Failed,
            'error' => $error,
        ]);
    }
}
