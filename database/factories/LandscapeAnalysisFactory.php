<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Business;
use App\Models\LandscapeAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<LandscapeAnalysis>
 */
class LandscapeAnalysisFactory extends Factory
{
    /**
     * A finished comparison of a business against every competitor it has
     * when the row is made.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'api_key_id' => null,
            'status' => RunStatus::Succeeded,
            'report' => fn (array $attributes): array => self::reportFor(Business::query()->findOrFail($attributes['business_id'])),
            'provider' => 'anthropic',
            'model' => null,
            'prompt_tokens' => 4000,
            'completion_tokens' => 1500,
            'error' => null,
            'started_at' => Carbon::now()->subMinute(),
            'finished_at' => Carbon::now(),
        ];
    }

    /**
     * A plausible report covering the business and each of its competitors.
     *
     * @return array<string, mixed>
     */
    public static function reportFor(Business $business): array
    {
        $subjects = [LandscapeAnalysis::YOU, ...$business->competitors()->get()->map(LandscapeAnalysis::subjectFor(...))->all()];

        return [
            'summary' => 'You are the simplest option with the most generous free tier, but the newest and least known.',
            'actions' => ['Lead with the free tier on the home page.'],
            'dimensions' => [
                ['name' => 'Entry price', 'kind' => 'fact', 'description' => 'The cheapest paid plan.'],
                ['name' => 'Ease of use', 'kind' => 'judgement', 'description' => 'How quickly a new team gets value.'],
            ],
            'rows' => array_map(fn (string $subject, int $index): array => [
                'subject' => $subject,
                'confidence' => 'medium',
                'cells' => [
                    ['dimension' => 'Entry price', 'value' => '$'.(20 + $index * 10).'/month', 'score' => null],
                    ['dimension' => 'Ease of use', 'value' => 'Set up in an afternoon', 'score' => max(1, 5 - $index)],
                ],
            ], $subjects, array_keys($subjects)),
            'map' => [
                'x_axis' => 'Price',
                'y_axis' => 'Breadth',
                'points' => array_map(fn (string $subject, int $index): array => [
                    'subject' => $subject,
                    'x' => (float) min(10, 2 + $index * 3),
                    'y' => (float) min(10, 3 + $index * 2),
                ], $subjects, array_keys($subjects)),
            ],
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
            'error' => $error,
            'prompt_tokens' => null,
            'completion_tokens' => null,
        ]);
    }
}
