<?php

use App\Http\Controllers\BusinessAnalysisController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CompetitorAnalysisController;
use App\Http\Controllers\CompetitorController;
use App\Http\Controllers\CreepRunController;
use App\Http\Controllers\CurrentBusinessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandscapeController;
use App\Http\Controllers\WatchedPageController;
use App\Http\Controllers\WatchedPagePauseController;
use App\Http\Controllers\Webhooks\CreepCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
 * How to get your own copy running: the repository, and a prompt to hand an
 * agent so it deploys Creeper on Laravel Cloud for you.
 */
Route::get('deploy', DeployController::class)->name('deploy');

/*
 * The living style guide: every token, type style and component in one place,
 * so a new screen can be built by looking rather than by guessing. It documents
 * the design system, so it is never part of the shipped product.
 */
if (! app()->isProduction()) {
    Route::inertia('design', 'design')->name('design');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('businesses', BusinessController::class)->except('edit');

    Route::get('businesses/{business}/analysis', [BusinessAnalysisController::class, 'index'])
        ->name('businesses.analysis.index');

    /*
     * Each run spends the user's own key, so — like creeping on demand — the
     * button is throttled.
     */
    Route::post('businesses/{business}/analysis', [BusinessAnalysisController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('businesses.analysis.store');

    /*
     * The business set against all its competitors, shown on the dashboard.
     * Spends the user's key, so throttled like the other analyses.
     */
    Route::post('businesses/{business}/landscape', [LandscapeController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('businesses.landscape.store');

    /*
     * Picking a business in the sidebar chooser. Switching is its own write
     * rather than a side effect of viewing a business, so prefetching a link
     * can never change which business the app is working on.
     */
    Route::put('current-business', [CurrentBusinessController::class, 'update'])
        ->name('current-business.update');

    /*
     * Competitors belong to a business; once one exists it is addressed on
     * its own (`/competitors/{competitor}`), and so are its watched pages.
     */
    Route::resource('businesses.competitors', CompetitorController::class)
        ->shallow()
        ->except('edit');

    Route::get('competitors/{competitor}/analysis', [CompetitorAnalysisController::class, 'index'])
        ->name('competitors.analysis.index');

    Route::post('competitors/{competitor}/analysis', [CompetitorAnalysisController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('competitors.analysis.store');

    Route::resource('competitors.watched-pages', WatchedPageController::class)
        ->shallow()
        ->except(['index', 'edit']);

    /*
     * Creeping on demand costs the same as creeping on a schedule, so the
     * button is throttled — no amount of clicking should outrun a plan.
     */
    Route::post('watched-pages/{watched_page}/runs', [CreepRunController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('watched-pages.runs.store');

    /*
     * Stopping without deleting. Pausing is a one-click action rather than a
     * field on the settings form, because "stop creeping this" should never
     * require a trip through everything else about the page.
     */
    Route::post('watched-pages/{watched_page}/pause', [WatchedPagePauseController::class, 'store'])
        ->name('watched-pages.pause.store');

    Route::delete('watched-pages/{watched_page}/pause', [WatchedPagePauseController::class, 'destroy'])
        ->name('watched-pages.pause.destroy');
});

/*
 * Where an asynchronous creeping agent posts its results. The signature on
 * the URL is the only credential — it is generated per run and expires.
 */
Route::post('webhooks/creep/{run}', CreepCallbackController::class)
    ->middleware('signed')
    ->name('creep.callback');

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
