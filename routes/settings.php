<?php

use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    /*
     * Confirming a new address. Throttled like the sign-in code it reuses —
     * six digits is only safe while guessing is slow.
     */
    Route::post('settings/profile/email', [ProfileController::class, 'confirmEmail'])
        ->middleware('throttle:login-verify')
        ->name('profile.email.confirm');

    Route::delete('settings/profile/email', [ProfileController::class, 'cancelEmail'])
        ->name('profile.email.cancel');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
     * No password to re-enter before reaching this screen: there isn't one. The
     * only thing here is revoking other browsers, and being signed in on this
     * one is the whole authority for that.
     */
    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::delete('settings/security/sessions', [SecurityController::class, 'destroy'])
        ->middleware('throttle:6,1')
        ->name('security.sessions.destroy');

    /*
     * The user's own keyring. Every model API key Creeper spends lives here —
     * there is none in the environment — and each target picks the one it
     * spends. Writes are throttled because a key is a credential, and adding
     * or dropping them repeatedly is never a legitimate rhythm.
     */
    Route::get('settings/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');

    Route::post('settings/api-keys', [ApiKeyController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('api-keys.store');

    Route::delete('settings/api-keys/{api_key}', [ApiKeyController::class, 'destroy'])
        ->middleware('throttle:6,1')
        ->name('api-keys.destroy');
});
