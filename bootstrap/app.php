<?php

use App\Http\Middleware\AuthenticateLocalUser;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['sidebar_state']);

        // The creep callback authenticates itself by its signed URL.
        $middleware->validateCsrfTokens(except: [
            'webhooks/creep/*',
        ]);

        $middleware->web(append: [
            // Local checkouts sign themselves in; see the middleware.
            AuthenticateLocalUser::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        /*
         * `auth` is a prioritised middleware, so without this it would run —
         * and redirect — before anything merely appended to the web group.
         */
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: AuthenticateLocalUser::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
