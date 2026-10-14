<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BusinessResource;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every company across every account, newest first. Read only: a company
 * belongs to its owner, and an admin has no business editing it.
 */
class BusinessController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $businesses = Business::query()
            ->with('user:id,email')
            ->withCount('competitors')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('url', 'like', "%{$search}%")
                ->orWhereRelation('user', 'email', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/businesses', [
            'businesses' => BusinessResource::collection($businesses),
            'search' => $search,
        ]);
    }
}
