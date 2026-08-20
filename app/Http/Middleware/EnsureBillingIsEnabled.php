<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the billing routes entirely on self-hosted installs.
 */
class EnsureBillingIsEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('billing.enabled', false), 404);

        return $next($request);
    }
}
