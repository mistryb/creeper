<?php

namespace App\Concerns;

use App\Ai\Contracts\Analyzable;
use App\Enums\RunStatus;
use App\Http\Resources\BusinessAnalysisResource;
use App\Http\Resources\BusinessResource;
use App\Jobs\AnalyzeBusiness;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\BusinessAnalysis;
use App\Models\Competitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The analysis screen and its run button, for anything {@see Analyzable}:
 * a business's own analysis and a competitor's work the same way.
 */
trait HandlesAnalyses
{
    /**
     * How many past runs the history lists.
     */
    private int $analysisHistory = 20;

    /**
     * The report being read, and every run before it.
     */
    protected function renderAnalysis(Request $request, Model&Analyzable $subject, Business $business): Response
    {
        $history = $subject->analyses()->reorder()->latest('id')->limit($this->analysisHistory)->get();

        return Inertia::render('analysis/show', [
            'subject' => [
                'type' => $subject->getMorphClass(),
                'id' => $subject->getKey(),
                'name' => $subject->getAttribute('name'),
                'url' => $subject->analysisUrl(),
            ],
            'business' => BusinessResource::make($business),
            'analysis' => $this->selectedAnalysis($request, $subject),
            'history' => BusinessAnalysisResource::collection($history),
            'isAnalyzing' => $subject->analyses()->inFlight()->exists(),
            // Analysing is paid for with one of the user's own keys, picked
            // each time, the same way a watched page picks one.
            'apiKeys' => $request->user()->apiKeys()->get()->map(fn (ApiKey $key): array => [
                'value' => (string) $key->id,
                'label' => $key->label(),
            ])->all(),
        ]);
    }

    /**
     * Queue a run, unless one is already going. Says whether it was queued.
     */
    protected function startAnalysis(Request $request, Model&Analyzable $subject): bool
    {
        $validated = $request->validate([
            'api_key_id' => [
                'required',
                'integer',
                Rule::exists(ApiKey::class, 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        // One at a time: a second run started while the first is going would
        // only pay twice for the same answer.
        if ($subject->analyses()->inFlight()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('An analysis is already running.')]);

            return false;
        }

        $analysis = new BusinessAnalysis;
        $analysis->forceFill([
            'api_key_id' => $validated['api_key_id'],
            'status' => RunStatus::Queued,
        ]);
        $subject->analyses()->save($analysis);

        AnalyzeBusiness::dispatch($analysis);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Analysis started.')]);

        return true;
    }

    /**
     * The report on screen: the one asked for by `?analysis=`, otherwise the
     * newest that succeeded, otherwise the newest run of any kind so a first
     * run that failed still explains itself.
     */
    private function selectedAnalysis(Request $request, Business|Competitor $subject): ?BusinessAnalysisResource
    {
        $analyses = $subject->analyses()->reorder()->latest('id');

        $analysis = $request->filled('analysis')
            ? (clone $analyses)->find($request->integer('analysis'))
            : null;

        $analysis ??= (clone $analyses)->where('status', RunStatus::Succeeded)->first()
            ?? (clone $analyses)->first();

        return $analysis === null ? null : BusinessAnalysisResource::make($analysis);
    }
}
