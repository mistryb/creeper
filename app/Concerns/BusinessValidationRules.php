<?php

namespace App\Concerns;

use App\Models\Business;
use App\Rules\PublicUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait BusinessValidationRules
{
    /**
     * The most a description may run to. Generous enough for a proper account
     * of a business, short enough to be read in full on every creep.
     */
    protected const DESCRIPTION_MAX = 5000;

    /**
     * Get the validation rules used to validate a business.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function businessRules(int $userId, ?int $businessId = null): array
    {
        $unique = Rule::unique(Business::class, 'name')->where('user_id', $userId);

        return [
            // Two businesses with one name could not be told apart in a list.
            'name' => ['required', 'string', 'max:255', $businessId === null ? $unique : $unique->ignore($businessId)],
            // Held to the same standard as a watched page's URL, because the
            // business's own site may be fetched and read later.
            'url' => ['nullable', 'string', 'max:2048', new PublicUrl],
            'description' => ['required', 'string', 'max:'.self::DESCRIPTION_MAX],
        ];
    }
}
