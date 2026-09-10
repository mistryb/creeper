<?php

namespace App\Auth;

use App\Http\Controllers\Auth\LoginCodeController;
use App\Rules\AuthorizedEmail;
use Illuminate\Support\Str;

/**
 * Who is allowed an account on this install.
 *
 * There is no invitation and no admin screen: an address is let in because it
 * is named in `AUTHORIZED_EMAILS`, and adding somebody is an edit to that
 * variable followed by a redeploy. That is the whole user administration a
 * self-hosted copy has, and it is deliberate — the operator already owns the
 * environment, and anything more would be an account system to maintain.
 *
 * An empty list means no restriction at all, which is how a public install
 * and a local checkout run. Turning the list on is what makes an install
 * private, so the check has to hold everywhere an address becomes an account:
 * sign-in ({@see LoginCodeController}) and changing
 * an existing account's address ({@see AuthorizedEmail}).
 */
class AuthorizedEmails
{
    /**
     * Whether this address may sign in.
     *
     * Unlisted addresses are refused, and an install with an empty list
     * refuses nobody.
     */
    public function allows(string $email): bool
    {
        if ($this->unrestricted()) {
            return true;
        }

        return in_array($this->normalise($email), $this->all(), true);
    }

    /**
     * Whether this install lets any address in.
     */
    public function unrestricted(): bool
    {
        return $this->all() === [];
    }

    /**
     * Every address on the list, lowercased and de-duplicated.
     *
     * Addresses are separated by commas, but any run of whitespace parts them
     * too, so a variable pasted across several lines works the way it looks.
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        $configured = config('auth.authorized_emails');

        if (! is_string($configured) || trim($configured) === '') {
            return [];
        }

        return collect(preg_split('/[\s,]+/', $configured) ?: [])
            ->map(fn (string $email): string => $this->normalise($email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Addresses are compared lowercase, the same way {@see LoginCodes} does,
     * so the case somebody types is never the reason they are turned away.
     */
    protected function normalise(string $email): string
    {
        return Str::lower(trim($email));
    }
}
