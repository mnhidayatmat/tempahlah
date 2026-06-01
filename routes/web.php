<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GuestOtpController;
use App\Http\Controllers\Auth\TenantRegisterController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Public\PublicBookingController;
use App\Http\Controllers\Public\TenantHomeController;
use App\Http\Controllers\Tenant\BookingController;
use App\Http\Controllers\Tenant\CalendarController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\GuestController;
use App\Http\Controllers\Tenant\HousekeepingController;
use App\Http\Controllers\Tenant\IntegrationController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\Tenant\PropertyController;
use App\Http\Controllers\Tenant\PropertyPhotoController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Domain-agnostic — works on apex AND every tenant subdomain so the locale
// toggle works without bouncing cross-domain.
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])
    ->whereIn('locale', ['ms', 'en'])
    ->name('locale.switch');

// Toyyibpay billReturnUrl lands here. Public + unauthenticated — the page
// just shows the canonical Payment.status; webhook does the real work.
Route::get('/payments/return/{payment}', [\App\Http\Controllers\PaymentReturnController::class, 'show'])
    ->name('payments.return');

// -----------------------------------------------------------------------------
// Tenant public subdomain — acme.tempahlah.com → tenant `acme`'s booking page.
// Resolved by the {tenant_slug} domain parameter via ResolveTenantFromSubdomain.
// Registered FIRST so subdomain matching wins over the catch-all root group.
// -----------------------------------------------------------------------------
Route::domain('{tenant_slug}.'.config('app.tenant_domain'))
    ->middleware('tenant.subdomain')
    ->name('tenant-public.')
    ->group(function () {
        Route::get('/', [TenantHomeController::class, 'index'])->name('home');

        // Public direct-booking flow: guest fills the form on home, we
        // create a pending booking + Toyyibpay deposit bill + invoice,
        // then send the pay link via email + WhatsApp.
        Route::post('/book', [PublicBookingController::class, 'store'])
            ->middleware('throttle:booking-create-public')
            ->name('booking.store');
        Route::get('/book/sent/{reference}', [PublicBookingController::class, 'sent'])
            ->name('booking.sent');
    });

// -----------------------------------------------------------------------------
// Root / apex domain — tempahlah.com.
// All existing app routes live here.
// -----------------------------------------------------------------------------
Route::domain(config('app.tenant_domain'))->group(function () {
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

    // OAuth callbacks (platform-owned OAuth apps — Tempahlah holds the
    // client_id/secret; tenants just grant access to their own account).
    // Lives at /oauth/google/* (not /dashboard/oauth/*) because the redirect
    // URI registered with Google must be a fixed, short path.
    Route::middleware(['auth', 'tenant.require'])
        ->prefix('oauth/google')
        ->name('oauth.google.')
        ->group(function () {
            Route::get('/start',    [\App\Http\Controllers\OAuth\GoogleCalendarOAuthController::class, 'start'])->name('start');
            Route::get('/callback', [\App\Http\Controllers\OAuth\GoogleCalendarOAuthController::class, 'callback'])->name('callback');
        });

    // Marketplace (public)
    Route::prefix('marketplace')->name('marketplace.')->middleware('throttle:marketplace-search')->group(function () {
        Route::get('/', [App\Http\Controllers\Marketplace\MarketplaceController::class, 'search'])->name('search');
        Route::get('/{listing:slug}', [App\Http\Controllers\Marketplace\MarketplaceController::class, 'show'])->name('show');
    });

    // Tenant dashboard (auth + tenant context required)
    Route::middleware(['auth', 'tenant.require'])->prefix('dashboard')->name('tenant.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/properties',                           [PropertyController::class, 'index'])->name('properties.index');
        Route::get('/properties/create',                    [PropertyController::class, 'create'])->name('properties.create');
        Route::post('/properties',                          [PropertyController::class, 'store'])->name('properties.store');
        Route::get('/properties/{id}',                      [PropertyController::class, 'show'])->name('properties.show')->whereNumber('id');
        Route::get('/properties/{property:public_id}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
        Route::patch('/properties/{property:public_id}',          [PropertyController::class, 'update'])->name('properties.update');
        Route::patch('/properties/{property:public_id}/policies', [PropertyController::class, 'updatePolicies'])->name('properties.policies.update');
        Route::patch('/properties/{property:public_id}/fee',      [PropertyController::class, 'updateFee'])->name('properties.fee.update');
        Route::delete('/properties/{property:public_id}',         [PropertyController::class, 'destroy'])->name('properties.destroy');

        // Property photos (upload to DO Spaces, delete, set hero, tag category)
        Route::post('/properties/{property:public_id}/photos',                  [PropertyPhotoController::class, 'store'])->name('properties.photos.store');
        Route::delete('/properties/{property:public_id}/photos/{photo}',        [PropertyPhotoController::class, 'destroy'])->name('properties.photos.destroy');
        Route::post('/properties/{property:public_id}/photos/{photo}/hero',     [PropertyPhotoController::class, 'setHero'])->name('properties.photos.hero');
        Route::patch('/properties/{property:public_id}/photos/{photo}/category',[PropertyPhotoController::class, 'updateCategory'])->name('properties.photos.category');

        // Dynamic pricing rules (weekend uplift, holiday markup, custom date ranges)
        Route::post('/properties/{property:public_id}/pricing-rules',               [\App\Http\Controllers\Tenant\PricingRuleController::class, 'store'])->name('properties.pricing.store');
        Route::patch('/properties/{property:public_id}/pricing-rules/{rule}',       [\App\Http\Controllers\Tenant\PricingRuleController::class, 'update'])->name('properties.pricing.update');
        Route::delete('/properties/{property:public_id}/pricing-rules/{rule}',      [\App\Http\Controllers\Tenant\PricingRuleController::class, 'destroy'])->name('properties.pricing.destroy');
        Route::post('/properties/{property:public_id}/pricing-rules/{rule}/toggle', [\App\Http\Controllers\Tenant\PricingRuleController::class, 'toggle'])->name('properties.pricing.toggle');

        // Malaysian public-holiday lookup for the pricing-rule form's
        // "holiday" rule_type. Server-side cache 24h per year so opening
        // Add rule doesn't re-fetch upstream every time.
        Route::get('/api/public-holidays/{year}', [\App\Http\Controllers\Tenant\PublicHolidayController::class, 'index'])
            ->whereNumber('year')
            ->name('api.public-holidays');

        Route::get('/calendar',             [CalendarController::class, 'index'])->name('calendar');

        Route::get('/bookings',             [BookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create',      [BookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings',            [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{id}',        [BookingController::class, 'show'])->name('bookings.show')->whereNumber('id');
        Route::post('/bookings/{id}/mark-paid',      [BookingController::class, 'markPaid'])->name('bookings.mark-paid');
        Route::post('/bookings/{id}/send-reminder', [BookingController::class, 'sendReminder'])->name('bookings.send-reminder');
        Route::post('/bookings/{id}/whatsapp',      [BookingController::class, 'sendWhatsapp'])->name('bookings.whatsapp');
        Route::post('/bookings/{id}/pay-link',      [BookingController::class, 'payLink'])->name('bookings.pay-link');
        Route::post('/bookings/{id}/cancel',        [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::delete('/bookings/{id}',             [BookingController::class, 'destroy'])->name('bookings.destroy')->whereNumber('id');

        Route::get('/guests',               [GuestController::class, 'index'])->name('guests.index');
        Route::get('/guests/export.csv',    [GuestController::class, 'exportCsv'])->name('guests.export');
        Route::get('/housekeeping',         [HousekeepingController::class, 'index'])->name('housekeeping.index');
        Route::get('/housekeeping/print.pdf',          [HousekeepingController::class, 'printRunSheet'])->name('housekeeping.print');
        Route::post('/housekeeping/cleaning',          [HousekeepingController::class, 'storeCleaning'])->name('housekeeping.cleaning.store');
        Route::post('/housekeeping/laundry',           [HousekeepingController::class, 'storeLaundry'])->name('housekeeping.laundry.store');
        Route::patch('/housekeeping/cleaning/{id}',    [HousekeepingController::class, 'updateCleaning'])->name('housekeeping.cleaning.update');
        Route::patch('/housekeeping/laundry/{id}',     [HousekeepingController::class, 'updateLaundry'])->name('housekeeping.laundry.update');
        Route::patch('/housekeeping/maintenance/{id}', [HousekeepingController::class, 'updateMaintenance'])->name('housekeeping.maintenance.update');
        Route::get('/payments',             [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/export.csv',  [PaymentController::class, 'exportCsv'])->name('payments.export');
        Route::get('/reports',              [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export.pdf',   [ReportController::class, 'exportPdf'])->name('reports.export-pdf');
        Route::get('/settings',             [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings',           [SettingsController::class, 'update'])->name('settings.update');

        Route::get('/subscription',         [SubscriptionController::class, 'index'])->name('subscription');
        Route::post('/subscription/change', [SubscriptionController::class, 'change'])->name('subscription.change');
        Route::get('/integrations',                       [IntegrationController::class, 'index'])->name('integrations.index');
        Route::get('/integrations/{provider}',            [IntegrationController::class, 'show'])->name('integrations.show');
        Route::patch('/integrations/{provider}',          [IntegrationController::class, 'update'])->name('integrations.update');
        Route::delete('/integrations/{provider}',         [IntegrationController::class, 'disconnect'])->name('integrations.disconnect');
        Route::post('/integrations/toyyibpay/test',       [IntegrationController::class, 'testToyyibpay'])->name('integrations.toyyibpay.test');
        Route::post('/integrations/google_calendar/select-calendar', [\App\Http\Controllers\Tenant\GoogleCalendarController::class, 'selectCalendar'])->name('integrations.google_calendar.select');
    });

    require __DIR__.'/auth-extra.php';
});
