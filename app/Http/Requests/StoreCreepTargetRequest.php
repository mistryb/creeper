<?php

namespace App\Http\Requests;

use App\Concerns\CreepTargetValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        return $this->creepTargetRules($this->user()->id);
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
