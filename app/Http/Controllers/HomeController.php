<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page.
 *
 * Pricing is read from config rather than written into the page, so the
 * headline number, the allowance and the overage rate cannot drift away from
 * what the application actually charges.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            'pricing' => $this->pricing(),
        ]);
    }

    /**
     * @return array{amount: string, period: string, included_runs: int, overage: string}
     */
    protected function pricing(): array
    {
        /** @var array<string, array<string, mixed>> $plans */
        $plans = config('billing.plans', []);

        /** @var array<string, mixed> $plan */
        $plan = collect($plans)->first(fn (array $plan): bool => filled($plan['amount'] ?? null)) ?? [];

        return [
            'amount' => $this->money((int) ($plan['amount'] ?? 0), trimZeroes: true),
            'period' => 'month',
            'included_runs' => (int) ($plan['included_runs'] ?? 0),
            'overage' => $this->money((int) config('billing.meter.unit_amount', 1)),
        ];
    }

    /**
     * Minor units to something a headline can carry — "$7" rather than
     * "$7.00", but "$0.01" stays exact.
     */
    protected function money(int $minorUnits, bool $trimZeroes = false): string
    {
        $formatted = number_format($minorUnits / 100, 2);

        if ($trimZeroes && str_ends_with($formatted, '.00')) {
            $formatted = mb_substr($formatted, 0, -3);
        }

        return '$'.$formatted;
    }
}
