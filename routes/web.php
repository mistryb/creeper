<?php

use App\Http\Controllers\Admin\BusinessController as AdminBusinessController;
use App\Http\Controllers\Admin\OverviewController as AdminOverviewController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CompetitorController;
use App\Http\Controllers\CompetitorDiscoveryController;
use App\Http\Controllers\CurrentBusinessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\DigestController;
use App\Http\Controllers\HomeController;
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

    /*
     * Finding a company's competitors again, on demand. Discovery runs by
     * itself when a company is set up; this is for when the market has moved
     * or the first pass missed somebody. Spends the company's key, so it is
     * throttled like everything else that costs money.
     */
    Route::post('businesses/{business}/discovery', [CompetitorDiscoveryController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('businesses.discovery.store');

    /*
     * Digests go out on the company's schedule; this is the history, and the
     * button for sending one now rather than waiting.
     */
    Route::get('businesses/{business}/digests', [DigestController::class, 'index'])
        ->name('businesses.digests.index');

    Route::post('businesses/{business}/digests', [DigestController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('businesses.digests.store');

    Route::get('digests/{digest}', [DigestController::class, 'show'])
        ->name('digests.show');

    /*
     * Picking a business in the sidebar chooser. Switching is its own write
     * rather than a side effect of viewing a business, so prefetching a link
     * can never change which business the app is working on.
     */
    Route::put('current-business', [CurrentBusinessController::class, 'update'])
        ->name('current-business.update');

    /*
     * Competitors belong to a business; once one exists it is addressed on
     * its own (`/competitors/{competitor}`).
     */
    Route::resource('businesses.competitors', CompetitorController::class)
        ->shallow()
        ->except('edit');
});

/*
 * The admin panel, for the addresses in ADMIN_EMAILS: every account and its
 * plan, every company, and what the install earns. Comping writes to another
 * account, so it is throttled like any other write that matters.
 */
Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminOverviewController::class)->name('overview');

    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');

    Route::post('users/{user}/comp', [AdminUserController::class, 'comp'])
        ->middleware('throttle:30,1')
        ->name('users.comp');

    Route::delete('users/{user}/comp', [AdminUserController::class, 'uncomp'])
        ->middleware('throttle:30,1')
        ->name('users.uncomp');

    Route::get('businesses', [AdminBusinessController::class, 'index'])->name('businesses.index');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
