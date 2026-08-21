<?php

namespace App\Auth;

use App\Models\LoginCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Issues and checks the one-time codes that are the only way into this
 * application.
 *
 * Six digits is a million combinations, which is not many. Three things make
 * that safe rather than reckless, and all three have to hold:
 *
 *   1. A code lasts ten minutes.
 *   2. A code dies after five wrong guesses, so an attacker gets five tries
 *      per issued code rather than five tries per minute forever.
 *   3. The routes that call this are rate limited per address and per IP.
 *
 * Only one code is outstanding per address. Asking for another replaces it,
 * which is also how "resend" works — an older email stops being useful the
 * moment a newer one is sent.
 */
class LoginCodes
{
    /** How long a code stays good for. */
    public const TTL_MINUTES = 10;

    /** Wrong guesses a single code tolerates before it is destroyed. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Issue a fresh code for an address and return it in the clear.
     *
     * The plaintext is returned once, to be put in an email, and never stored.
     */
    public function issue(string $email): string
    {
        $code = $this->generate();

        LoginCode::query()->updateOrCreate(
            ['email' => $this->normalise($email)],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'created_at' => now(),
            ],
        );

        return $code;
    }

    /**
     * Check a code against an address, consuming it if it matches.
     *
     * Returns false for every kind of failure — no code, wrong code, expired,
     * too many guesses — so a caller cannot accidentally tell an attacker
     * which of those it was.
     */
    public function verify(string $email, string $code): bool
    {
        $record = LoginCode::query()->find($this->normalise($email));

        if ($record === null) {
            return false;
        }

        if ($record->isExpired() || $record->attempts >= self::MAX_ATTEMPTS) {
            $record->delete();

            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            // Spend the last guess and the code is gone, not merely refused.
            if ($record->attempts >= self::MAX_ATTEMPTS) {
                $record->delete();
            }

            return false;
        }

        // Single use: a correct code is spent, even if the request that used
        // it goes on to fail for some other reason.
        $record->delete();

        return true;
    }

    /**
     * Throw away any outstanding code for an address.
     */
    public function forget(string $email): void
    {
        LoginCode::query()->whereKey($this->normalise($email))->delete();
    }

    /**
     * Delete codes nobody can use any more. Called from the scheduler.
     */
    public function purgeExpired(): int
    {
        return LoginCode::query()->expired()->delete();
    }

    /**
     * A six digit code, zero padded, from a cryptographically secure source.
     */
    protected function generate(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Addresses are compared lowercase, so the code sent to "Jane@x.com"
     * can be redeemed by someone who types "jane@x.com".
     */
    protected function normalise(string $email): string
    {
        return Str::lower(trim($email));
    }
}
