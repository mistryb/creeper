<?php

namespace App\Http\Requests;

use App\Concerns\WatchedPageValidationRules;
use App\Enums\PageStatus;
use App\Models\WatchedPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWatchedPageRequest extends FormRequest
{
    use WatchedPageValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var WatchedPage $watchedPage */
        $watchedPage = $this->route('watched_page');

        return [
            ...$this->watchedPageRules($this->user()->id, $watchedPage->competitor_id, $watchedPage->id),
            'status' => ['sometimes', Rule::enum(PageStatus::class)],
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
