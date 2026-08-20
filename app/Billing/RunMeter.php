<?php

namespace App\Billing;

use App\Enums\RunStatus;
use App\Models\CreepRun;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Counts and reports billable creep runs.
 *
 * Stripe owns the allowance. The metered price is tiered so the first
 * `included_runs` units of a billing period cost nothing, which means this
 * class can report every billable run without tracking where a user's period
 * starts. The counts here are for showing people their usage and for the
 * safety ceiling — never for deciding what to charge.
 *
 * A run is billable when it succeeded. Retries and failures cost us money but
 * charging for them is indefensible, so they are free.
 */
class RunMeter
{
    public function __construct(private PlanLimits $limits) {}

    /**
     * Billable runs so far this calendar month.
     */
    public function runsThisMonth(User $user): int
    {
        return CreepRun::query()
            ->whereHas('target', fn ($query) => $query->where('user_id', $user->id))
            ->where('status', RunStatus::Succeeded)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();
    }

    /**
     * Runs the flat fee covers, or null when there is no allowance to speak
     * of because billing is switched off.
     */
    public function includedRuns(User $user): ?int
    {
        if (! $this->limits->enabled()) {
            return null;
        }

        $included = $this->limits->planFor($user)['included_runs'] ?? 0;

        return (int) $included;
    }

    /**
     * How many runs are left inside the allowance, floored at zero. Null when
     * there is no allowance to run out of.
     */
    public function runsRemaining(User $user): ?int
    {
        $included = $this->includedRuns($user);

        if ($included === null) {
            return null;
        }

        return max(0, $included - $this->runsThisMonth($user));
    }

    /**
     * Runs this month that fall outside the allowance and will be invoiced.
     */
    public function billableOverage(User $user): int
    {
        $included = $this->includedRuns($user);

        if ($included === null) {
            return 0;
        }

        return max(0, $this->runsThisMonth($user) - $included);
    }

    /**
     * What the overage has cost so far, in minor units.
     */
    public function overageAmount(User $user): int
    {
        return $this->billableOverage($user) * (int) config('billing.meter.unit_amount', 1);
    }

    /**
     * The hard stop, past which we refuse to creep at all. Null when there
     * isn't one.
     */
    public function ceiling(): ?int
    {
        if (! $this->limits->enabled()) {
            return null;
        }

        $ceiling = config('billing.run_ceiling');

        return is_numeric($ceiling) && (int) $ceiling > 0 ? (int) $ceiling : null;
    }

    /**
     * Whether this user may be crept again right now.
     */
    public function allowsRun(User $user): bool
    {
        $ceiling = $this->ceiling();

        if ($ceiling === null) {
            return true;
        }

        return $this->runsThisMonth($user) < $ceiling;
    }

    /**
     * Tell Stripe about one billable run.
     *
     * Silently does nothing when billing is off or the user has no
     * subscription to bill against — a self-hosted install never talks to
     * Stripe, and neither does an unsubscribed account.
     */
    public function report(CreepRun $run): void
    {
        if (! $this->limits->enabled()) {
            return;
        }

        $user = $run->target->user;

        if (! $user->subscribed((string) config('billing.subscription', 'default'))) {
            return;
        }

        $user->reportMeterEvent((string) config('billing.meter.event', 'creep_run'));
    }
}
