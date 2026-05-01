<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GuestOtpController;
use App\Http\Controllers\Auth\TenantRegisterController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])
    ->whereIn('locale', ['ms', 'en'])
    ->name('locale.switch');

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Tenant signup + login
Route::middleware('guest')->group(function () {
    Route::get('/register', [TenantRegisterController::class, 'show'])->name('register');
    Route::post('/register', [TenantRegisterController::class, 'store'])
        ->middleware('throttle:auth-login');

    Route::get('/login', [AuthenticatedSessionController::class, 'show'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth-login');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Guest OTP (marketplace booking) — public
Route::prefix('otp')->name('otp.')->group(function () {
    Route::post('/send', [GuestOtpController::class, 'send'])
        ->middleware('throttle:auth-otp-send')
        ->name('send');
    Route::post('/verify', [GuestOtpController::class, 'verify'])
        ->middleware('throttle:auth-otp-send')
        ->name('verify');
});

// Tenant onboarding bridge (when user has no tenant)
Route::get('/onboard', function () {
    return redirect()->route('register');
})->name('tenant.onboard');

// Marketplace (public)
Route::prefix('marketplace')->name('marketplace.')->middleware('throttle:marketplace-search')->group(function () {
    Route::get('/', [App\Http\Controllers\Marketplace\MarketplaceController::class, 'search'])->name('search');
    Route::get('/{listing:slug}', [App\Http\Controllers\Marketplace\MarketplaceController::class, 'show'])->name('show');
});

// Tenant dashboard (auth + tenant context required)
Route::middleware(['auth', 'tenant.require'])->prefix('dashboard')->name('tenant.')->group(function () {
    Route::get('/', function () {
        return view('tenant.dashboard');
    })->name('dashboard');

    Route::get('/properties', function () {
        return view('tenant.properties.index');
    })->name('properties.index');

    Route::get('/properties/create', function () {
        return view('tenant.properties.create');
    })->name('properties.create');

    Route::get('/properties/{property:public_id}/edit', function (\App\Models\Property $property) {
        return view('tenant.properties.edit', compact('property'));
    })->name('properties.edit');

    Route::get('/subscription', function () {
        return view('tenant.subscription');
    })->name('subscription');
});

require __DIR__.'/auth-extra.php';
