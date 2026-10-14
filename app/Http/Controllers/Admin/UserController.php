<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every account on the install, and the one thing an admin does to one:
 * giving it Unlimited for free, or taking that back.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with('subscriptions')
            ->withCount('businesses')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('email', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/users', [
            'users' => UserResource::collection($users),
            'search' => $search,
            'billingEnabled' => (bool) config('billing.enabled'),
        ]);
    }

    /**
     * Give an account Unlimited without a subscription.
     */
    public function comp(User $user): RedirectResponse
    {
        $user->forceFill(['comped_at' => now()])->save();

        return $this->done(__(':email now has Unlimited for free.', ['email' => $user->email]));
    }

    /**
     * Take a comp back. The account falls back to its plan, or its trial, or
     * to nothing.
     */
    public function uncomp(User $user): RedirectResponse
    {
        $user->forceFill(['comped_at' => null])->save();

        return $this->done(__(':email is back on their own plan.', ['email' => $user->email]));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
