<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Plan;
use App\Enums\RunStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Digest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Subscription;

/**
 * The admin panel's front page: how many people use this install and, when
 * it charges, how many of them pay and what that comes to a month.
 */
class OverviewController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/overview', [
            'accounts' => User::query()->count(),
            'businesses' => Business::query()->count(),
            'digestsThisWeek' => Digest::query()
                ->where('status', RunStatus::Succeeded)
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
            'revenue' => config('billing.enabled') ? $this->revenue() : null,
        ]);
    }

    /**
     * Subscribers on each plan, split into paying and still on trial, with
     * the monthly recurring revenue the paying ones add up to. Read from the
     * subscriptions table the webhooks keep, so it is only as fresh as those.
     *
     * @return array{plans: list<array{label: string, price: int, paying: int, trialing: int}>, mrr: int, trials: int, comped: int}
     */
    private function revenue(): array
    {
        $plans = array_map(function (Plan $plan): array {
            $subscriptions = fn () => Subscription::query()
                ->where('type', 'default')
                ->where('stripe_price', $plan->priceId())
                ->active();

            return [
                'label' => $plan->label(),
                'price' => $plan->monthlyPrice(),
                'paying' => $subscriptions()->notOnTrial()->count(),
                'trialing' => $subscriptions()->onTrial()->count(),
            ];
        }, Plan::cases());

        return [
            'plans' => $plans,
            'mrr' => array_sum(array_map(fn (array $plan): int => $plan['paying'] * $plan['price'], $plans)),
            'trials' => User::query()->whereNull('comped_at')->where('trial_ends_at', '>', now())->whereDoesntHave('subscriptions')->count(),
            'comped' => User::query()->whereNotNull('comped_at')->count(),
        ];
    }
}
