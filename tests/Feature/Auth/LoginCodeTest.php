<?php

use App\Auth\LoginCodes;
use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Notification::fake();
    RateLimiter::clear('login-code');
});

/**
 * Capture the code out of the notification that was sent, since it is only
 * ever in the clear inside the email.
 */
function sentCode(string $email): string
{
    $sent = null;

    Notification::assertSentOnDemand(
        LoginCodeNotification::class,
        function (LoginCodeNotification $notification, array $channels, object $notifiable) use ($email, &$sent) {
            if (($notifiable->routes['mail'] ?? null) !== $email) {
                return false;
            }

            $sent = (new ReflectionProperty($notification, 'code'))->getValue($notification);

            return true;
        },
    );

    expect($sent)->not->toBeNull();

    return $sent;
}

it('shows the sign-in form to visitors', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

it('emails a code and sends the visitor on to type it', function () {
    $this->post(route('login.store'), ['email' => 'jane@example.com'])
        ->assertRedirect(route('login.verify'))
        ->assertSessionHasNoErrors();

    Notification::assertSentOnDemandTimes(LoginCodeNotification::class, 1);

    expect(LoginCode::query()->find('jane@example.com'))->not->toBeNull();
});

it('does not create an account merely because a code was asked for', function () {
    $this->post(route('login.store'), ['email' => 'nobody@example.com']);

    expect(User::query()->count())->toBe(0);
});

it('stores the code hashed, never in the clear', function () {
    $this->post(route('login.store'), ['email' => 'jane@example.com']);

    $code = sentCode('jane@example.com');
    $record = LoginCode::query()->find('jane@example.com');

    expect($record->code_hash)->not->toBe($code)
        ->and(Hash::check($code, $record->code_hash))->toBeTrue();
});

it('signs in an existing account when the code is right', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->post(route('login.store'), ['email' => 'jane@example.com']);

    $this->post(route('login.verify.store'), [
        'email' => 'jane@example.com',
        'code' => sentCode('jane@example.com'),
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('creates a verified account the first time an address signs in', function () {
    $this->post(route('login.store'), ['email' => 'jane.doe@example.com']);

    $this->post(route('login.verify.store'), [
        'email' => 'jane.doe@example.com',
        'code' => sentCode('jane.doe@example.com'),
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->sole();

    expect($user->email)->toBe('jane.doe@example.com')
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('remembers the browser so the visitor does not sign in again', function () {
    $this->post(route('login.store'), ['email' => 'jane@example.com']);

    $response = $this->post(route('login.verify.store'), [
        'email' => 'jane@example.com',
        'code' => sentCode('jane@example.com'),
    ]);

    $user = User::query()->sole();

    // Laravel's recaller cookie is what keeps this browser signed in once the
    // session lapses. Without it, "stay signed in" is only session-long.
    $response->assertCookie(Auth::guard('web')->getRecallerName());
    expect($user->remember_token)->not->toBeNull();
});

it('spends the code, so it cannot be used twice', function () {
    $this->post(route('login.store'), ['email' => 'jane@example.com']);
    $code = sentCode('jane@example.com');

    $this->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => $code]);
    $this->post(route('logout'));

    $this->from(route('login.verify'))
        ->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('rejects a wrong code', function () {
    $this->post(route('login.store'), ['email' => 'jane@example.com']);

    $this->from(route('login.verify'))
        ->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('rejects an expired code', function () {
    LoginCode::factory()->forEmail('jane@example.com', '123456')->expired()->create();

    $this->from(route('login.verify'))
        ->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => '123456'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('destroys the code after five wrong guesses', function () {
    LoginCode::factory()->forEmail('jane@example.com', '123456')->create();

    foreach (range(1, LoginCodes::MAX_ATTEMPTS) as $attempt) {
        $this->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => '999999']);
    }

    expect(LoginCode::query()->find('jane@example.com'))->toBeNull();

    // Even the code that was correct is now worthless.
    $this->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => '123456']);

    $this->assertGuest();
});

it('replaces the outstanding code when another is asked for', function () {
    $this->post(route('login.store'), ['email' => 'jane@example.com']);
    $first = sentCode('jane@example.com');

    Notification::fake();
    $this->post(route('login.store'), ['email' => 'jane@example.com']);
    $second = sentCode('jane@example.com');

    expect($first)->not->toBe($second);

    $this->from(route('login.verify'))
        ->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => $first])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('accepts a code typed in a different browser from the one that asked', function () {
    $this->post(route('login.store'), ['email' => 'jane@example.com']);
    $code = sentCode('jane@example.com');

    // A fresh session, standing in for a phone.
    $this->flushSession();

    $this->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => $code])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

it('matches the address case-insensitively', function () {
    $this->post(route('login.store'), ['email' => 'Jane@Example.com']);

    $this->post(route('login.verify.store'), [
        'email' => 'jane@example.com',
        'code' => sentCode('jane@example.com'),
    ])->assertRedirect(route('dashboard'));

    expect(User::query()->sole()->email)->toBe('jane@example.com');
});

it('throttles how fast codes can be requested for one address', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post(route('login.store'), ['email' => 'jane@example.com'])
            ->assertRedirect(route('login.verify'));
    }

    $this->post(route('login.store'), ['email' => 'jane@example.com'])
        ->assertTooManyRequests();
});

it('throttles how fast codes can be guessed', function () {
    LoginCode::factory()->forEmail('jane@example.com', '123456')->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => '999999']);
    }

    $this->post(route('login.verify.store'), ['email' => 'jane@example.com', 'code' => '123456'])
        ->assertTooManyRequests();

    $this->assertGuest();
});

it('keeps signed-in visitors away from the sign-in form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

it('sends the old sign-up route to the one form there is', function () {
    $this->get('/register')->assertRedirect('/login');
});

it('signs out and drops the remembered browser', function () {
    $user = User::factory()->create();
    $token = $user->remember_token;

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();

    // Cycling the token is what stops the stored cookie working again.
    expect($user->fresh()->remember_token)->not->toBe($token);
});
