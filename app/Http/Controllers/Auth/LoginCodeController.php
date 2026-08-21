<?php

namespace App\Http\Controllers\Auth;

use App\Auth\LoginCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestLoginCodeRequest;
use App\Http\Requests\Auth\VerifyLoginCodeRequest;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The whole way in.
 *
 * There is no password and no sign-up form: you give an address, we send a
 * six digit code, you type it back. If the address has no account, verifying
 * the code creates one — which is also what proves the address is real, so
 * these accounts are verified from birth.
 *
 * The account is created on verification, never on request. Otherwise anyone
 * could fill the users table with addresses they do not control.
 */
class LoginCodeController extends Controller
{
    /** Where the address awaiting a code is remembered between requests. */
    protected const PENDING_KEY = 'login.email';

    public function __construct(protected LoginCodes $codes) {}

    /**
     * Ask for an address.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'email' => $request->session()->get(self::PENDING_KEY),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Send a code to that address.
     */
    public function store(RequestLoginCodeRequest $request): RedirectResponse
    {
        $email = $request->email();

        Notification::route('mail', $email)
            ->notify(new LoginCodeNotification($this->codes->issue($email)));

        /*
         * Held in the session only so the next screen can prefill and offer a
         * resend. The code itself is checked against whatever address is
         * submitted with it, so losing this does not strand anybody.
         */
        $request->session()->put(self::PENDING_KEY, $email);

        return to_route('login.verify')
            ->with('status', __('We sent a code to :email.', ['email' => $email]));
    }

    /**
     * Ask for the code.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('auth/verify-code', [
            // A code can be typed on a different device from the one that
            // asked for it, so the address is editable when we do not know it.
            'email' => $request->session()->get(self::PENDING_KEY) ?? $request->query('email'),
            'status' => $request->session()->get('status'),
            'expiresInMinutes' => LoginCodes::TTL_MINUTES,
        ]);
    }

    /**
     * Check the code and sign the visitor in.
     */
    public function update(VerifyLoginCodeRequest $request): RedirectResponse
    {
        $email = $request->email();

        if (! $this->codes->verify($email, $request->code())) {
            /*
             * One message for every kind of failure — wrong, expired, spent,
             * or never issued. Saying which would tell an attacker whether an
             * address has a code outstanding.
             */
            throw ValidationException::withMessages([
                'code' => __('That code is not valid. Ask for a new one.'),
            ]);
        }

        // Creates the account if this address is new, and marks it verified
        // either way — redeeming a code is proof of control over the address.
        $user = User::forEmail($email);

        // `remember` is what keeps this browser signed in afterwards.
        Auth::login($user, remember: true);

        $request->session()->regenerate();
        $request->session()->forget(self::PENDING_KEY);

        return redirect()->intended(route('dashboard'));
    }
}
