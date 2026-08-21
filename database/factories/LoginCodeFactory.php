<?php

namespace Database\Factories;

use App\Auth\LoginCodes;
use App\Models\LoginCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<LoginCode>
 */
class LoginCodeFactory extends Factory
{
    protected $model = LoginCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(LoginCodes::TTL_MINUTES),
            'created_at' => now(),
        ];
    }

    /** A code whose window has closed. */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /** A code that has already been guessed at its limit. */
    public function exhausted(): static
    {
        return $this->state(fn (): array => [
            'attempts' => LoginCodes::MAX_ATTEMPTS,
        ]);
    }

    /**
     * A known code for a known address.
     *
     * Deliberately not called `for()`: that name belongs to Eloquent's
     * relationship helper on Factory, and shadowing it takes the process down.
     */
    public function forEmail(string $email, string $code = '123456'): static
    {
        return $this->state(fn (): array => [
            'email' => $email,
            'code_hash' => Hash::make($code),
        ]);
    }
}
