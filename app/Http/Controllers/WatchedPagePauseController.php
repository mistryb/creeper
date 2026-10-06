<?php

namespace App\Http\Controllers;

use App\Models\WatchedPage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Pausing is the middle ground between creeping and deleting: the schedule
 * stops, but the price history, the run log and the changes all stay put.
 *
 * It lives outside {@see WatchedPageController} because it is one click, not
 * a form — a page should be stoppable without editing anything else about it.
 */
class WatchedPagePauseController extends Controller
{
    /**
     * Stop creeping a page.
     */
    public function store(WatchedPage $watchedPage): RedirectResponse
    {
        $this->authorize('update', $watchedPage);

        $watchedPage->pause();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Creeping paused. Nothing has been deleted.')]);

        return back();
    }

    /**
     * Start creeping it again.
     */
    public function destroy(WatchedPage $watchedPage): RedirectResponse
    {
        $this->authorize('update', $watchedPage);

        $watchedPage->resume();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Creeping resumed.')]);

        return back();
    }
}
