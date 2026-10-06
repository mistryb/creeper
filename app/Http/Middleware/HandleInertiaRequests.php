<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'businessChooser' => fn (): array => $this->businessChooser($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Everything the sidebar's business chooser needs: the business it shows,
     * and the ones it can switch to.
     *
     * @return array{current: array{id: int, name: string}|null, all: list<array{id: int, name: string}>}
     */
    private function businessChooser(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['current' => null, 'all' => []];
        }

        $all = $user->businesses()->get(['id', 'user_id', 'name']);
        $current = $all->firstWhere('id', $user->current_business_id) ?? $all->first();

        return [
            'current' => $current?->only(['id', 'name']),
            'all' => $all->map(fn (Business $business): array => $business->only(['id', 'name']))->values()->all(),
        ];
    }
}
