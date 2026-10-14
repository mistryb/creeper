<?php

namespace Database\Factories;

use App\Enums\DigestFrequency;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'url' => 'https://'.fake()->unique()->domainName(),
            'description' => fake()->paragraph(),
            'digest_frequency' => DigestFrequency::Weekly,
            'api_key_id' => null,
            'last_digest_at' => null,
            'next_digest_at' => null,
        ];
    }

    /**
     * A company with a key of its owner's to spend.
     */
    public function withKey(): static
    {
        return $this->state(fn (): array => [
            // Resolved after `user_id` has, so the key belongs to the same user.
            'api_key_id' => fn (array $attributes): int => ApiKey::factory()->create(['user_id' => $attributes['user_id']])->id,
        ]);
    }

    /**
     * A company whose next digest is due now.
     */
    public function due(): static
    {
        return $this->state(fn (): array => ['next_digest_at' => Carbon::now()->subMinute()]);
    }

    /**
     * A business with no website of its own.
     */
    public function withoutUrl(): static
    {
        return $this->state(fn (): array => ['url' => null]);
    }
}
