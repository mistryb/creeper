<?php

namespace App\Http\Requests;

use App\Concerns\CreepTargetValidationRules;
use App\Enums\CreepType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreepTargetRequest extends FormRequest
{
    use CreepTargetValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The one setting a target cannot change later: its readings are
            // filed in a table per type, so switching would strand them.
            'type' => ['required', Rule::enum(CreepType::class)],
            ...$this->creepTargetRules($this->user()->id),
        ];
    }

    /**
     * An unchecked checkbox isn't submitted at all, so without this the box
     * could be ticked but never unticked.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'notify_on_change' => $this->boolean('notify_on_change'),
        ]);
    }
}
