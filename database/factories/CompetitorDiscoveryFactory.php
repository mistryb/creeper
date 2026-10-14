<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Business;
use App\Models\CompetitorDiscovery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CompetitorDiscovery>
 */
class CompetitorDiscoveryFactory extends Factory
{
    /**
     * A finished, successful run.
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
            'provider' => 'anthropic',
            'model' => null,
            'prompt_tokens' => 2400,
            'completion_tokens' => 500,
            'error' => null,
            'started_at' => Carbon::now()->subMinute(),
            'finished_at' => Carbon::now(),
        ];
    }

    /**
     * A report of the shape the action stores.
     *
     * @return array<string, mixed>
     */
    public static function report(): array
    {
        return [
            'proposals' => [
                ['name' => 'Acme Insights', 'url' => 'https://acme.example', 'reason' => 'The incumbent, sold to the same teams.', 'added' => true],
                ['name' => 'Widgets Inc', 'url' => 'https://widgets.example', 'reason' => 'A newer rival with a free tier.', 'added' => true],
            ],
            'notes' => null,
        ];
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
