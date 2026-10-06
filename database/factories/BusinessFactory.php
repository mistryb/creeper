<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        ];
    }

    /**
     * A business with no website of its own.
     */
    public function withoutUrl(): static
    {
        return $this->state(fn (): array => ['url' => null]);
    }
}
