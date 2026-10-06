<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesAnalyses;
use App\Models\Competitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CompetitorAnalysisController extends Controller
{
    use HandlesAnalyses;

    /**
     * A competitor's analysis, and every run before it.
     */
    public function index(Request $request, Competitor $competitor): Response
    {
        $this->authorize('view', $competitor);

        return $this->renderAnalysis($request, $competitor, $competitor->business);
    }

    /**
     * Run the competitor's analysis again.
     */
    public function store(Request $request, Competitor $competitor): RedirectResponse
    {
        $this->authorize('update', $competitor);

        $this->startAnalysis($request, $competitor);

        return to_route('competitors.analysis.index', $competitor);
    }
}
