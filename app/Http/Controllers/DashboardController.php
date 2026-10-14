<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Where signing in lands: the digests about the company picked in the
     * header, or, before there is a company, the way to set one up.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $business = $request->user()->selectedBusiness();

        if ($business === null) {
            return Inertia::render('dashboard');
        }

        return to_route('businesses.digests.index', $business);
    }
}
