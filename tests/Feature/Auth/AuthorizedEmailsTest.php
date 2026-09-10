<?php

use App\Auth\AuthorizedEmails;
use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Notification::fake();
    RateLimiter::clear('login-code');
});

/** Put an install behind an allow list. */
function authorizeEmails(string $emails): void
{
    config(['auth.authorized_emails' => $emails]);
}

it('lets any address in when no list is configured', function () {
    authorizeEmails('');

    expect(app(AuthorizedEmails::class)->unrestricted())->toBeTrue()
        ->and(app(AuthorizedEmails::class)->allows('anyone@example.com'))->toBeTrue();
});

it('reads a list separated by commas, spaces or newlines', function () {
    authorizeEmails("jane@example.com, JOHN@example.com\n  jane@example.com ");

    $authorized = app(AuthorizedEmails::class);

    expect($authorized->all())->toBe(['jane@example.com', 'john@example.com'])
        ->and($authorized->allows('Jane@Example.com'))->toBeTrue()
        ->and($authorized->allows('nobody@example.com'))->toBeFalse();
});

it('emails a code to an address on the list', function () {
    authorizeEmails('jane@example.com');

    $this->post(route('login.store'), ['email' => 'jane@example.com'])
        ->assertRedirect(route('login.verify'))
        ->assertSessionHasNoErrors();

    Notification::assertSentOnDemandTimes(LoginCodeNotification::class, 1);

    expect(LoginCode::query()->find('jane@example.com'))->not->toBeNull();
});

/*
 * An unlisted address must not be able to tell it is unlisted, or the variable
 * becomes a list anybody can read one address at a time. Same redirect, same
 * message, no code and no email.
 */
it('sends nothing to an address that is not on the list, and says nothing about it', function () {
    authorizeEmails('jane@example.com');

    $this->post(route('login.store'), ['email' => 'stranger@example.com'])
        ->assertRedirect(route('login.verify'))
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();

    expect(LoginCode::query()->find('stranger@example.com'))->toBeNull();
});

it('refuses to sign in an unlisted address even holding a valid code', function () {
    LoginCode::factory()->forEmail('stranger@example.com', '123456')->create();

    authorizeEmails('jane@example.com');

    $this->from(route('login.verify'))
        ->post(route('login.verify.store'), ['email' => 'stranger@example.com', 'code' => '123456'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('signs in an address on the list', function () {
    authorizeEmails('jane@example.com, john@example.com');

    LoginCode::factory()->forEmail('jane@example.com', '123456')->create();

    $this->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => '123456'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::query()->sole());
});

/*
 * Taking somebody off the list is how they are removed, so an account that
 * already exists is no longer a way in once its address is gone.
 */
it('turns away an existing account whose address has left the list', function () {
    $user = User::factory()->create(['email' => 'former@example.com']);

    authorizeEmails('jane@example.com');

    LoginCode::factory()->forEmail('former@example.com', '123456')->create();

    $this->from(route('login.verify'))
        ->post(route('login.verify.store'), ['email' => 'former@example.com', 'code' => '123456'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
    expect($user->fresh())->not->toBeNull();
});
