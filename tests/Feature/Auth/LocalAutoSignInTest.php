<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The suite runs as "testing", so every test that wants the local behaviour
 * has to say so.
 */
function runningLocally(): void
{
    app()->detectEnvironment(fn (): string => 'local');
}

it('creates and signs in an account on a local checkout', function () {
    runningLocally();

    $this->get(route('dashboard'))->assertOk();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->email)->toBe('dev@example.test')
        ->and(Auth::user()->email_verified_at)->not->toBeNull();
});

it('signs in as the configured local address', function () {
    runningLocally();
    config()->set('auth.local_user_email', 'sam@example.test');

    $this->get(route('dashboard'))->assertOk();

    expect(Auth::user()->email)->toBe('sam@example.test');
});

it('reuses the account already in the database', function () {
    runningLocally();
    $user = User::factory()->create();

    $this->get(route('dashboard'))->assertOk();

    expect(Auth::id())->toBe($user->id)
        ->and(User::query()->count())->toBe(1);
});

it('signs in as the first authorized address when the install is restricted', function () {
    runningLocally();
    config()->set('auth.authorized_emails', 'first@example.test, second@example.test');
    User::factory()->create();

    $this->get(route('dashboard'))->assertOk();

    expect(Auth::user()->email)->toBe('first@example.test');
});

it('refuses to sign in as an address the install does not allow', function () {
    runningLocally();
    config()->set('auth.authorized_emails', 'someone@example.test');
    config()->set('auth.local_user_email', 'stranger@example.test');

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse()
        ->and(User::query()->count())->toBe(0);
});

it('leaves the sign-in flow alone', function () {
    runningLocally();

    $this->get(route('login'))->assertOk();

    expect(Auth::check())->toBeFalse()
        ->and(User::query()->count())->toBe(0);
});

it('can be turned off', function () {
    runningLocally();
    config()->set('auth.local_auto_sign_in', false);

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});

it('does nothing outside the local environment', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse()
        ->and(User::query()->count())->toBe(0);
});
