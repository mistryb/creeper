<?php

namespace App\Billing;

use App\Enums\CreepFrequency;
use App\Models\User;

/**
 * The single source of truth for what a user is allowed to do.
 *
 * When billing is switched off — the default, and how every self-hosted
 * install runs — there are no limits at all. Nothing else in the application
 * needs to know whether it's talking to the SaaS or somebody's Raspberry Pi.
 */
class PlanLimits
{
    public function enabled(): bool
    {
        return (bool) config('billing.enabled', false);
    }

    /**
     * The key of the plan this user is on, or null when billing is off.
     */
    public function planKey(User $user): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $subscription = config('billing.subscription', 'default');

        foreach ($this->plans() as $key => $plan) {
            $price = $plan['stripe_price'] ?? null;

            if (is_string($price) && $price !== '' && $user->subscribedToPrice($price, $subscription)) {
                return $key;
            }
        }

        /** @var string $default */
        $default = config('billing.default_plan', 'free');

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function planFor(User $user): array
    {
        $key = $this->planKey($user);

        if ($key === null) {
            return [
                'key' => 'self-hosted',
                'name' => 'Self-hosted',
                'price' => 'Free',
                'targets' => null,
                'min_frequency' => CreepFrequency::Hourly->value,
                'included_runs' => null,
            ];
        }

        /** @var array<string, mixed> $plan */
        $plan = $this->plans()[$key] ?? [];

        return [...$plan, 'key' => $key];
    }

    /**
     * How many targets this user may keep, or null for no limit.
     */
    public function targetLimit(User $user): ?int
    {
        if (! $this->enabled()) {
            return null;
        }

        $limit = $this->planFor($user)['targets'] ?? null;

        return is_numeric($limit) ? (int) $limit : null;
    }

    public function targetsRemaining(User $user): ?int
    {
        $limit = $this->targetLimit($user);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $user->creepTargets()->count());
    }

    public function canAddTarget(User $user): bool
    {
        $remaining = $this->targetsRemaining($user);

        return $remaining === null || $remaining > 0;
    }

    /**
     * The schedules this user's plan allows.
     *
     * @return array<int, CreepFrequency>
     */
    public function allowedFrequencies(User $user): array
    {
        if (! $this->enabled()) {
            return CreepFrequency::cases();
        }

        $slowest = CreepFrequency::tryFrom((string) ($this->planFor($user)['min_frequency'] ?? 'daily'))
            ?? CreepFrequency::Daily;

        return CreepFrequency::upTo($slowest);
    }

    public function allowsFrequency(User $user, CreepFrequency $frequency): bool
    {
        return in_array($frequency, $this->allowedFrequencies($user), true);
    }

    /**
     * The one plan people can actually buy.
     *
     * @return array<string, mixed>|null
     */
    public function purchasablePlan(): ?array
    {
        foreach ($this->plans() as $key => $plan) {
            if (filled($plan['stripe_price'] ?? null)) {
                return [...$plan, 'key' => $key];
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function plans(): array
    {
        /** @var array<string, array<string, mixed>> $plans */
        $plans = config('billing.plans', []);

        return $plans;
    }
}
