<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Competitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        ];
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
