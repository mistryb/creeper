<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page — but only where there is an audience for one.
 *
 * With marketing mode off, everyone who arrives at "/" is someone who already
 * has an account, so the front door is the sign-in page and the landing page
 * is not served at all. The flag is read here, per request, rather than around
 * the route in routes/web.php: `route:cache` would bake that decision into the
 * cached route table and the environment variable would stop meaning anything.
 */
class HomeController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        if (! config('marketing.enabled')) {
            // Signed in already? The `guest` middleware on the sign-in page
            // sends them along to the dashboard from there.
            return to_route('login');
        }

        // A billed install sells its plans; a self-hosted one has none to
        // sell, and the page pitches running your own copy instead.
        return Inertia::render('welcome', [
            'plans' => config('billing.enabled') ? Plan::options() : null,
            'trialDays' => config('billing.trial_days'),
        ]);
    }
}
