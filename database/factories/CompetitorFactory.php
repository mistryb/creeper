<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Competitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Competitor>
 */
class CompetitorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->unique()->company(),
            'url' => 'https://'.fake()->unique()->domainName(),
            'description' => fake()->sentence(),
            'discovered_at' => null,
            'reason' => null,
        ];
    }

    /**
     * A competitor Creeper found rather than the user.
     */
    public function discovered(): static
    {
        return $this->state(fn (): array => [
            'description' => null,
            'discovered_at' => Carbon::now(),
            'reason' => fake()->sentence(),
        ]);
    }

    /**
     * A competitor of this user's first business, setting one up if they
     * have none — so several made for one user share a business, as they
     * would in the app.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'business_id' => fn (): int => ($user->businesses()->first() ?? Business::factory()->for($user)->create())->id,
        ]);
    }
}
