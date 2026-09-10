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

## AUTHORIZED_EMAILS is the whole user administration
`AUTHORIZED_EMAILS` (config `auth.authorized_emails`, read by `App\Auth\AuthorizedEmails`) names every address allowed an account. Empty means no restriction, which is how local and public installs run. Adding somebody is an edit to the variable and a redeploy — there is no invite flow and no admin screen, deliberately.

Because sign-in and sign-up are one act, the check has to hold everywhere an address becomes an account: `LoginCodeController::store`/`update`, and `App\Rules\AuthorizedEmail` in `ProfileValidationRules::emailRules()` for an address change.

An unlisted address must never learn it is unlisted. `store` skips sending the code and returns the identical redirect and message; `update` fails with the same "That code is not valid" as every other verification failure. Saying "not authorized" there would publish the list one address at a time. The signed-in profile screen is the one place that can say it plainly.

The gate is on sign-in only. Removing an address does not sign out a browser already holding a remember cookie — cycle `remember_token` via security.sessions.destroy for that.
