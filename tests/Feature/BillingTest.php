<?php

use App\Enums\CreepFrequency;
use App\Models\CreepTarget;
use App\Models\User;

/**
 * Creeper ships with billing off. A self-hosted install is the full
 * application with no limits and no billing UI anywhere.
 */
describe('self-hosted', function () {
    beforeEach(fn () => config(['billing.enabled' => false]));

    it('hides the billing pages entirely', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('billing.show'))
            ->assertNotFound();
    });

    it('tells the front end billing does not exist', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('billing.enabled', false));
    });

    it('does not cap how many targets you can keep', function () {
        $user = User::factory()->create();
        CreepTarget::factory()->count(10)->for($user)->create();

        $this->actingAs($user)
            ->post(route('creep-targets.store'), [
                'url' => 'https://example.com/products/one-more',
                'frequency' => CreepFrequency::Hourly->value,
            ])
            ->assertSessionHasNoErrors();

        expect($user->creepTargets()->count())->toBe(11);
    });

    it('allows every schedule', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('creep-targets.store'), [
                'url' => 'https://example.com/products/fast',
                'frequency' => CreepFrequency::Hourly->value,
            ])
            ->assertSessionHasNoErrors();
    });
});

describe('hosted', function () {
    beforeEach(fn () => config(['billing.enabled' => true]));

    it('caps targets at the free plan limit', function () {
        $user = User::factory()->create();
        CreepTarget::factory()->count(3)->for($user)->create();

        $this->actingAs($user)
            ->post(route('creep-targets.store'), [
                'url' => 'https://example.com/products/one-too-many',
                'frequency' => CreepFrequency::Daily->value,
            ])
            ->assertSessionHasErrors('url');

        expect($user->creepTargets()->count())->toBe(3);
    });

    it('rejects a schedule the free plan does not include', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('creep-targets.store'), [
                'url' => 'https://example.com/products/fast',
                'frequency' => CreepFrequency::Hourly->value,
            ])
            ->assertSessionHasErrors('frequency');
    });

    it('still allows the schedules the free plan does include', function (string $frequency) {
        $this->actingAs(User::factory()->create())
            ->post(route('creep-targets.store'), [
                'url' => 'https://example.com/products/'.$frequency,
                'frequency' => $frequency,
            ])
            ->assertSessionHasNoErrors();
    })->with(['manual', 'daily', 'weekly']);

    it('shows the billing page with the current plan', function () {
        $user = User::factory()->create();
        CreepTarget::factory()->count(2)->for($user)->create();

        $this->actingAs($user)
            ->get(route('billing.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/billing')
                ->where('plan.key', 'free')
                ->where('usage.targets', 2)
                ->where('usage.limit', 3)
                ->where('subscribed', false)
            );
    });

    it('404s for a plan that has no Stripe price', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('billing.checkout', 'free'))
            ->assertNotFound();
    });
});
