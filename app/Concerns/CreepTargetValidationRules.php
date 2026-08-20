<?php

namespace App\Concerns;

use App\Enums\CreepFrequency;
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
            'frequency' => ['required', Rule::enum(CreepFrequency::class)],
            'notify_on_change' => ['boolean'],
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
