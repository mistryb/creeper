<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Submitting a code. The address travels with it, so a code requested on a
 * laptop can be typed on a phone.
 */
class VerifyLoginCodeRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255'],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim($this->string('email')->value())),
            // Codes get pasted out of emails with spaces in them.
            'code' => preg_replace('/\D/', '', $this->string('code')->value()),
        ]);
    }

    public function email(): string
    {
        return $this->string('email')->value();
    }

    public function code(): string
    {
        return $this->string('code')->value();
    }
}
