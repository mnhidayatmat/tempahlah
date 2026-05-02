<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GuestOtpController;
use App\Http\Controllers\Auth\TenantRegisterController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Tenant\BookingController;
use App\Http\Controllers\Tenant\CalendarController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\GuestController;
use App\Http\Controllers\Tenant\HousekeepingController;
use App\Http\Controllers\Tenant\IntegrationController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\Tenant\PropertyController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\SubscriptionController;
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
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/properties',           [PropertyController::class, 'index'])->name('properties.index');
    Route::get('/properties/create',    [PropertyController::class, 'create'])->name('properties.create');
    Route::post('/properties',          [PropertyController::class, 'store'])->name('properties.store');
    Route::get('/properties/{id}',      [PropertyController::class, 'show'])->name('properties.show');
    Route::get('/properties/{property:public_id}/edit', function (\App\Models\Property $property) {
        return view('tenant.properties.edit', compact('property'));
    })->name('properties.edit');

    Route::get('/calendar',             [CalendarController::class, 'index'])->name('calendar');

    Route::get('/bookings',             [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{id}',        [BookingController::class, 'show'])->name('bookings.show');

    Route::get('/guests',               [GuestController::class, 'index'])->name('guests.index');
    Route::get('/housekeeping',         [HousekeepingController::class, 'index'])->name('housekeeping.index');
    Route::get('/payments',             [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/reports',              [ReportController::class, 'index'])->name('reports.index');
    Route::get('/settings',             [SettingsController::class, 'index'])->name('settings.index');

    Route::get('/subscription',         [SubscriptionController::class, 'index'])->name('subscription');
    Route::get('/integrations',         [IntegrationController::class, 'index'])->name('integrations');
});

require __DIR__.'/auth-extra.php';
