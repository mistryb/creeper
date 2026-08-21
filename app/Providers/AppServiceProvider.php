<?php

namespace App\Providers;

use App\Creeping\Contracts\CreepDriver;
use App\Creeping\CreepManager;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Fortify is still installed but no longer runs anything: its routes
         * are all password-shaped, and authentication here is a one-time code
         * owned by App\Http\Controllers\Auth. See routes/auth.php.
         */
        Fortify::ignoreRoutes();

        $this->app->singleton(CreepManager::class);

        // Anything that needs to creep asks for the contract and gets
        // whichever driver `creeping.driver` names.
        $this->app->bind(
            CreepDriver::class,
            fn ($app): CreepDriver => $app->make(CreepManager::class)->driver(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );
    }

    /**
     * Rate limits for the sign-in flow.
     *
     * These are load bearing rather than housekeeping. A six digit code is
     * only a million combinations, so the thing standing between an attacker
     * and an account is the number of guesses per minute they are allowed.
     * The per-code attempt cap lives in App\Auth\LoginCodes; these limits stop
     * somebody sidestepping it by requesting code after code.
     */
    protected function configureRateLimiting(): void
    {
        // Asking for a code. Limited per address so a mailbox cannot be
        // flooded, and per IP so one host cannot spray many addresses.
        RateLimiter::for('login-code', fn (Request $request): array => [
            Limit::perMinute(3)->by($this->emailKey($request)),
            Limit::perHour(10)->by($this->emailKey($request)),
            Limit::perMinute(15)->by($request->ip()),
        ]);

        // Submitting a code. Deliberately tighter than the per-code cap of
        // five, so guessing is slow even across freshly issued codes.
        RateLimiter::for('login-verify', fn (Request $request): array => [
            Limit::perMinute(5)->by($this->emailKey($request)),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }

    /**
     * A throttle key for the submitted address, falling back to the IP when no
     * address was given, so a malformed request cannot dodge the limit.
     */
    protected function emailKey(Request $request): string
    {
        $email = $request->input('email');

        return is_string($email) && $email !== ''
            ? Str::transliterate(Str::lower($email))
            : (string) $request->ip();
    }
}
