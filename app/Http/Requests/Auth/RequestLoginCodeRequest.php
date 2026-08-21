<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Asking for a code. The address is not checked against the users table:
 * signing in and signing up are the same act here, and telling the visitor
 * whether an address is registered would hand that fact to anyone who asks.
 */
class RequestLoginCodeRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->string('email')->value()))]);
        }
    }

    public function email(): string
    {
        return $this->string('email')->value();
    }
}
