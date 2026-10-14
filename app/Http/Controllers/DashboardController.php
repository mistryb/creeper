<?php

namespace App\Http\Controllers;

use App\Enums\DigestFrequency;
use App\Enums\RunStatus;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\CompetitorDiscoveryResource;
use App\Http\Resources\CompetitorResource;
use App\Http\Resources\DigestResource;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The company picked in the sidebar chooser: who it competes with, the
     * latest digest about them, and when the next one goes out.
     */
    public function index(Request $request): Response
    {
        $business = $request->user()->selectedBusiness();

        if ($business === null) {
            return Inertia::render('dashboard', ['business' => null]);
        }

        return Inertia::render('dashboard', [
            'business' => BusinessResource::make($business->loadMissing('apiKey')),
            'frequencies' => DigestFrequency::options(),
            'competitors' => fn () => CompetitorResource::collection($business->competitors()->get()),
            'digest' => fn (): ?DigestResource => $this->digest($business, RunStatus::Succeeded),
            'latestDigestRun' => fn (): ?DigestResource => $this->digest($business),
            'isDigesting' => fn (): bool => $business->digests()->inFlight()->exists(),
            'latestDiscovery' => fn (): ?CompetitorDiscoveryResource => $this->discovery($business),
            'isDiscovering' => fn (): bool => $business->discoveries()->inFlight()->exists(),
            'hasApiKeys' => fn (): bool => $request->user()->apiKeys()->exists(),
        ]);
    }

    /**
     * The newest digest, of a given status or any.
     */
    private function digest(Business $business, ?RunStatus $status = null): ?DigestResource
    {
        $digest = $business->digests()
            ->reorder()
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest('id')
            ->first();

        return $digest === null ? null : DigestResource::make($digest);
    }

    private function discovery(Business $business): ?CompetitorDiscoveryResource
    {
        $discovery = $business->discoveries()->reorder()->latest('id')->first();

        return $discovery === null ? null : CompetitorDiscoveryResource::make($discovery);
    }
}
