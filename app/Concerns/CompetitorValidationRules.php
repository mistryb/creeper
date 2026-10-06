<?php

namespace App\Concerns;

use App\Models\Competitor;
use App\Rules\PublicUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CompetitorValidationRules
{
    /**
     * Get the validation rules used to validate a competitor.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function competitorRules(int $businessId, ?int $competitorId = null): array
    {
        $unique = Rule::unique(Competitor::class, 'name')->where('business_id', $businessId);

        return [
            // Two competitors with one name could not be told apart in a list.
            'name' => ['required', 'string', 'max:255', $competitorId === null ? $unique : $unique->ignore($competitorId)],
            // Read when the competitor is analysed, so held to the same
            // standard as any other URL Creeper fetches.
            'url' => ['nullable', 'string', 'max:2048', new PublicUrl],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
