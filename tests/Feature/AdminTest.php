<?php

use App\Enums\Plan;
use App\Models\Business;
use App\Models\User;

function admin(): User
{
    config(['auth.admin_emails' => 'boss@example.com, other-boss@example.com']);

    return User::factory()->create(['email' => 'boss@example.com']);
}

it('sends guests to log in', function () {
    $this->get(route('admin.overview'))->assertRedirect(route('login'));
});

it('keeps everyone not in ADMIN_EMAILS out', function (string $route) {
    admin();

    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertForbidden();
})->with(['admin.overview', 'admin.users.index', 'admin.businesses.index']);

it('lets nobody in when ADMIN_EMAILS is empty', function () {
    config(['auth.admin_emails' => '']);

    $user = User::factory()->create();

    expect($user->isAdmin())->toBeFalse();

    $this->actingAs($user)->get(route('admin.overview'))->assertForbidden();
});

it('matches admin addresses whatever their case', function () {
    config(['auth.admin_emails' => "Boss@Example.com\nsecond@example.com"]);

    expect(User::factory()->create(['email' => 'boss@example.com'])->isAdmin())->toBeTrue();
});

it('tells the front end who is an admin', function () {
    $this->actingAs(admin())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.isAdmin', true));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.isAdmin', false));
});

it('shows the overview with no revenue on a self-hosted install', function () {
    Business::factory()->count(2)->create();

    $this->actingAs(admin())
        ->get(route('admin.overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/overview')
            ->where('accounts', 3)
            ->where('businesses', 2)
            ->where('revenue', null));
});

it('adds up what a billed install earns', function () {
    enableBilling();
    $admin = admin();

    subscribe(User::factory()->create(), Plan::Single);
    subscribe(User::factory()->create(), Plan::Single);
    subscribe(User::factory()->create(), Plan::Unlimited);
    subscribe(User::factory()->create(), Plan::Unlimited, ['stripe_status' => 'trialing', 'trial_ends_at' => now()->addDays(3)]);
    subscribe(User::factory()->create(), Plan::Unlimited, ['stripe_status' => 'canceled', 'ends_at' => now()->subDay()]);
    User::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    User::factory()->create(['comped_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.overview'))
        ->assertInertia(fn ($page) => $page
            ->where('revenue.plans.0.paying', 2)
            ->where('revenue.plans.0.trialing', 0)
            ->where('revenue.plans.1.paying', 1)
            ->where('revenue.plans.1.trialing', 1)
            ->where('revenue.mrr', 2 * 5 + 20)
            ->where('revenue.trials', 1)
            ->where('revenue.comped', 1));
});

it('lists every account with where it stands', function () {
    enableBilling();
    $admin = admin();

    $paying = User::factory()->create(['email' => 'paying@example.com']);
    subscribe($paying, Plan::Single);
    Business::factory()->for($paying)->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'paying']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users')
            ->has('users.data', 1)
            ->where('users.data.0.email', 'paying@example.com')
            ->where('users.data.0.status', 'One company')
            ->where('users.data.0.businesses_count', 1)
            ->where('users.data.0.business_limit', 1)
            ->where('billingEnabled', true));
});

it('calls every account self-hosted when billing is off', function () {
    $this->actingAs(admin())
        ->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page
            ->where('users.data.0.status', 'Self-hosted')
            ->where('billingEnabled', false));
});

it('comps an account Unlimited, and takes it back', function () {
    enableBilling();
    $admin = admin();
    $user = User::factory()->create(['trial_ends_at' => now()->subDay()]);

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.comp', $user))
        ->assertRedirect(route('admin.users.index'));

    expect($user->fresh()->isComped())->toBeTrue()
        ->and($user->fresh()->businessLimit())->toBeNull();

    $this->actingAs($admin)
        ->delete(route('admin.users.uncomp', $user));

    expect($user->fresh()->isComped())->toBeFalse()
        ->and($user->fresh()->businessLimit())->toBe(0);
});

it('does not let a non-admin comp anybody', function () {
    admin();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.users.comp', $user))
        ->assertForbidden();

    expect($user->fresh()->isComped())->toBeFalse();
});

it('lists every company across every account', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    Business::factory()->for($owner)->create(['name' => 'Northwind Coffee']);
    Business::factory()->create(['name' => 'Someone Else']);

    $this->actingAs(admin())
        ->get(route('admin.businesses.index', ['search' => 'owner@']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/businesses')
            ->has('businesses.data', 1)
            ->where('businesses.data.0.name', 'Northwind Coffee')
            ->where('businesses.data.0.owner_email', 'owner@example.com'));
});

it('pages long lists', function () {
    User::factory()->count(30)->create();

    $this->actingAs(admin())
        ->get(route('admin.users.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 6)
            ->where('users.meta.current_page', 2)
            ->where('users.meta.total', 31));
});
