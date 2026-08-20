<?php

namespace App\Http\Requests;

use App\Billing\PlanLimits;
use App\Concerns\CreepTargetValidationRules;
use App\Enums\CreepFrequency;
use App\Enums\TargetStatus;
use App\Models\CreepTarget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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
            'status' => ['required', Rule::enum(TargetStatus::class)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(PlanLimits $limits): array
    {
        return [
            function (Validator $validator) use ($limits): void {
                $frequency = CreepFrequency::tryFrom((string) $this->input('frequency'));

                if ($frequency !== null && ! $limits->allowsFrequency($this->user(), $frequency)) {
                    $validator->errors()->add('frequency', 'Your plan does not include creeping that often.');
                }
            },
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
