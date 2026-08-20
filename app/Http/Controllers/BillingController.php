<?php

namespace App\Http\Controllers;

use App\Billing\PlanLimits;
use App\Billing\RunMeter;
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
    public function show(Request $request, PlanLimits $limits, RunMeter $meter): Response
    {
        $user = $request->user();
        $offer = $limits->purchasablePlan();

        return Inertia::render('settings/billing', [
            'plan' => $limits->planFor($user),
            'offer' => $offer === null ? null : [
                'key' => $offer['key'],
                'name' => $offer['name'] ?? $offer['key'],
                'price' => $offer['price'] ?? null,
                'targets' => $offer['targets'] ?? null,
                'min_frequency' => $offer['min_frequency'] ?? null,
                'included_runs' => $offer['included_runs'] ?? null,
            ],
            'usage' => [
                'targets' => $user->creepTargets()->count(),
                'limit' => $limits->targetLimit($user),
                'runs' => $meter->runsThisMonth($user),
                'included_runs' => $meter->includedRuns($user),
                'overage_runs' => $meter->billableOverage($user),
                'overage_amount' => $meter->overageAmount($user),
            ],
            'overage_unit_amount' => (int) config('billing.meter.unit_amount', 1),
            'has_api_key' => $user->hasCreepApiKey(),
            'subscribed' => $user->subscribed((string) config('billing.subscription', 'default')),
        ]);
    }

    /**
     * Send the user to Stripe Checkout for a plan.
     */
    public function checkout(Request $request, string $plan, PlanLimits $limits): SymfonyResponse
    {
        $price = $limits->plans()[$plan]['stripe_price'] ?? null;
        $metered = $limits->plans()[$plan]['metered_price'] ?? null;

        abort_unless(is_string($price) && $price !== '', 404);

        $subscription = $request->user()
            ->newSubscription((string) config('billing.subscription', 'default'), $price);

        // The flat fee and the run meter ride on one subscription, so a
        // customer sees a single line for Creeper and a single line for
        // whatever they used beyond the allowance.
        if (is_string($metered) && $metered !== '') {
            $subscription->meteredPrice($metered);
        }

        $checkout = $subscription
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
