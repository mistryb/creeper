<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * There is no password to ask for, so deleting an account is confirmed by
 * typing its address. That is not a secret — it is a speed bump, to make
 * destroying everything a deliberate act rather than a misplaced click.
 */
class ProfileDeleteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', Rule::in([$this->user()->email])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.in' => __('That is not the email address on this account.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim($this->string('email')->value()))]);
    }
}
