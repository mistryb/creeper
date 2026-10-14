<?php

namespace App\Concerns;

use App\Enums\DigestFrequency;
use App\Models\ApiKey;
use App\Models\Business;
use App\Rules\PublicUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait BusinessValidationRules
{
    /**
     * The most a description may run to. Generous enough for a proper account
     * of a company, short enough to be read in full on every digest.
     */
    protected const DESCRIPTION_MAX = 5000;

    /**
     * Get the validation rules used to validate a business.
     *
     * The key is required when a company is set up — discovery runs straight
     * away and has to spend something — and `sometimes` after, so a form that
     * omits it leaves the company on the key it has.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function businessRules(int $userId, ?int $businessId = null): array
    {
        $unique = Rule::unique(Business::class, 'name')->where('user_id', $userId);

        return [
            // Two companies with one name could not be told apart in a list.
            'name' => ['required', 'string', 'max:255', $businessId === null ? $unique : $unique->ignore($businessId)],
            // Read by discovery, so held to the same standard as any other
            // URL Creeper fetches.
            'url' => ['nullable', 'string', 'max:2048', new PublicUrl],
            'description' => ['required', 'string', 'max:'.self::DESCRIPTION_MAX],
            'digest_frequency' => ['required', Rule::enum(DigestFrequency::class)],
            'api_key_id' => [
                $businessId === null ? 'required' : 'sometimes',
                'integer',
                Rule::exists(ApiKey::class, 'id')->where('user_id', $userId),
            ],
        ];
    }
}
