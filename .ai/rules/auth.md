---
paths:
  - 'app/Auth/**'
---

# Auth

## Authentication is an emailed one-time code, and nothing else
There are no passwords, no password reset, no two-factor and no passkeys. Signing in and signing up are one flow: `routes/auth.php` → `App\Http\Controllers\Auth\LoginCodeController` → `App\Auth\LoginCodes`. A new address gets an account when its code is redeemed, with a name guessed by `User::nameFromEmail()`; `User::forEmail()` is the only place accounts are created.

Fortify is still in composer.json but does nothing: `Fortify::ignoreRoutes()` in AppServiceProvider, and `config/fortify.php` features are empty. Do not re-enable a feature to get something done — every one of them assumes a password. `users.password` is a nullable, unused relic and must stay unread.

Three things make a six-digit code safe, and all three have to hold: a 10-minute TTL, destruction after 5 wrong guesses (`LoginCodes::MAX_ATTEMPTS`), and the `login-code`/`login-verify` rate limiters in AppServiceProvider. Never loosen one without tightening another. Codes are stored bcrypt-hashed and are single use. Verification failures must stay one indistinguishable message — saying "expired" vs "wrong" tells an attacker whether an address has a code outstanding.

Sign-in calls `Auth::login($user, remember: true)`, so a browser stays signed in for about a year on the recaller cookie. That is deliberate. Its counterpart is `security.sessions.destroy`, which cycles `remember_token` to revoke other browsers — the only security control this app has.

Because the address is the only credential, changing it goes through `pending_email` and a code sent to the new address (ProfileController::confirmEmail). Never write `users.email` directly from a request.
