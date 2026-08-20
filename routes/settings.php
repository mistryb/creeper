<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\EnsureBillingIsEnabled;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    /*
     * The user's own model API key. Writes are throttled because a key is a
     * credential, and swapping one repeatedly is never a legitimate rhythm.
     */
    Route::get('settings/api-key', [ApiKeyController::class, 'edit'])->name('api-key.edit');

    Route::put('settings/api-key', [ApiKeyController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('api-key.update');

    Route::delete('settings/api-key', [ApiKeyController::class, 'destroy'])
        ->middleware('throttle:6,1')
        ->name('api-key.destroy');

    /*
     * Billing only exists when this install is running as a paid service.
     * Self-hosted installs 404 here and never see the nav item.
     */
    Route::middleware(EnsureBillingIsEnabled::class)->group(function () {
        Route::get('settings/billing', [BillingController::class, 'show'])->name('billing.show');
        Route::post('settings/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::get('settings/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
