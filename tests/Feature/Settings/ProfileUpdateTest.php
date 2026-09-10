<?php

use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('shows the profile page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

it('updates a name without touching the address', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Jane Doe')
        ->and($user->email)->toBe('jane@example.com')
        ->and($user->pending_email)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    Notification::assertNothingSent();
});

/*
 * The address is the only credential, so a change cannot be allowed to take
 * effect on trust — a typo would lock the account for good.
 */
it('parks a new address and emails a code to it', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new@example.com',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->email)->toBe('jane@example.com')
        ->and($user->pending_email)->toBe('new@example.com');

    Notification::assertSentOnDemandTimes(LoginCodeNotification::class, 1);
    expect(LoginCode::query()->find('new@example.com'))->not->toBeNull();
});

it('applies the new address once its code comes back', function () {
    $user = User::factory()->changingEmailTo('new@example.com')->create([
        'email' => 'jane@example.com',
    ]);

    LoginCode::factory()->forEmail('new@example.com', '123456')->create();

    $this->actingAs($user)
        ->post(route('profile.email.confirm'), ['code' => '123456'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->email)->toBe('new@example.com')
        ->and($user->pending_email)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('keeps the old address when the code is wrong', function () {
    $user = User::factory()->changingEmailTo('new@example.com')->create([
        'email' => 'jane@example.com',
    ]);

    LoginCode::factory()->forEmail('new@example.com', '123456')->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('profile.email.confirm'), ['code' => '999999'])
        ->assertSessionHasErrors('code');

    $user->refresh();

    expect($user->email)->toBe('jane@example.com')
        ->and($user->pending_email)->toBe('new@example.com');
});

it('abandons a change rather than colliding with an address taken meanwhile', function () {
    User::factory()->create(['email' => 'new@example.com']);

    $user = User::factory()->changingEmailTo('new@example.com')->create([
        'email' => 'jane@example.com',
    ]);

    LoginCode::factory()->forEmail('new@example.com', '123456')->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('profile.email.confirm'), ['code' => '123456'])
        ->assertSessionHasErrors('code');

    $user->refresh();

    expect($user->email)->toBe('jane@example.com')
        ->and($user->pending_email)->toBeNull();
});

it('cancels a pending change and throws away its code', function () {
    $user = User::factory()->changingEmailTo('new@example.com')->create();

    LoginCode::factory()->forEmail('new@example.com', '123456')->create();

    $this->actingAs($user)
        ->delete(route('profile.email.cancel'))
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->pending_email)->toBeNull()
        ->and(LoginCode::query()->find('new@example.com'))->toBeNull();
});

it('rejects an address already on another account', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ])
        ->assertSessionHasErrors('email');

    expect($user->refresh()->pending_email)->toBeNull();
});

it('deletes the account when its own address is typed back', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['email' => 'jane@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

it('refuses to delete the account on the wrong address', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['email' => 'someone@example.com'])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

/*
 * The address is the only way into an account, so moving one off the install's
 * allow list would be a locked door at the next sign-in. Unlike the sign-in
 * form, this can say so: the person reading it is already signed in.
 */
it('refuses to move an account to an address the install does not admit', function () {
    config(['auth.authorized_emails' => 'jane@example.com']);

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'stranger@example.com',
        ])
        ->assertSessionHasErrors('email');

    expect($user->fresh()->pending_email)->toBeNull();

    Notification::assertNothingSent();
});

it('allows a move between two addresses the install admits', function () {
    config(['auth.authorized_emails' => 'jane@example.com, jane@work.example.com']);

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'jane@work.example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->pending_email)->toBe('jane@work.example.com');

    Notification::assertSentOnDemandTimes(LoginCodeNotification::class, 1);
});
