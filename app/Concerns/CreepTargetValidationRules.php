<?php

namespace App\Concerns;

use App\Enums\CreepFrequency;
use App\Models\ApiKey;
use App\Models\CreepTarget;
use App\Rules\PublicUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CreepTargetValidationRules
{
    /**
     * Get the validation rules used to validate creep targets.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function creepTargetRules(int $userId, ?int $targetId = null): array
    {
        return [
            'url' => $this->urlRules($userId, $targetId),
            'name' => ['nullable', 'string', 'max:255'],
            'api_key_id' => ['sometimes', 'required', ...$this->apiKeyRules($userId)],
            'frequency' => ['required', Rule::enum(CreepFrequency::class)],
            'notify_on_change' => ['boolean'],
        ];
    }

    /**
     * Get the validation rules used to validate the key a target spends.
     *
     * A key can only be one of the user's own, which is what stops a target
     * being pointed at somebody else's credit.
     *
     * Whether one has to be given is the caller's call: creating a target
     * requires a key, while updating one is `sometimes`, for the same reason
     * `status` is — the settings form submits the field, but a request that
     * leaves it out must leave the target on the key it already has.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function apiKeyRules(int $userId): array
    {
        return [
            'integer',
            Rule::exists(ApiKey::class, 'id')->where('user_id', $userId),
        ];
    }

    /**
     * Get the validation rules used to validate a creep target's URL.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function urlRules(int $userId, ?int $targetId = null): array
    {
        $unique = Rule::unique(CreepTarget::class, 'url')->where('user_id', $userId);

        return [
            'required',
            'string',
            'max:2048',
            new PublicUrl,
            $targetId === null ? $unique : $unique->ignore($targetId),
        ];
    }
}
