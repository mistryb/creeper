<?php

namespace App\Rules;

use App\Auth\AuthorizedEmails;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects an address this install has not been told to let in.
 *
 * Used where somebody signed in is naming an address for their own account,
 * so the failure can say plainly what is wrong. The sign-in form must not use
 * this: telling a stranger whether an address is on the list would publish
 * the list to anybody who asks.
 */
class AuthorizedEmail implements ValidationRule
{
    public function __construct(protected AuthorizedEmails $authorized = new AuthorizedEmails) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (! $this->authorized->allows($value)) {
            $fail(__('That address is not authorized to use this Creeper.'));
        }
    }
}
