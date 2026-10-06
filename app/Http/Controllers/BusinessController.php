<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    /**
     * List the businesses this user watches competitors for.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Business::class);

        return Inertia::render('businesses/index', [
            'businesses' => BusinessResource::collection($request->user()->businesses()->get()),
        ]);
    }

    /**
     * Show the form for setting up a business.
     */
    public function create(): Response
    {
        $this->authorize('create', Business::class);

        return Inertia::render('businesses/create');
    }

    /**
     * Set up a business.
     */
    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $this->authorize('create', Business::class);

        $business = $request->user()->businesses()->create($request->validated());

        // Somebody who has just set a business up wants to work on it next.
        $request->user()->switchBusiness($business);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business set up.')]);

        return to_route('businesses.show', $business);
    }

    /**
     * One business, and the form for changing it.
     */
    public function show(Business $business): Response
    {
        $this->authorize('view', $business);

        return Inertia::render('businesses/show', [
            'business' => BusinessResource::make($business),
        ]);
    }

    /**
     * Change a business's name or description.
     */
    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);

        $business->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business updated.')]);

        return to_route('businesses.show', $business);
    }

    /**
     * Forget a business.
     */
    public function destroy(Business $business): RedirectResponse
    {
        $this->authorize('delete', $business);

        $business->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business deleted.')]);

        return to_route('businesses.index');
    }
}
