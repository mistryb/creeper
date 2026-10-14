<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\Competitor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Cashier\Console\WebhookCommand;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /** Where Mailpit serves its inbox during development. */
    protected const MAILPIT_UI_PORT = 8025;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureDevCommands();

        // The admin panel is for the addresses named in ADMIN_EMAILS.
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
    }

    /**
     * Extra panes for `composer run dev` (`php artisan dev`): Mailpit for the
     * mail the app sends, and Stripe's webhook listener when it bills.
     */
    protected function configureDevCommands(): void
    {
        // Scoped to the one command that reads this, so no other Artisan call
        // pays for the lookups below.
        if (! $this->app->runningInConsole() || ($_SERVER['argv'][1] ?? null) !== 'dev') {
            return;
        }

        $this->registerMailpit();

        if ($listen = $this->stripeListenCommand($this->locateBinary('stripe'))) {
            DevCommands::register($listen, 'stripe')->blue();
        }
    }

    /**
     * Add Mailpit to `composer run dev`, so the sign-in codes the application
     * emails are readable in a browser instead of grepped out of the log.
     *
     * Three guards, because this is the one dev process that is not part of the
     * repository: it is skipped unless mail is actually pointed at a local SMTP
     * server, unless Mailpit is installed, and unless the port is free. That
     * last one matters — somebody running `brew services start mailpit` already
     * has it listening, and a second copy would crash-loop in its own pane.
     */
    protected function registerMailpit(): void
    {
        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');

        if (config('mail.default') !== 'smtp') {
            return;
        }

        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], strict: true)) {
            return;
        }

        $mailpit = $this->locateBinary('mailpit');

        if ($mailpit === null || $this->portIsTaken($host, $port)) {
            return;
        }

        DevCommands::register(
            sprintf('%s --smtp %s:%d --listen 127.0.0.1:%d', $mailpit, $host, $port, self::MAILPIT_UI_PORT),
            'mail',
        )->yellow();
    }

    /**
     * The `stripe listen` command that forwards Stripe's events to Cashier's
     * webhook, or null when there is nothing to listen for: a self-hosted
     * install, or no Stripe CLI on this machine.
     *
     * A subscription started on Stripe Checkout only reaches the database by
     * webhook, and so does every change made in the billing portal, so a
     * billed install run locally without this never sees anyone pay. The CLI
     * signs what it forwards with its own secret — `stripe listen
     * --print-secret` — which is the one STRIPE_WEBHOOK_SECRET must hold.
     *
     * Forwards exactly the events `cashier:webhook` subscribes a real
     * endpoint to, so local and production hear the same things.
     */
    public function stripeListenCommand(?string $stripe): ?string
    {
        if ($stripe === null || ! config('billing.enabled')) {
            return null;
        }

        return sprintf(
            '%s listen --forward-to %s --events %s',
            escapeshellarg($stripe),
            escapeshellarg(url(config('cashier.path').'/webhook')),
            implode(',', config('cashier.webhook.events') ?: WebhookCommand::DEFAULT_EVENTS),
        );
    }

    /**
     * Find a binary — Mailpit, the Stripe CLI — without shelling out.
     *
     * The Homebrew paths are checked too: `brew install` links into a
     * directory that is on an interactive shell's PATH but not always on the
     * PATH this process inherited.
     */
    protected function locateBinary(string $name): ?string
    {
        $candidates = [
            ...array_map(
                fn (string $dir): string => rtrim($dir, DIRECTORY_SEPARATOR).'/'.$name,
                explode(PATH_SEPARATOR, (string) getenv('PATH')),
            ),
            "/opt/homebrew/bin/{$name}",
            "/opt/homebrew/opt/{$name}/bin/{$name}",
            "/usr/local/bin/{$name}",
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Whether something is already listening there — somebody running Mailpit
     * as a background service, most likely. A refused connection is the answer
     * we want, so the timeout is short.
     */
    protected function portIsTaken(string $host, int $port): bool
    {
        $socket = @fsockopen($host, $port, $errno, $error, timeout: 0.2);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
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

        // Polymorphic rows store these names, not class names, so a class can
        // be renamed without rewriting any row that points at it.
        Relation::morphMap([
            'business' => Business::class,
            'competitor' => Competitor::class,
        ]);
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
