<?php

use App\Http\Controllers\CreepRunController;
use App\Http\Controllers\CreepTargetController;
use App\Http\Controllers\CreepTargetPauseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\HomeController;
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

    Route::resource('creep-targets', CreepTargetController::class)->except('edit');

    /*
     * Creeping on demand costs the same as creeping on a schedule, so the
     * button is throttled — no amount of clicking should outrun a plan.
     */
    Route::post('creep-targets/{creep_target}/runs', [CreepRunController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('creep-targets.runs.store');

    /*
     * Stopping without deleting. Pausing is a one-click action rather than a
     * field on the settings form, because "stop creeping this" should never
     * require a trip through everything else about the target.
     */
    Route::post('creep-targets/{creep_target}/pause', [CreepTargetPauseController::class, 'store'])
        ->name('creep-targets.pause.store');

    Route::delete('creep-targets/{creep_target}/pause', [CreepTargetPauseController::class, 'destroy'])
        ->name('creep-targets.pause.destroy');
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
