<?php

use App\Enums\CreepFrequency;
use App\Models\CreepRun;
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

    it('gives an account with no subscription nothing to creep with', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('creep-targets.store'), [
                'url' => 'https://example.com/products/nope',
                'frequency' => CreepFrequency::Daily->value,
            ])
            ->assertSessionHasErrors('url');
    });

    it('lets a subscriber keep as many targets as they like', function () {
        $user = User::factory()->create();
        subscribe($user);
        CreepTarget::factory()->count(80)->for($user)->create();

        $this->actingAs($user)
            ->post(route('creep-targets.store'), [
                'url' => 'https://example.com/products/one-more',
                'frequency' => CreepFrequency::Hourly->value,
            ])
            ->assertSessionHasNoErrors();

        expect($user->creepTargets()->count())->toBe(81);
    });

    it('allows every schedule on the paid plan', function (string $frequency) {
        $user = User::factory()->create();
        subscribe($user);

        $this->actingAs($user)
            ->post(route('creep-targets.store'), [
                'url' => 'https://example.com/products/'.$frequency,
                'frequency' => $frequency,
            ])
            ->assertSessionHasNoErrors();
    })->with(['manual', 'hourly', 'daily', 'weekly']);

    it('shows an unsubscribed account the offer rather than usage', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('billing.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/billing')
                ->where('plan.key', 'none')
                ->where('usage.included_runs', 0)
                ->where('subscribed', false)
            );
    });

    it('shows a subscriber what they have used this month', function () {
        $user = User::factory()->create();
        subscribe($user);

        $target = CreepTarget::factory()->for($user)->create();
        CreepRun::factory()->count(4)->for($target, 'target')->create();

        $this->actingAs($user)
            ->get(route('billing.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('plan.key', 'creeper')
                ->where('plan.price', '$7/month')
                ->where('usage.runs', 4)
                ->where('usage.included_runs', 1500)
                ->where('usage.overage_runs', 0)
                ->where('overage_unit_amount', 1)
                ->where('subscribed', true)
            );
    });

    it('404s for a plan that has no Stripe price', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('billing.checkout', 'none'))
            ->assertNotFound();
    });
});
