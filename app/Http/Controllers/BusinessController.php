<?php

namespace App\Http\Controllers;

use App\Enums\DigestFrequency;
use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\ApiKey;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    /**
     * List the companies this user gets digests about.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Business::class);

        return Inertia::render('businesses/index', [
            'businesses' => BusinessResource::collection($request->user()->businesses()->get()),
        ]);
    }

    /**
     * Show the form for setting up a company.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Business::class);

        return Inertia::render('businesses/create', $this->formOptions($request));
    }

    /**
     * Set up a company, and go looking for its competitors straight away.
     */
    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $this->authorize('create', Business::class);

        $business = $request->user()->businesses()->create($request->validated());

        // The first digest is due on the schedule, counting from today.
        $business->scheduleDigestFrom(Carbon::now());

        // Somebody who has just set a company up wants to work on it next.
        $request->user()->switchBusiness($business);

        CompetitorDiscoveryController::start($business);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company set up. Creeper is looking for its competitors.')]);

        return to_route('dashboard');
    }

    /**
     * One company, and the form for changing it.
     */
    public function show(Request $request, Business $business): Response
    {
        $this->authorize('view', $business);

        return Inertia::render('businesses/show', [
            'business' => BusinessResource::make($business),
            ...$this->formOptions($request),
        ]);
    }

    /**
     * Change a company's details, schedule or key.
     */
    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);

        $business->fill($request->validated());
        $cadenceChanged = $business->isDirty('digest_frequency');
        $business->save();

        // A new cadence takes effect from now, not from whenever the old one
        // would next have fired.
        if ($cadenceChanged) {
            $business->scheduleDigestFrom(Carbon::now());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('businesses.show', $business);
    }

    /**
     * Forget a company, and every digest written about it.
     */
    public function destroy(Business $business): RedirectResponse
    {
        $this->authorize('delete', $business);

        $business->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

        return to_route('businesses.index');
    }

    /**
     * What the form needs to offer: the cadences, and the user's keys.
     *
     * @return array{frequencies: list<array{value: string, label: string}>, apiKeys: array<int, array{value: string, label: string}>}
     */
    private function formOptions(Request $request): array
    {
        return [
            'frequencies' => DigestFrequency::options(),
            'apiKeys' => $request->user()->apiKeys()->get()->map(fn (ApiKey $key): array => [
                'value' => (string) $key->id,
                'label' => $key->label(),
            ])->all(),
        ];
    }
}
