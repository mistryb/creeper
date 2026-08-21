<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * There is no password to change and no second factor to enrol, so the only
 * thing this screen does is the one thing a very long-lived cookie makes
 * necessary: revoke every other browser that is still signed in.
 */
class SecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/security', [
            'otherSessions' => $this->otherSessions($request),
        ]);
    }

    /**
     * Sign out every browser except this one.
     *
     * Laravel keeps one remember token per user, so replacing it is what
     * actually invalidates the long-lived cookies elsewhere; deleting the
     * session rows only closes sessions that have not yet expired. This
     * browser is then signed back in against the new token.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Signed out everywhere else.'),
        ]);

        return to_route('security.edit');
    }

    /**
     * How many other browsers hold a live session for this user. Sessions are
     * only part of the picture — a browser whose session has lapsed can still
     * return using its remember cookie — so the screen words this carefully.
     */
    protected function otherSessions(Request $request): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->count();
    }
}
