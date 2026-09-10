<?php

namespace Database\Factories;

use App\Enums\CreepProvider;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'sk-ant-api03-'.fake()->unique()->regexify('[a-zA-Z0-9]{24}');

        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'provider' => CreepProvider::Anthropic,
            'key' => $key,
            'hint' => mb_substr($key, -4),
        ];
    }

    /**
     * A key for a particular provider, with a key that looks like one of
     * theirs.
     */
    public function provider(CreepProvider $provider): static
    {
        return $this->state(function () use ($provider): array {
            $key = str_replace('...', '', $provider->placeholder()).fake()->unique()->regexify('[a-zA-Z0-9]{24}');

            return [
                'provider' => $provider,
                'key' => $key,
                'hint' => mb_substr($key, -4),
            ];
        });
    }

    /**
     * A key with a known value, for tests that assert on what was spent.
     */
    public function value(string $key): static
    {
        return $this->state(fn (): array => [
            'key' => $key,
            'hint' => mb_substr($key, -4),
        ]);
    }
}
