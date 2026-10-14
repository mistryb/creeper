<?php

use App\Enums\Plan;
use App\Jobs\RunDigest;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Cashier\Console\WebhookCommand;

describe('a self-hosted install', function () {
    it('puts no limit on companies', function () {
        $user = User::factory()->create();
        Business::factory()->for($user)->count(3)->create();

        expect($user->businessLimit())->toBeNull()
            ->and($user->canAddBusiness())->toBeTrue();
    });

    it('starts no trial', function () {
        expect(User::forEmail('new@example.com')->trial_ends_at)->toBeNull();
    });

    it('has no billing screen', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('billing.edit'))
            ->assertNotFound();
    });

    it('tells the front end there is nothing to pay for', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('billing', null));
    });
});

it('turns billing on only once the secret and both prices are configured', function () {
    $enabled = fn (array $env): bool => (function () use ($env) {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }

        return (require config_path('billing.php'))['enabled'];
    })();

    expect($enabled(['STRIPE_SECRET' => '', 'STRIPE_PRICE_SINGLE' => 'a', 'STRIPE_PRICE_UNLIMITED' => 'b']))->toBeFalse()
        ->and($enabled(['STRIPE_SECRET' => 'sk_test', 'STRIPE_PRICE_SINGLE' => '', 'STRIPE_PRICE_UNLIMITED' => 'b']))->toBeFalse()
        ->and($enabled(['STRIPE_SECRET' => 'sk_test', 'STRIPE_PRICE_SINGLE' => 'a', 'STRIPE_PRICE_UNLIMITED' => 'b']))->toBeTrue();

    foreach (['STRIPE_SECRET', 'STRIPE_PRICE_SINGLE', 'STRIPE_PRICE_UNLIMITED'] as $key) {
        putenv("{$key}=");
        $_ENV[$key] = '';
    }
});

describe('a billed install', function () {
    beforeEach(fn () => enableBilling());

    it('starts a new account on a seven day trial', function () {
        Carbon::setTestNow('2026-10-14 12:00:00');

        $user = User::forEmail('new@example.com');

        expect($user->trial_ends_at->toDateTimeString())->toBe('2026-10-21 12:00:00')
            ->and($user->businessLimit())->toBe(1);
    });

    it('does not restart the trial when an account signs in again', function () {
        $user = User::factory()->create(['email' => 'back@example.com', 'trial_ends_at' => now()->subDay()]);

        expect(User::forEmail('back@example.com')->trial_ends_at->toDateTimeString())
            ->toBe($user->trial_ends_at->toDateTimeString());
    });

    it('works out how many companies each kind of account covers', function (Closure $setUp, ?int $limit) {
        $user = User::factory()->create();
        $setUp($user);

        expect($user->fresh()->businessLimit())->toBe($limit);
    })->with([
        'on trial' => [fn (User $user) => $user->forceFill(['trial_ends_at' => now()->addDays(3)])->save(), 1],
        'trial ended' => [fn (User $user) => $user->forceFill(['trial_ends_at' => now()->subDay()])->save(), 0],
        'one company' => [fn (User $user) => subscribe($user, Plan::Single), 1],
        'unlimited' => [fn (User $user) => subscribe($user, Plan::Unlimited), null],
        'comped' => [fn (User $user) => $user->forceFill(['comped_at' => now()])->save(), null],
        'cancelled, still paid up' => [fn (User $user) => subscribe($user, Plan::Unlimited, ['ends_at' => now()->addWeek()]), null],
        'cancelled and ended' => [fn (User $user) => subscribe($user, Plan::Unlimited, ['stripe_status' => 'canceled', 'ends_at' => now()->subDay()]), 0],
        'an unknown price' => [fn (User $user) => subscribe($user, Plan::Single, ['stripe_price' => 'price_retired']), 0],
    ]);

    it('lets a trial set up its first company', function () {
        $user = User::factory()->create(['trial_ends_at' => now()->addWeek()]);

        $this->actingAs($user)
            ->get(route('businesses.create'))
            ->assertOk();
    });

    it('sends an account with no room left to pick a plan instead of the form', function () {
        $user = User::factory()->create(['trial_ends_at' => now()->addWeek()]);
        Business::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('businesses.create'))
            ->assertRedirect(route('billing.edit'));
    });

    it('refuses to set up a company past the limit', function () {
        $user = User::factory()->create();
        subscribe($user, Plan::Single);
        Business::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('businesses.store'), [
                'name' => 'Second',
                'description' => 'Another company to keep tabs on.',
                'digest_frequency' => 'weekly',
                'api_key_id' => ApiKey::factory()->for($user)->create()->id,
            ])
            ->assertForbidden();

        expect($user->businesses()->count())->toBe(1);
    });

    it('covers only the earliest companies an account set up', function () {
        $user = User::factory()->create();
        subscribe($user, Plan::Single);
        [$first, $second] = Business::factory()->for($user)->count(2)->create();

        expect($user->covers($first))->toBeTrue()
            ->and($user->covers($second))->toBeFalse()
            ->and($user->covers(Business::factory()->create()))->toBeFalse();
    });

    it('only sends scheduled digests for companies a plan covers', function () {
        Queue::fake();

        $paying = User::factory()->create();
        subscribe($paying, Plan::Single);
        $covered = Business::factory()->for($paying)->withKey()->due()->create();
        $over = Business::factory()->for($paying)->withKey()->due()->create();

        $lapsed = User::factory()->create(['trial_ends_at' => now()->subDay()]);
        $paused = Business::factory()->for($lapsed)->withKey()->due()->create();

        $this->artisan('creeper:dispatch-due-digests')->expectsOutputToContain('Queued 1 digest.');

        expect($covered->digests()->count())->toBe(1)
            ->and($over->digests()->count())->toBe(0)
            ->and($paused->digests()->count())->toBe(0)
            // Left due, so it goes out as soon as the account is paid up.
            ->and($paused->fresh()->next_digest_at->isPast())->toBeTrue();

        Queue::assertPushed(RunDigest::class, 1);
    });

    it('will not write a digest or look for competitors on demand for an uncovered company', function (string $route) {
        Queue::fake();

        $user = User::factory()->create(['trial_ends_at' => now()->subDay()]);
        $business = Business::factory()->for($user)->withKey()->create();

        $this->actingAs($user)
            ->post(route($route, $business))
            ->assertRedirect(route('billing.edit'));

        Queue::assertNothingPushed();
    })->with(['businesses.digests.store', 'businesses.discovery.store']);

    it('shares where the account stands on every page', function () {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $user = User::factory()->create(['trial_ends_at' => now()->addDays(3)]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('billing.plan', null)
                ->where('billing.trialEndsAt', '2026-10-17T12:00:00+00:00')
                ->where('billing.needsPlan', false));
    });

    it('says when digests have stopped for want of a plan', function () {
        $this->actingAs(User::factory()->create(['trial_ends_at' => now()->subDay()]))
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('billing.needsPlan', true));
    });

    it('shows the billing screen', function () {
        $user = User::factory()->create();
        subscribe($user, Plan::Single);
        Business::factory()->for($user)->count(2)->create();

        $this->actingAs($user)
            ->get(route('billing.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/billing')
                ->has('plans', 2)
                ->where('plans.0.price', 5)
                ->where('plans.1.price', 20)
                ->where('plans.1.businessLimit', null)
                ->where('plan', 'single')
                ->where('businessCount', 2)
                ->where('businessLimit', 1)
                ->where('justCheckedOut', false));
    });

    it('does not start a second subscription for an account that has one', function () {
        $user = User::factory()->create();
        subscribe($user, Plan::Single);

        $this->actingAs($user)
            ->post(route('billing.checkout', 'unlimited'))
            ->assertRedirect(route('billing.edit'));

        expect($user->subscriptions()->count())->toBe(1);
    });

    it('only sells the plans it has', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('billing.checkout', 'enterprise'))
            ->assertNotFound();
    });

    it('has nothing to switch for an account without a subscription', function () {
        $this->actingAs(User::factory()->create())
            ->put(route('billing.update', 'unlimited'))
            ->assertRedirect(route('billing.edit'));
    });

    it('has no portal for an account Stripe has never seen', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('billing.portal'))
            ->assertNotFound();
    });
});

it('404s every billing action on a self-hosted install', function (string $method, string $route, array $parameters) {
    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, $parameters))
        ->assertNotFound();
})->with([
    ['get', 'billing.edit', []],
    ['post', 'billing.checkout', ['single']],
    ['put', 'billing.update', ['single']],
    ['post', 'billing.portal', []],
]);

describe('the Stripe listener in php artisan dev', function () {
    $listen = fn (?string $stripe): ?string => app()
        ->getProvider(AppServiceProvider::class)
        ->stripeListenCommand($stripe);

    it('forwards the events Cashier handles to its webhook', function () use ($listen) {
        enableBilling();
        config(['app.url' => 'http://localhost:8001']);

        expect($listen('/opt/homebrew/bin/stripe'))
            ->toStartWith("'/opt/homebrew/bin/stripe' listen")
            ->toContain("--forward-to 'http://localhost:8001/stripe/webhook'")
            ->toContain('--events '.implode(',', WebhookCommand::DEFAULT_EVENTS));
    });

    it('is not started on a self-hosted install', function () use ($listen) {
        expect($listen('/opt/homebrew/bin/stripe'))->toBeNull();
    });

    it('is not started without the Stripe CLI', function () use ($listen) {
        enableBilling();

        expect($listen(null))->toBeNull();
    });
});
