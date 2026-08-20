<?php

namespace App\Http\Controllers;

use App\Billing\PlanLimits;
use App\Http\Middleware\EnsureBillingIsEnabled;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Only reachable when billing is switched on. See {@see EnsureBillingIsEnabled}.
 */
class BillingController extends Controller
{
    public function show(Request $request, PlanLimits $limits): Response
    {
        $user = $request->user();

        return Inertia::render('settings/billing', [
            'plan' => $limits->planFor($user),
            'plans' => collect($limits->plans())
                ->map(fn (array $plan, string $key): array => [
                    'key' => $key,
                    'name' => $plan['name'] ?? $key,
                    'price' => $plan['price'] ?? null,
                    'targets' => $plan['targets'] ?? null,
                    'min_frequency' => $plan['min_frequency'] ?? null,
                    'purchasable' => filled($plan['stripe_price'] ?? null),
                ])
                ->values()
                ->all(),
            'usage' => [
                'targets' => $user->creepTargets()->count(),
                'limit' => $limits->targetLimit($user),
            ],
            'subscribed' => $user->subscribed((string) config('billing.subscription', 'default')),
        ]);
    }

    /**
     * Send the user to Stripe Checkout for a plan.
     */
    public function checkout(Request $request, string $plan, PlanLimits $limits): SymfonyResponse
    {
        $price = $limits->plans()[$plan]['stripe_price'] ?? null;

        abort_unless(is_string($price) && $price !== '', 404);

        $checkout = $request->user()
            ->newSubscription((string) config('billing.subscription', 'default'), $price)
            ->checkout([
                'success_url' => route('billing.show').'?checkout=success',
                'cancel_url' => route('billing.show').'?checkout=cancelled',
            ]);

        // Inertia can't follow a cross-origin redirect over XHR.
        return Inertia::location($checkout->asStripeCheckoutSession()->url);
    }

    /**
     * Hand subscription management over to Stripe's own portal.
     */
    public function portal(Request $request): RedirectResponse
    {
        return $request->user()->redirectToBillingPortal(route('billing.show'));
    }
}
