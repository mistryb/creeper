<?php

namespace Database\Factories;

use App\Enums\CreepFrequency;
use App\Enums\CreepType;
use App\Enums\TargetStatus;
use App\Models\ApiKey;
use App\Models\CreepTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CreepTarget>
 */
class CreepTargetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $frequency = fake()->randomElement(CreepFrequency::cases());

        return [
            'user_id' => User::factory(),
            // Every target is crept with one of its owner's keys, so a target
            // that made itself up needs a key of its own to be usable.
            'api_key_id' => fn (array $attributes): int => ApiKey::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
            'type' => CreepType::Product,
            'url' => 'https://'.fake()->unique()->domainName().'/products/'.fake()->slug(),
            'name' => fake()->words(3, true),
            'status' => TargetStatus::Active,
            'frequency' => $frequency,
            'notify_on_change' => true,
            'consecutive_failures' => 0,
            'last_crept_at' => null,
            'next_creep_at' => $frequency->nextRunAfter(Carbon::now()),
            'settings' => null,
        ];
    }

    /**
     * A target whose key has been deleted, and which therefore cannot be
     * crept until somebody gives it another.
     */
    public function keyless(): static
    {
        return $this->state(fn (): array => ['api_key_id' => null]);
    }

    /**
     * A target pointed at a changelog rather than a shop page.
     */
    public function changelog(): static
    {
        return $this->state(fn (): array => [
            'type' => CreepType::Changelog,
            'url' => 'https://'.fake()->unique()->domainName().'/changelog',
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => [
            'status' => TargetStatus::Paused,
            'next_creep_at' => null,
        ]);
    }

    public function failing(): static
    {
        return $this->state(fn (): array => [
            'status' => TargetStatus::Failed,
            'consecutive_failures' => CreepTarget::FAILURE_LIMIT,
            'next_creep_at' => null,
        ]);
    }

    /**
     * A target the scheduler should pick up on its next sweep.
     */
    public function due(): static
    {
        return $this->state(fn (): array => [
            'status' => TargetStatus::Active,
            'frequency' => CreepFrequency::Hourly,
            'next_creep_at' => Carbon::now()->subMinute(),
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (): array => [
            'frequency' => CreepFrequency::Manual,
            'next_creep_at' => null,
        ]);
    }
}
