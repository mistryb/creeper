<?php

namespace App\Http\Requests;

use App\Concerns\CreepTargetValidationRules;
use App\Enums\TargetStatus;
use App\Models\CreepTarget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreepTargetRequest extends FormRequest
{
    use CreepTargetValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var CreepTarget $target */
        $target = $this->route('creep_target');

        return [
            ...$this->creepTargetRules($this->user()->id, $target->id),
            'status' => ['sometimes', Rule::enum(TargetStatus::class)],
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
