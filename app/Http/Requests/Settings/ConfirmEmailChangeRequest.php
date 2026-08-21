<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The code sent to a pending new address. The address itself is not submitted
 * — it is read from the account, so a caller cannot aim the confirmation at
 * some other address.
 */
class ConfirmEmailChangeRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => preg_replace('/\D/', '', $this->string('code')->value()),
        ]);
    }

    public function code(): string
    {
        return $this->string('code')->value();
    }
}
