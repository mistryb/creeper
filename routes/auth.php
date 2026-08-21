<?php

use App\Http\Controllers\Auth\LoginCodeController;
use App\Http\Controllers\Auth\LogoutController;
use Illuminate\Support\Facades\Route;

/*
 * Signing in and signing up are one flow: give an address, get a code, type it
 * back. Both POST routes are throttled — the whole reason a six digit code is
 * safe is that it cannot be guessed at speed. See App\Auth\LoginCodes.
 */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginCodeController::class, 'create'])->name('login');

    Route::post('login', [LoginCodeController::class, 'store'])
        ->middleware('throttle:login-code')
        ->name('login.store');

    Route::get('login/verify', [LoginCodeController::class, 'edit'])
        ->name('login.verify');

    Route::post('login/verify', [LoginCodeController::class, 'update'])
        ->middleware('throttle:login-verify')
        ->name('login.verify.store');

    /*
     * There is no separate sign-up any more. Kept so older links, bookmarks
     * and emails do not dead-end.
     */
    Route::redirect('register', '/login')->name('register');
});

Route::post('logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');
