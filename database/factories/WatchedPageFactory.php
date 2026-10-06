<?php

namespace Database\Factories;

use App\Enums\CreepFrequency;
use App\Enums\PageCategory;
use App\Enums\PageStatus;
use App\Models\ApiKey;
use App\Models\Competitor;
use App\Models\WatchedPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<WatchedPage>
 */
class WatchedPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $frequency = fake()->randomElement(CreepFrequency::cases());

        return [
            'competitor_id' => Competitor::factory(),
            // Every page is crept with one of its owner's keys, so a page
            // that made itself up needs a key of its own to be usable.
            'api_key_id' => fn (array $attributes): int => ApiKey::factory()->create([
                'user_id' => Competitor::query()->findOrFail($attributes['competitor_id'])->owner()->id,
            ])->id,
            'url' => 'https://'.fake()->unique()->domainName().'/pricing',
            'watch_for' => 'Each plan\'s name and price, and any plan added or removed.',
            'category' => PageCategory::Pricing,
            'name' => fake()->words(3, true),
            'status' => PageStatus::Active,
            'frequency' => $frequency,
            'notify_on_change' => true,
            'consecutive_failures' => 0,
            'last_crept_at' => null,
            'next_creep_at' => $frequency->nextRunAfter(Carbon::now()),
            'settings' => null,
        ];
    }

    /**
     * A page watched for this user, on the first competitor of their first
     * business — both set up if they do not exist yet.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'competitor_id' => fn (): int => (
                Competitor::query()->whereHas('business', fn ($query) => $query->where('user_id', $user->id))->orderBy('id')->first()
                ?? Competitor::factory()->forUser($user)->create()
            )->id,
        ]);
    }

    /**
     * A page whose key has been deleted, and which therefore cannot be
     * crept until somebody gives it another.
     */
    public function keyless(): static
    {
        return $this->state(fn (): array => ['api_key_id' => null]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => [
            'status' => PageStatus::Paused,
            'next_creep_at' => null,
        ]);
    }

    public function failing(): static
    {
        return $this->state(fn (): array => [
            'status' => PageStatus::Failed,
            'consecutive_failures' => WatchedPage::FAILURE_LIMIT,
            'next_creep_at' => null,
        ]);
    }

    /**
     * A page the scheduler should pick up on its next sweep.
     */
    public function due(): static
    {
        return $this->state(fn (): array => [
            'status' => PageStatus::Active,
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
