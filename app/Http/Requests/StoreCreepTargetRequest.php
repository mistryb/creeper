<?php

namespace App\Http\Requests;

use App\Billing\PlanLimits;
use App\Concerns\CreepTargetValidationRules;
use App\Enums\CreepFrequency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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
     * Plan limits are validation failures, not authorization failures — the
     * user should see why on the form rather than get a blank 403.
     *
     * @return array<int, callable>
     */
    public function after(PlanLimits $limits): array
    {
        return [
            function (Validator $validator) use ($limits): void {
                $user = $this->user();

                if (! $limits->canAddTarget($user)) {
                    $validator->errors()->add('url', sprintf(
                        'Your plan covers %d creep targets. Upgrade or remove one to add another.',
                        (int) $limits->targetLimit($user),
                    ));
                }

                $frequency = CreepFrequency::tryFrom((string) $this->input('frequency'));

                if ($frequency !== null && ! $limits->allowsFrequency($user, $frequency)) {
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
