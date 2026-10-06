<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesAnalyses;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class BusinessAnalysisController extends Controller
{
    use HandlesAnalyses;

    /**
     * A business's own analysis, and every run before it.
     */
    public function index(Request $request, Business $business): Response
    {
        $this->authorize('view', $business);

        return $this->renderAnalysis($request, $business, $business);
    }

    /**
     * Run the business's analysis again.
     */
    public function store(Request $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);

        $this->startAnalysis($request, $business);

        return to_route('businesses.analysis.index', $business);
    }
}
