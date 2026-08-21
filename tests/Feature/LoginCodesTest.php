<?php

use App\Auth\LoginCodes;
use App\Models\LoginCode;
use App\Models\User;

beforeEach(function () {
    $this->codes = app(LoginCodes::class);
});

it('issues a six digit code', function () {
    expect($this->codes->issue('jane@example.com'))->toMatch('/^\d{6}$/');
});

it('accepts the code it issued, once', function () {
    $code = $this->codes->issue('jane@example.com');

    expect($this->codes->verify('jane@example.com', $code))->toBeTrue()
        ->and($this->codes->verify('jane@example.com', $code))->toBeFalse();
});

it('normalises the address on both sides', function () {
    $code = $this->codes->issue('  Jane@Example.COM ');

    expect($this->codes->verify('jane@example.com', $code))->toBeTrue();
});

it('counts wrong guesses and gives up at the limit', function () {
    $this->codes->issue('jane@example.com');

    foreach (range(1, LoginCodes::MAX_ATTEMPTS - 1) as $attempt) {
        expect($this->codes->verify('jane@example.com', '000000'))->toBeFalse();
    }

    expect(LoginCode::query()->find('jane@example.com')->attempts)
        ->toBe(LoginCodes::MAX_ATTEMPTS - 1);

    $this->codes->verify('jane@example.com', '000000');

    expect(LoginCode::query()->find('jane@example.com'))->toBeNull();
});

it('refuses an expired code and clears it away', function () {
    $code = $this->codes->issue('jane@example.com');

    $this->travel(LoginCodes::TTL_MINUTES + 1)->minutes();

    expect($this->codes->verify('jane@example.com', $code))->toBeFalse()
        ->and(LoginCode::query()->find('jane@example.com'))->toBeNull();
});

it('refuses a code for an address that never had one', function () {
    expect($this->codes->verify('nobody@example.com', '123456'))->toBeFalse();
});

it('purges only codes that have expired', function () {
    LoginCode::factory()->forEmail('old@example.com')->expired()->create();
    LoginCode::factory()->forEmail('new@example.com')->create();

    expect($this->codes->purgeExpired())->toBe(1)
        ->and(LoginCode::query()->find('new@example.com'))->not->toBeNull();
});

describe('names guessed from an address', function () {
    it('splits the local part into words', function (string $email, string $expected) {
        expect(User::nameFromEmail($email))->toBe($expected);
    })->with([
        ['jane.doe@example.com', 'Jane Doe'],
        ['jane_doe@example.com', 'Jane Doe'],
        ['jane-doe@example.com', 'Jane Doe'],
        ['bhavik@example.com', 'Bhavik'],
        // Everything after a "+" tags the mailbox, it is not part of a name.
        ['jane.doe+shopping@example.com', 'Jane Doe'],
        ['JANE.DOE@example.com', 'Jane Doe'],
        // Nothing name-shaped in there, so the address is the honest answer.
        ['12345@example.com', '12345@example.com'],
    ]);
});

it('creates an account that is verified from birth', function () {
    $user = User::forEmail('Jane.Doe@Example.com');

    expect($user->email)->toBe('jane.doe@example.com')
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('returns the existing account rather than a second one', function () {
    $existing = User::factory()->create(['email' => 'jane@example.com', 'name' => 'Jane']);

    expect(User::forEmail('jane@example.com')->id)->toBe($existing->id)
        ->and(User::query()->count())->toBe(1)
        // An existing name is not overwritten by a guess.
        ->and(User::forEmail('jane@example.com')->name)->toBe('Jane');
});
