<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Business;
use App\Models\Competitor;
use App\Models\Digest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Digest>
 */
class DigestFactory extends Factory
{
    /**
     * A written digest covering the last week, not yet sent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'api_key_id' => null,
            'status' => RunStatus::Succeeded,
            'report' => self::report(),
            'period_start' => Carbon::now()->subWeek(),
            'period_end' => Carbon::now(),
            'sent_at' => null,
            'provider' => 'anthropic',
            'model' => null,
            'prompt_tokens' => 6000,
            'completion_tokens' => 1200,
            'error' => null,
            'started_at' => Carbon::now()->subMinutes(2),
            'finished_at' => Carbon::now(),
        ];
    }

    /**
     * A report of the shape the action stores, about the given competitors
     * (or nobody in particular).
     *
     * @param  list<Competitor>  $competitors
     * @return array<string, mixed>
     */
    public static function report(array $competitors = []): array
    {
        $sections = array_map(fn (Competitor $competitor): array => [
            'subject' => $competitor->subject(),
            'summary' => "{$competitor->name} raised prices and shipped a new plan.",
            'items' => [
                ['title' => 'Growth plan now $249/month', 'detail' => 'Up from $199.', 'url' => 'https://example.com/pricing', 'date' => '2026-10-10'],
                ['title' => 'New Team plan', 'detail' => 'Seats for five, aimed at small agencies.', 'url' => null, 'date' => null],
            ],
        ], $competitors);

        return [
            'headline' => 'Prices went up across the board this week.',
            'competitors' => $sections,
            'notes' => null,
        ];
    }

    /**
     * A digest that has gone out to the owner.
     */
    public function sent(): static
    {
        return $this->state(fn (): array => ['sent_at' => Carbon::now()]);
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Queued,
            'report' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Running,
            'report' => null,
            'finished_at' => null,
        ]);
    }

    public function failed(string $error = 'The model provider rejected the key.'): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Failed,
            'report' => null,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'error' => $error,
        ]);
    }
}
