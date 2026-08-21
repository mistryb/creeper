<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * There is no password: accounts are reached with a one-time code sent to
     * the address, and the column only survives as a nullable relic.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'pending_email' => null,
            'remember_token' => Str::random(60),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * Only reachable for accounts that predate code sign-in — redeeming a code
     * is what creates an account now, so new ones are verified on arrival.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /** An account midway through changing its address. */
    public function changingEmailTo(string $email): static
    {
        return $this->state(fn (array $attributes): array => [
            'pending_email' => $email,
        ]);
    }
}
