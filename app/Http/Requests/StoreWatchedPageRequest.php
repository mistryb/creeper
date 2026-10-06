<?php

namespace App\Http\Requests;

use App\Concerns\WatchedPageValidationRules;
use App\Models\Competitor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWatchedPageRequest extends FormRequest
{
    use WatchedPageValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Competitor $competitor */
        $competitor = $this->route('competitor');

        return [
            ...$this->watchedPageRules($this->user()->id, $competitor->id),

            // Creeping costs money, and Creeper has no key of its own to spend:
            // a new page says which of the user's keys it bills, up front.
            'api_key_id' => ['required', ...$this->apiKeyRules($this->user()->id)],
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
