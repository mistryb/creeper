<?php

namespace App\Http\Middleware;

use App\Auth\AuthorizedEmails;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign a local checkout in without asking for a code.
 *
 * Signing in means waiting on an email, and on a machine that is only ever
 * used by the person sitting at it that is friction with nothing behind it.
 * So on a local checkout the account named by {@see resolveEmail()} is created
 * on first request and signed in, exactly as if a code had been redeemed.
 *
 * Two things keep this from being a hole. It is confined to the `local`
 * environment, which a deployed install never runs as, and the address still
 * has to pass {@see AuthorizedEmails} — a restricted install auto-signs in as
 * somebody already on its list or not at all. Set `LOCAL_AUTO_SIGN_IN=false`
 * to exercise the real sign-in flow instead.
 */
class AuthenticateLocalUser
{
    /**
     * The account created when nothing else names one.
     */
    protected const FALLBACK_EMAIL = 'dev@example.test';

    /**
     * Paths left alone, so that the sign-in flow and the creep callback still
     * behave the way they do everywhere else.
     *
     * @var array<int, string>
     */
    protected const EXCEPT = ['login', 'login/*', 'logout', 'webhooks/*'];

    public function __construct(protected AuthorizedEmails $authorizedEmails) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSignIn($request)) {
            $this->signIn();
        }

        return $next($request);
    }

    /**
     * Whether this request should be handed an account.
     */
    protected function shouldSignIn(Request $request): bool
    {
        if (! app()->environment('local') || ! config('auth.local_auto_sign_in')) {
            return false;
        }

        if (Auth::check() || $request->is(...self::EXCEPT)) {
            return false;
        }

        return true;
    }

    /**
     * Create the local account if it is not there yet and sign it in.
     *
     * The remember cookie is deliberately left off: the session is enough for
     * a checkout, and an unremembered browser is one fewer thing that outlives
     * turning this off.
     */
    protected function signIn(): void
    {
        $email = $this->resolveEmail();

        if ($email === null || ! $this->authorizedEmails->allows($email)) {
            return;
        }

        Auth::login(User::forEmail($email));
    }

    /**
     * Which address to sign in as.
     *
     * `LOCAL_USER_EMAIL` wins when it is set. Otherwise a restricted install
     * uses the first address it allows, so the account that appears is one the
     * install would have let in anyway; an unrestricted one reuses whichever
     * account is already in the database before falling back to inventing one,
     * so a checkout that has been signed into for real keeps its own account.
     */
    protected function resolveEmail(): ?string
    {
        $configured = config('auth.local_user_email');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        if (! $this->authorizedEmails->unrestricted()) {
            return $this->authorizedEmails->all()[0] ?? null;
        }

        return User::query()->oldest('id')->value('email') ?? self::FALLBACK_EMAIL;
    }
}
