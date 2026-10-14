<?php

namespace App\Auth;

use Illuminate\Support\Str;

/**
 * Who may open the admin panel: the addresses named in `ADMIN_EMAILS`.
 *
 * The opposite default to {@see AuthorizedEmails}: an empty list lets nobody
 * in, so an install is never one forgotten variable away from handing every
 * account the panel.
 */
class AdminEmails
{
    /**
     * Whether this address is an admin.
     */
    public function includes(string $email): bool
    {
        return in_array(Str::lower(trim($email)), $this->all(), true);
    }

    /**
     * Every admin address, lowercased. Commas or any run of whitespace part
     * them, the same way the authorized list is read.
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        $configured = config('auth.admin_emails');

        if (! is_string($configured) || trim($configured) === '') {
            return [];
        }

        return collect(preg_split('/[\s,]+/', $configured) ?: [])
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
