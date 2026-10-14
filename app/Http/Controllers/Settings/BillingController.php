<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Plan;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Picking and changing a plan on a billed install.
 *
 * Paying happens on Stripe: starting a subscription goes through Checkout,
 * and the card, invoices and cancelling live in the billing portal. The one
 * thing done here is moving between the two plans, because that is a single
 * call and does not need a card.
 *
 * Every action 404s on a self-hosted install. The routes are always
 * registered and the check is made per request, so `route:cache` cannot bake
 * whether Stripe was configured at deploy time into the route table.
 */
class BillingController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->ensureBillingIsEnabled();

        $user = $request->user();
        $subscription = $user->subscription();

        return Inertia::render('settings/billing', [
            'plans' => Plan::options(),
            'plan' => $user->plan()?->value,
            'comped' => $user->isComped(),
            'trialEndsAt' => $user->onGenericTrial() ? $user->trial_ends_at?->toIso8601String() : null,
            'endsAt' => $subscription?->onGracePeriod() ? $subscription->ends_at?->toIso8601String() : null,
            'businessCount' => $user->businesses()->count(),
            'businessLimit' => $user->businessLimit(),
            'hasBillingAccount' => $user->hasStripeId(),
            'justCheckedOut' => $request->query('checkout') === 'success',
        ]);
    }

    /**
     * Start a subscription on Stripe Checkout.
     *
     * Days left on the trial carry over, so subscribing early does not cost
     * the rest of the week. Stripe will not start a trial shorter than 48
     * hours, so the last two days of one are simply forfeited.
     */
    public function checkout(Request $request, Plan $plan): SymfonyResponse
    {
        $this->ensureBillingIsEnabled();

        $user = $request->user();

        if ($user->plan() !== null) {
            return to_route('billing.edit');
        }

        $subscription = $user->newSubscription('default', $plan->priceId())->allowPromotionCodes();

        if ($user->onGenericTrial() && $user->trial_ends_at->isAfter(now()->addDays(2))) {
            $subscription->trialUntil($user->trial_ends_at);
        }

        $checkout = $subscription->checkout([
            'success_url' => route('billing.edit', ['checkout' => 'success']),
            'cancel_url' => route('billing.edit'),
        ]);

        return Inertia::location($checkout->asStripeCheckoutSession()->url);
    }

    /**
     * Move an existing subscription to the other plan. Stripe prorates the
     * difference onto the next invoice.
     */
    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->ensureBillingIsEnabled();

        $subscription = $request->user()->subscription();

        if ($subscription === null || ! $subscription->valid()) {
            return to_route('billing.edit');
        }

        try {
            $subscription->swap($plan->priceId());
        } catch (IncompletePayment $exception) {
            return to_route('cashier.payment', [
                $exception->payment->asStripePaymentIntent()->id,
                'redirect' => route('billing.edit'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You are now on :plan.', ['plan' => $plan->label()])]);

        return to_route('billing.edit');
    }

    /**
     * Hand over to Stripe's billing portal for the card, invoices and
     * cancelling.
     */
    public function portal(Request $request): SymfonyResponse
    {
        $this->ensureBillingIsEnabled();

        abort_unless($request->user()->hasStripeId(), 404);

        return Inertia::location($request->user()->billingPortalUrl(route('billing.edit')));
    }

    private function ensureBillingIsEnabled(): void
    {
        abort_unless(config('billing.enabled'), 404);
    }
}
