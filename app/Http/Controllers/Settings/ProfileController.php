<?php

namespace App\Http\Controllers\Settings;

use App\Auth\LoginCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ConfirmEmailChangeRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Notifications\LoginCodeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(protected LoginCodes $codes) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'pendingEmail' => $request->user()->pending_email,
            'expiresInMinutes' => LoginCodes::TTL_MINUTES,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     *
     * A new address is parked in `pending_email` and a code is sent to it. It
     * only becomes the account's address once that code comes back, because an
     * address is the only way into this account and an unverified one would be
     * a locked door.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->name = $request->validated('name');

        $email = $request->validated('email');
        $changing = $email !== $user->email;

        if ($changing) {
            $user->pending_email = $email;
        }

        $user->save();

        if ($changing) {
            Notification::route('mail', $email)
                ->notify(new LoginCodeNotification($this->codes->issue($email)));

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Enter the code we sent to :email to finish the change.', ['email' => $email]),
            ]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);
        }

        return to_route('profile.edit');
    }

    /**
     * Confirm a pending address change with the code sent to it.
     */
    public function confirmEmail(ConfirmEmailChangeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $pending = $user->pending_email;

        if ($pending === null) {
            return to_route('profile.edit');
        }

        if (! $this->codes->verify($pending, $request->code())) {
            throw ValidationException::withMessages([
                'code' => __('That code is not valid. Ask for a new one.'),
            ]);
        }

        /*
         * The address is only claimed here, at the point it is proved. If
         * somebody else has taken it in the meantime the change is abandoned
         * rather than colliding with the unique index.
         */
        if ($user->newQuery()->where('email', $pending)->exists()) {
            $user->forceFill(['pending_email' => null])->save();

            throw ValidationException::withMessages([
                'code' => __('That address now belongs to another account.'),
            ]);
        }

        $user->forceFill([
            'email' => $pending,
            'pending_email' => null,
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email address updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Abandon a pending address change.
     */
    public function cancelEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->pending_email !== null) {
            $this->codes->forget($user->pending_email);

            $user->forceFill(['pending_email' => null])->save();
        }

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
