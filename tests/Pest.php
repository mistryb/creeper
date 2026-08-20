<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Put a user on the paid plan without going anywhere near Stripe.
 *
 * Cashier answers `subscribed()` and `subscribedToPrice()` from the local
 * subscriptions tables, so a plain pair of rows is a complete subscription as
 * far as every plan check in the application is concerned.
 */
function subscribe(User $user, string $price = 'price_creeper_test', string $status = 'active'): void
{
    config([
        'billing.enabled' => true,
        'billing.plans.creeper.stripe_price' => $price,
    ]);

    $subscription = $user->subscriptions()->create([
        'type' => (string) config('billing.subscription', 'default'),
        'stripe_id' => 'sub_'.Str::random(14),
        'stripe_status' => $status,
        'stripe_price' => $price,
        'quantity' => 1,
    ]);

    $subscription->items()->create([
        'stripe_id' => 'si_'.Str::random(14),
        'stripe_product' => 'prod_creeper_test',
        'stripe_price' => $price,
        'quantity' => 1,
    ]);
}
