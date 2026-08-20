<?php

use App\Http\Controllers\CreepRunController;
use App\Http\Controllers\CreepTargetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Webhooks\CreepCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

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
});

/*
 * Where an asynchronous creeping agent posts its results. The signature on
 * the URL is the only credential — it is generated per run and expires.
 */
Route::post('webhooks/creep/{run}', CreepCallbackController::class)
    ->middleware('signed')
    ->name('creep.callback');

require __DIR__.'/settings.php';
