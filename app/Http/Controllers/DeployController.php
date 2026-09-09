<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * How to get your own copy of Creeper running on Laravel Cloud.
 *
 * This is marketing collateral, so it lives or dies with the landing page that
 * links to it: an install with no public face has no one to hand a deploy
 * prompt to. The flag is read per request for the same reason as in
 * HomeController — `route:cache` would otherwise bake the decision in.
 */
class DeployController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(config('marketing.enabled'), 404);

        return Inertia::render('deploy');
    }
}
