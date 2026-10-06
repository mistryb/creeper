<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Business;
use App\Models\BusinessAnalysis;
use App\Models\Competitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<BusinessAnalysis>
 */
class BusinessAnalysisFactory extends Factory
{
    /**
     * A finished, successful run on somebody's business.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'analyzable_type' => 'business',
            'analyzable_id' => Business::factory(),
            'api_key_id' => null,
            'status' => RunStatus::Succeeded,
            'report' => self::report(),
            'source_url' => null,
            'provider' => 'anthropic',
            'model' => null,
            'prompt_tokens' => 1200,
            'completion_tokens' => 600,
            'error' => null,
            'started_at' => Carbon::now()->subMinute(),
            'finished_at' => Carbon::now(),
        ];
    }

    /**
     * A report of the shape the agent returns.
     *
     * @return array<string, mixed>
     */
    public static function report(): array
    {
        return [
            'summary' => 'A small-batch coffee roaster selling subscriptions to home brewers.',
            'offering' => 'Single-origin coffee, roasted to order, by subscription.',
            'audience' => 'Enthusiast home brewers in the UK.',
            'positioning' => 'Freshness over price: roasted to order and shipped within 48 hours.',
            'pricing' => null,
            'strengths' => ['Freshness guarantee'],
            'weaknesses' => ['Premium price point'],
            'opportunities' => ['Office subscriptions'],
            'threats' => ['Supermarket specialty ranges'],
            'confidence' => 'medium',
            'notes' => null,
        ];
    }

    public function forBusiness(Business $business): static
    {
        return $this->forSubject($business);
    }

    /**
     * A run on a business or one of its competitors.
     */
    public function forSubject(Business|Competitor $subject): static
    {
        return $this->state(fn (): array => [
            'analyzable_type' => $subject->getMorphClass(),
            'analyzable_id' => $subject->getKey(),
        ]);
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
