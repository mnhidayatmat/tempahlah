<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GuestOtpController;
use App\Http\Controllers\Auth\TenantRegisterController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Public\PublicBookingController;
use App\Http\Controllers\Public\TenantHomeController;
use App\Http\Controllers\Tenant\BookingController;
use App\Http\Controllers\Tenant\BookingDocumentController;
use App\Http\Controllers\Tenant\CalendarController;
use App\Http\Controllers\Tenant\CleanerController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\DirectoryController;
use App\Http\Controllers\Tenant\ExpenseController;
use App\Http\Controllers\Tenant\MaintenancePersonController;
use App\Http\Controllers\Tenant\GuestController;
use App\Http\Controllers\Tenant\HousekeepingController;
use App\Http\Controllers\Tenant\IntegrationController;
use App\Http\Controllers\Tenant\LaundryVendorController;
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

// Gateway return URL lands here. Public + unauthenticated — the page just shows
// the canonical Payment.status (and reconciles server-side); the webhook does
// the real work.
//
// POST twin: Toyyibpay and Billplz send the guest back with a GET, but SecurePay
// POSTs its result to `redirect_url`. Same handler either way — the posted body
// is never trusted, we re-check with the gateway. The POST is CSRF-exempt (see
// bootstrap/app.php) because the form is submitted by the gateway, not by us.
Route::match(['get', 'post'], '/payments/return/{payment}', [\App\Http\Controllers\PaymentReturnController::class, 'show'])
    ->name('payments.return');

// Where Billplz sends a tenant back after paying their RM 49/mo subscription.
// Public + unauthenticated on purpose: the invoice is resolved from the bill id,
// and the redirect's `paid` flag is never trusted — we re-ask Billplz. Registered
// outside the domain groups so it resolves on any host, like payments.return.
Route::get('/subscription/billing/return', [\App\Http\Controllers\Tenant\SubscriptionCheckoutController::class, 'paymentReturn'])
    ->name('subscription.billing.return');

// Where Stripe Checkout sends the tenant back. Informational only — the webhook
// is authoritative. Registered outside the domain groups so it resolves on any
// host, like the Billplz return above.
Route::get('/subscription/stripe/return', [\App\Http\Controllers\Tenant\StripeCheckoutController::class, 'return'])
    ->middleware('auth')
    ->name('subscription.stripe.return');

// Public iCal busy-feed for one room, addressed by an unguessable token. This
// is the URL the host pastes into Airbnb / Booking.com to import their booked
// dates. Unauthenticated (OTA crawlers fetch it) + rate-limited. Registered
// outside the domain groups so it resolves on any host. Token constrained so
// the literal `.ics` suffix isn't captured into the parameter.
Route::get('/calendar/{token}.ics', [\App\Http\Controllers\Public\ChannelFeedController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:ical-feed')
    ->name('ical.feed');

// -----------------------------------------------------------------------------
// Tenant public subdomain — acme.tempahlah.com → tenant `acme`'s booking page.
// PRO-ONLY perk: the tenant.subdomain middleware 404s free tenants (they use
// the canonical tempahlah.com/{slug} path below). Registered FIRST so subdomain
// matching wins over the apex group. Only the landing lives here — the page's
// forms + signed magic-links resolve to the tenant-public.* PATH routes
// (tempahlah.com/slug/...), which work for both free and pro, so there's no
// need to duplicate the booking flow on the subdomain.
// -----------------------------------------------------------------------------
Route::domain('{tenant_slug}.'.config('app.tenant_domain'))
    ->middleware('tenant.subdomain')
    ->group(function () {
        Route::get('/', [TenantHomeController::class, 'index'])->name('tenant-sub.home');
    });

// -----------------------------------------------------------------------------
// Root / apex domain — tempahlah.com.
// All existing app routes live here.
// -----------------------------------------------------------------------------
Route::domain(config('app.tenant_domain'))->group(function () {
    // Root is the host-acquisition landing page (the marketing/welcome page).
    // Keeps the `hosts` route name so every existing route('hosts') reference
    // points here. The old /hosts URL 301-redirects to / for bookmarks/SEO.
    Route::get('/', fn () => view('welcome'))->name('hosts');
    Route::redirect('/hosts', '/', 301);

    // Affiliate short link — tempahlah.com/r/{code}. Captures the referral
    // cookie (60 days, last click wins) + counts the click, then lands the
    // prospect on the host-acquisition page. ?ref={code} on any web page does
    // the same via the CaptureReferralCode middleware.
    Route::get('/r/{code}', function (\Illuminate\Http\Request $request, string $code) {
        $request->query->set('ref', $code);
        \App\Support\Affiliate\ReferralAttribution::capture($request);

        return redirect()->route('hosts');
    })->where('code', '[A-Za-z0-9\-]{1,24}')->name('affiliate.visit');

    // Affiliate earnings statement — an EXTERNAL affiliate (no homestay login)
    // sees their own commissions + sets payout bank details. Addressed by the
    // referral CODE (their public share handle), with the legacy statement token
    // still accepted. No auth; throttled so the form can't be hammered.
    Route::get('/affiliate/{token}', [\App\Http\Controllers\Public\AffiliateStatementController::class, 'show'])
        ->where('token', '[A-Za-z0-9\-]+')
        ->middleware('throttle:60,1')
        ->name('affiliate.statement');
    Route::post('/affiliate/{token}/bank', [\App\Http\Controllers\Public\AffiliateStatementController::class, 'updateBank'])
        ->where('token', '[A-Za-z0-9\-]+')
        ->middleware('throttle:20,1')
        ->name('affiliate.statement.bank');

    // Public legal pages linked from the register form + footers. Bilingual
    // via the app locale; static content, so plain view routes.
    Route::view('/terms', 'legal.terms')->name('legal.terms');
    Route::view('/privacy', 'legal.privacy')->name('legal.privacy');

    // Tenant signup + login
    Route::middleware('guest')->group(function () {
        Route::get('/register', [TenantRegisterController::class, 'show'])->name('register');
        Route::post('/register', [TenantRegisterController::class, 'store'])
            ->middleware('throttle:auth-login');

        Route::get('/login', [AuthenticatedSessionController::class, 'show'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:auth-login');

        // "Continue with Google" — single OAuth flow that handles BOTH
        // sign-in (existing email) and sign-up (creates a tenant on the
        // fly from the Google profile name).
        Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'start'])
            ->middleware('throttle:auth-login')
            ->name('auth.google.start');
        Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])
            ->middleware('throttle:auth-login')
            ->name('auth.google.callback');
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

    // "Name your homestay" — one-step onboarding for an authenticated but
    // tenant-less host (chiefly Google sign-ups). The host types the business
    // name; nothing is auto-generated from their Google profile / email.
    Route::middleware('auth')->group(function () {
        Route::get('/onboard/homestay', [\App\Http\Controllers\Onboarding\CreateHomestayController::class, 'show'])
            ->name('onboarding.homestay');
        Route::post('/onboard/homestay', [\App\Http\Controllers\Onboarding\CreateHomestayController::class, 'store'])
            ->middleware('throttle:auth-login')
            ->name('onboarding.homestay.store');
    });

    // Tenant onboarding bridge (when user has no tenant). An authenticated
    // user is missing a workspace → send them to name their homestay; a guest
    // → the full signup form.
    Route::get('/onboard', function (\Illuminate\Http\Request $request) {
        return $request->user()
            ? redirect()->route('onboarding.homestay')
            : redirect()->route('register');
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

    // Marketplace (public). The traveller-facing search lives at /marketplace;
    // it keeps the `marketplace.search` name so every existing
    // route('marketplace.search') reference points here. The listing detail
    // stays under /marketplace/{slug}.
    Route::get('/marketplace', [App\Http\Controllers\Marketplace\MarketplaceController::class, 'search'])
        ->middleware('throttle:marketplace-search')
        ->name('marketplace.search');
    Route::get('/marketplace/{listing:slug}', [App\Http\Controllers\Marketplace\MarketplaceController::class, 'show'])
        ->middleware('throttle:marketplace-search')
        ->name('marketplace.show');

    // SEO: XML sitemap + location landing pages (unthrottled for crawlability).
    Route::get('/sitemap.xml', [App\Http\Controllers\Marketplace\SitemapController::class, 'index'])->name('sitemap');
    Route::get('/homestay/{state}', [App\Http\Controllers\Marketplace\LocationController::class, 'show'])->name('marketplace.location.state');
    Route::get('/homestay/{state}/{town}', [App\Http\Controllers\Marketplace\LocationController::class, 'show'])->name('marketplace.location.town');

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

        // Marketplace opt-in — list / remove this homestay on tempahlah.com.
        Route::post('/properties/{property:public_id}/marketplace',   [PropertyController::class, 'publishMarketplace'])->name('properties.marketplace.publish');
        Route::delete('/properties/{property:public_id}/marketplace', [PropertyController::class, 'unpublishMarketplace'])->name('properties.marketplace.unpublish');

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
        Route::get('/bookings/send-form',   [BookingController::class, 'sendForm'])->name('bookings.send-form');
        Route::get('/bookings/quote',       [BookingController::class, 'quote'])->name('bookings.quote');
        Route::post('/bookings',            [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{id}/edit',   [BookingController::class, 'edit'])->name('bookings.edit')->whereNumber('id');
        Route::patch('/bookings/{id}/status', [BookingController::class, 'updateStatus'])->name('bookings.update-status')->whereNumber('id');
        Route::patch('/bookings/{id}',      [BookingController::class, 'update'])->name('bookings.update')->whereNumber('id');
        Route::get('/bookings/{id}',        [BookingController::class, 'show'])->name('bookings.show')->whereNumber('id');
        Route::post('/bookings/{id}/mark-paid',      [BookingController::class, 'markPaid'])->name('bookings.mark-paid');
        Route::post('/bookings/{id}/send-reminder', [BookingController::class, 'sendReminder'])->name('bookings.send-reminder');
        Route::post('/bookings/{id}/whatsapp',      [BookingController::class, 'sendWhatsapp'])->name('bookings.whatsapp');
        Route::post('/bookings/{id}/pay-link',      [BookingController::class, 'payLink'])->name('bookings.pay-link');

        // Invoice + receipt documents: view the PDF, or email / WhatsApp it to the guest.
        Route::get('/bookings/{id}/documents/{doc}', [BookingDocumentController::class, 'show'])->name('bookings.documents.show')->whereNumber('id')->where('doc', 'invoice|receipt');
        Route::post('/bookings/{id}/documents/send', [BookingDocumentController::class, 'send'])->name('bookings.documents.send')->whereNumber('id');
        Route::post('/bookings/{id}/cancel',        [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{id}/check-out',     [BookingController::class, 'checkOut'])->name('bookings.check-out')->whereNumber('id');
        Route::post('/bookings/{id}/request-review', [BookingController::class, 'requestReview'])->name('bookings.request-review')->whereNumber('id');
        Route::delete('/bookings/{id}',             [BookingController::class, 'destroy'])->name('bookings.destroy')->whereNumber('id');

        // Refunds — auto-created on checkout, host updates status here.
        Route::post('/bookings/{id}/refunds',       [\App\Http\Controllers\Tenant\RefundController::class, 'store'])->name('refunds.store')->whereNumber('id');
        Route::patch('/refunds/{id}',               [\App\Http\Controllers\Tenant\RefundController::class, 'update'])->name('refunds.update')->whereNumber('id');
        Route::post('/refunds/{id}/request-bank',   [\App\Http\Controllers\Tenant\RefundController::class, 'requestBankDetails'])->name('refunds.request-bank')->whereNumber('id');

        Route::get('/guests',               [GuestController::class, 'index'])->name('guests.index');
        Route::get('/guests/export.csv',    [GuestController::class, 'exportCsv'])->name('guests.export');
        // Bind by {guest:id} — User's route key is public_id (ULID) via
        // HasUlidPublicId, so a plain {guest} would 404 on the numeric id we pass.
        Route::get('/guests/{guest:id}',       [GuestController::class, 'show'])->name('guests.show')->whereNumber('guest');
        Route::post('/guests/{guest:id}/report', [GuestController::class, 'report'])->name('guests.report')->whereNumber('guest');
        Route::get('/testimonials',         [\App\Http\Controllers\Tenant\TestimonialController::class, 'index'])->name('testimonials.index');
        Route::post('/testimonials/{id}/appeal', [\App\Http\Controllers\Tenant\TestimonialController::class, 'appeal'])->name('testimonials.appeal')->whereNumber('id');
        Route::get('/housekeeping',         [HousekeepingController::class, 'index'])->name('housekeeping.index');
        Route::get('/housekeeping/print.pdf',          [HousekeepingController::class, 'printRunSheet'])->name('housekeeping.print');
        Route::post('/housekeeping/auto-toggle',       [HousekeepingController::class, 'toggleAutoGenerate'])->name('housekeeping.auto-toggle');
        Route::post('/housekeeping/generate',          [HousekeepingController::class, 'generateSchedule'])->name('housekeeping.generate');
        Route::post('/housekeeping/cleaning',          [HousekeepingController::class, 'storeCleaning'])->name('housekeeping.cleaning.store');
        Route::post('/housekeeping/laundry',           [HousekeepingController::class, 'storeLaundry'])->name('housekeeping.laundry.store');
        Route::post('/housekeeping/maintenance',       [HousekeepingController::class, 'storeMaintenance'])->name('housekeeping.maintenance.store');
        Route::patch('/housekeeping/cleaning/{id}',    [HousekeepingController::class, 'updateCleaning'])->name('housekeeping.cleaning.update');
        Route::delete('/housekeeping/cleaning/{id}',   [HousekeepingController::class, 'destroyCleaning'])->name('housekeeping.cleaning.destroy');
        Route::patch('/housekeeping/laundry/{id}',     [HousekeepingController::class, 'updateLaundry'])->name('housekeeping.laundry.update');
        Route::delete('/housekeeping/laundry/{id}',    [HousekeepingController::class, 'destroyLaundry'])->name('housekeeping.laundry.destroy');
        Route::patch('/housekeeping/maintenance/{id}', [HousekeepingController::class, 'updateMaintenance'])->name('housekeeping.maintenance.update');
        Route::delete('/housekeeping/maintenance/{id}', [HousekeepingController::class, 'destroyMaintenance'])->name('housekeeping.maintenance.destroy');
        Route::get('/directory',            [DirectoryController::class, 'index'])->name('directory.index');
        Route::post('/cleaners',            [CleanerController::class, 'store'])->name('cleaners.store');
        Route::patch('/cleaners/{id}',      [CleanerController::class, 'update'])->name('cleaners.update');
        Route::delete('/cleaners/{id}',     [CleanerController::class, 'destroy'])->name('cleaners.destroy');
        Route::post('/laundry-vendors',     [LaundryVendorController::class, 'store'])->name('laundry-vendors.store');
        Route::patch('/laundry-vendors/{id}', [LaundryVendorController::class, 'update'])->name('laundry-vendors.update');
        Route::delete('/laundry-vendors/{id}', [LaundryVendorController::class, 'destroy'])->name('laundry-vendors.destroy');
        Route::post('/maintenance-persons', [MaintenancePersonController::class, 'store'])->name('maintenance-persons.store');
        Route::patch('/maintenance-persons/{id}', [MaintenancePersonController::class, 'update'])->name('maintenance-persons.update');
        Route::delete('/maintenance-persons/{id}', [MaintenancePersonController::class, 'destroy'])->name('maintenance-persons.destroy');
        Route::get('/payments',             [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/export.csv',  [PaymentController::class, 'exportCsv'])->name('payments.export');
        Route::get('/reports',              [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export.pdf',   [ReportController::class, 'exportPdf'])->name('reports.export-pdf');

        // Expenses — standalone spend ledger (renovation, upgrades, supplies).
        Route::get('/expenses',             [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses',            [ExpenseController::class, 'store'])->name('expenses.store');
        Route::patch('/expenses/{id}',      [ExpenseController::class, 'update'])->name('expenses.update')->whereNumber('id');
        Route::delete('/expenses/{id}',     [ExpenseController::class, 'destroy'])->name('expenses.destroy')->whereNumber('id');
        // Refer & Earn — the host's affiliate link, stats + commission
        // statement, and payout bank details. Available on every tier.
        Route::get('/referrals',            [\App\Http\Controllers\Tenant\ReferralController::class, 'index'])->name('referrals.index');
        Route::post('/referrals/bank',      [\App\Http\Controllers\Tenant\ReferralController::class, 'updateBank'])->name('referrals.bank');

        Route::post('/onboarding/complete', [\App\Http\Controllers\Tenant\OnboardingController::class, 'complete'])->name('onboarding.complete');
        Route::post('/onboarding/finish',   [\App\Http\Controllers\Tenant\OnboardingController::class, 'finish'])->name('onboarding.finish');
        Route::post('/onboarding/replay',   [\App\Http\Controllers\Tenant\OnboardingController::class, 'replay'])->name('onboarding.replay');

        Route::get('/settings',             [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/settings/slug-available', [SettingsController::class, 'slugAvailable'])->name('settings.slug-available');
        Route::patch('/settings',           [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/branding',   [SettingsController::class, 'updateBranding'])->name('settings.branding');
        Route::get('/settings/invoice-preview', [SettingsController::class, 'invoicePreview'])->name('settings.invoice-preview');

        Route::get('/subscription',         [SubscriptionController::class, 'index'])->name('subscription');
        Route::post('/subscription/change', [SubscriptionController::class, 'change'])->name('subscription.change');
        Route::post('/subscription/checkout', [\App\Http\Controllers\Tenant\SubscriptionCheckoutController::class, 'checkout'])
            ->name('subscription.checkout');
        // Billplz card auto-renew (Tokenization). Enroll → hosted 3DS → the
        // public callback in routes/api.php stores the token + charges the cycle.
        Route::post('/subscription/enroll-card', [\App\Http\Controllers\Tenant\SubscriptionCardController::class, 'enroll'])
            ->name('subscription.card.enroll');
        Route::post('/subscription/auto-renew', [\App\Http\Controllers\Tenant\SubscriptionCardController::class, 'toggle'])
            ->name('subscription.card.toggle');
        // Stripe recurring subscription — hosted Checkout + Customer Portal. The
        // public return + webhook live in routes/api.php / below.
        Route::post('/subscription/stripe/checkout', [\App\Http\Controllers\Tenant\StripeCheckoutController::class, 'checkout'])
            ->name('subscription.stripe.checkout');
        Route::post('/subscription/stripe/portal', [\App\Http\Controllers\Tenant\StripeCheckoutController::class, 'portal'])
            ->name('subscription.stripe.portal');
        // In-app cancel / resume (cancel_at_period_end) — the tenant can stop the
        // upcoming charge without leaving Tempahlah for the Stripe portal.
        Route::post('/subscription/stripe/cancel', [\App\Http\Controllers\Tenant\StripeCheckoutController::class, 'cancel'])
            ->name('subscription.stripe.cancel');
        Route::post('/subscription/stripe/resume', [\App\Http\Controllers\Tenant\StripeCheckoutController::class, 'resume'])
            ->name('subscription.stripe.resume');
        Route::get('/integrations',                       [IntegrationController::class, 'index'])->name('integrations.index');
        // Channel sync (Airbnb + Booking.com iCal) — registered BEFORE the
        // generic /integrations/{provider} route so "channel-sync" isn't
        // captured as a provider. {room} binds by public_id, tenant-scoped.
        Route::get('/integrations/channel-sync', [\App\Http\Controllers\Tenant\ChannelSyncController::class, 'index'])->name('integrations.channel-sync');
        Route::post('/integrations/channel-sync/{room}', [\App\Http\Controllers\Tenant\ChannelSyncController::class, 'update'])->name('integrations.channel-sync.update');
        Route::post('/integrations/channel-sync/{room}/sync', [\App\Http\Controllers\Tenant\ChannelSyncController::class, 'syncNow'])->name('integrations.channel-sync.sync');
        Route::post('/integrations/channel-sync/{room}/rotate', [\App\Http\Controllers\Tenant\ChannelSyncController::class, 'rotate'])->name('integrations.channel-sync.rotate');
        Route::get('/integrations/{provider}',            [IntegrationController::class, 'show'])->name('integrations.show');
        Route::patch('/integrations/{provider}',          [IntegrationController::class, 'update'])->name('integrations.update');
        Route::delete('/integrations/{provider}',         [IntegrationController::class, 'disconnect'])->name('integrations.disconnect');
        Route::post('/integrations/toyyibpay/test',       [IntegrationController::class, 'testToyyibpay'])->name('integrations.toyyibpay.test');
        Route::post('/integrations/billplz/test',          [IntegrationController::class, 'testBillplz'])->name('integrations.billplz.test');
        Route::post('/integrations/securepay/test',       [IntegrationController::class, 'testSecurePay'])->name('integrations.securepay.test');
        Route::post('/integrations/google_calendar/select-calendar', [\App\Http\Controllers\Tenant\GoogleCalendarController::class, 'selectCalendar'])->name('integrations.google_calendar.select');
        Route::post('/integrations/google_calendar/toggle-write', [\App\Http\Controllers\Tenant\GoogleCalendarController::class, 'toggleWrite'])->name('integrations.google_calendar.toggle-write');
    });

    // In-app Platform Admin ("super tenant") — same login as the tenant app,
    // toggled from the sidebar. Gated to users with is_platform_admin. Not
    // under tenant.require so a tenant-less platform admin can still enter.
    Route::middleware(['auth', 'platform.admin'])
        ->prefix('dashboard/admin')
        ->name('platform.')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\PlatformAdminController::class, 'overview'])->name('overview');
            // Edit a tenant's business details + force free / complimentary-Pro.
            // Bind by {tenant:id} — Tenant's route key is public_id (ULID) via the
            // HasUlidPublicId trait, so a plain {tenant} would 404 on a numeric id.
            Route::get('/tenants/{tenant:id}/edit', [\App\Http\Controllers\PlatformAdminController::class, 'editTenant'])->name('tenants.edit')->whereNumber('tenant');
            Route::patch('/tenants/{tenant:id}', [\App\Http\Controllers\PlatformAdminController::class, 'updateTenant'])->name('tenants.update')->whereNumber('tenant');
            Route::delete('/tenants/{tenant:id}', [\App\Http\Controllers\PlatformAdminController::class, 'destroyTenant'])->name('tenants.destroy')->whereNumber('tenant');
            // Cross-tenant testimonial moderation — hide/show/delete guest reviews.
            Route::get('/testimonials', [\App\Http\Controllers\PlatformAdminController::class, 'testimonials'])->name('testimonials');
            Route::post('/testimonials/{id}/toggle', [\App\Http\Controllers\PlatformAdminController::class, 'toggleTestimonial'])->name('testimonials.toggle')->whereNumber('id');
            Route::delete('/testimonials/{id}', [\App\Http\Controllers\PlatformAdminController::class, 'deleteTestimonial'])->name('testimonials.delete')->whereNumber('id');
            Route::post('/testimonials/{id}/appeal/resolve', [\App\Http\Controllers\PlatformAdminController::class, 'resolveAppeal'])->name('testimonials.appeal.resolve')->whereNumber('id');

            // Cross-tenant guest blacklist — verify / reject tenant-filed reports.
            // A verified entry marks the guest platform-wide (alerts all homestays).
            Route::get('/blacklist', [\App\Http\Controllers\PlatformAdminController::class, 'blacklist'])->name('blacklist');
            Route::post('/blacklist/{id}/review', [\App\Http\Controllers\PlatformAdminController::class, 'reviewBlacklist'])->name('blacklist.review')->whereNumber('id');
            // Platform settings — Stripe keys etc. (encrypted in platform_settings).
            Route::get('/settings', [\App\Http\Controllers\PlatformAdminController::class, 'settings'])->name('settings');
            Route::post('/settings', [\App\Http\Controllers\PlatformAdminController::class, 'updateSettings'])->name('settings.update');
            Route::post('/settings/test-stripe', [\App\Http\Controllers\PlatformAdminController::class, 'testStripe'])->name('settings.test-stripe');
            Route::post('/settings/marketing', [\App\Http\Controllers\PlatformAdminController::class, 'updateMarketing'])->name('settings.marketing');

            // Email marketing — campaigns to hosts (free → Pro pitches etc.),
            // with a per-recipient delivery log + PDPA unsubscribe.
            Route::get('/marketing', [\App\Http\Controllers\PlatformMarketingController::class, 'index'])->name('marketing.index');
            Route::get('/marketing/create', [\App\Http\Controllers\PlatformMarketingController::class, 'create'])->name('marketing.create');
            Route::post('/marketing', [\App\Http\Controllers\PlatformMarketingController::class, 'store'])->name('marketing.store');
            Route::get('/marketing/{campaign}', [\App\Http\Controllers\PlatformMarketingController::class, 'show'])->name('marketing.show')->whereNumber('campaign');
            Route::get('/marketing/{campaign}/edit', [\App\Http\Controllers\PlatformMarketingController::class, 'edit'])->name('marketing.edit')->whereNumber('campaign');
            Route::patch('/marketing/{campaign}', [\App\Http\Controllers\PlatformMarketingController::class, 'update'])->name('marketing.update')->whereNumber('campaign');
            Route::post('/marketing/{campaign}/test', [\App\Http\Controllers\PlatformMarketingController::class, 'sendTest'])->name('marketing.test')->whereNumber('campaign');
            Route::post('/marketing/{campaign}/send', [\App\Http\Controllers\PlatformMarketingController::class, 'send'])->name('marketing.send')->whereNumber('campaign');
            Route::post('/marketing/{campaign}/cancel', [\App\Http\Controllers\PlatformMarketingController::class, 'cancel'])->name('marketing.cancel')->whereNumber('campaign');
            Route::delete('/marketing/{campaign}', [\App\Http\Controllers\PlatformMarketingController::class, 'destroy'])->name('marketing.destroy')->whereNumber('campaign');

            // Affiliates — referral-program management: external affiliates,
            // per-affiliate rate/status, commission review + manual payouts.
            Route::get('/affiliates', [\App\Http\Controllers\PlatformAffiliateController::class, 'index'])->name('affiliates.index');
            Route::post('/affiliates', [\App\Http\Controllers\PlatformAffiliateController::class, 'store'])->name('affiliates.store');
            Route::get('/affiliates/{affiliate}', [\App\Http\Controllers\PlatformAffiliateController::class, 'show'])->name('affiliates.show')->whereNumber('affiliate');
            Route::patch('/affiliates/{affiliate}', [\App\Http\Controllers\PlatformAffiliateController::class, 'update'])->name('affiliates.update')->whereNumber('affiliate');
            Route::delete('/affiliates/{affiliate}', [\App\Http\Controllers\PlatformAffiliateController::class, 'destroy'])->name('affiliates.destroy')->whereNumber('affiliate');
            Route::post('/affiliates/{affiliate}/mark-paid', [\App\Http\Controllers\PlatformAffiliateController::class, 'markPaid'])->name('affiliates.mark-paid')->whereNumber('affiliate');
            Route::post('/affiliates/{affiliate}/commissions/{commission}/void', [\App\Http\Controllers\PlatformAffiliateController::class, 'voidCommission'])->name('affiliates.void')->whereNumber('affiliate')->whereNumber('commission');

            // Onboarding series — the automated new-host drip (add/edit/delete steps).
            Route::get('/marketing/onboarding/create', [\App\Http\Controllers\PlatformMarketingController::class, 'createOnboarding'])->name('marketing.onboarding.create');
            Route::post('/marketing/onboarding', [\App\Http\Controllers\PlatformMarketingController::class, 'storeOnboarding'])->name('marketing.onboarding.store');
            Route::get('/marketing/onboarding/{step}/edit', [\App\Http\Controllers\PlatformMarketingController::class, 'editOnboarding'])->name('marketing.onboarding.edit')->whereNumber('step');
            Route::patch('/marketing/onboarding/{step}', [\App\Http\Controllers\PlatformMarketingController::class, 'updateOnboarding'])->name('marketing.onboarding.update')->whereNumber('step');
            Route::delete('/marketing/onboarding/{step}', [\App\Http\Controllers\PlatformMarketingController::class, 'destroyOnboarding'])->name('marketing.onboarding.destroy')->whereNumber('step');
            Route::post('/marketing/onboarding/{step}/test', [\App\Http\Controllers\PlatformMarketingController::class, 'testOnboarding'])->name('marketing.onboarding.test')->whereNumber('step');
        });

    // Public, signed marketing opt-out — clicked from the campaign email, no
    // login. {tenant:id} because Tenant's route key is public_id (ULID) and
    // the signed link carries the numeric id.
    Route::get('/marketing/unsubscribe/{tenant:id}', [\App\Http\Controllers\PlatformMarketingController::class, 'unsubscribe'])
        ->name('marketing.unsubscribe')
        ->whereNumber('tenant');

    require __DIR__.'/auth-extra.php';
});

// -----------------------------------------------------------------------------
// Tenant public page by PATH — tempahlah.com/{slug}. The CANONICAL public URL:
// every free tenant lives here, and it's equally valid for pro. Registered LAST
// so the specific apex routes above (/register, /login, /marketplace, /hosts,
// /terms, /dashboard, /auth/google, …) always win the match; only an otherwise
// unmatched first path segment falls through to the tenant resolver.
//
// The named tenant-public.* routes live HERE (they used to be domain-based), so
// every route('tenant-public.*', ['tenant_slug' => …]) in views/controllers now
// generates a path URL automatically — the public page's booking form + signed
// magic-links therefore work on the path host with no view/controller changes.
// The slug is constrained to lowercase/digits/hyphens so it can't swallow paths
// with dots (files) or other segments.
// -----------------------------------------------------------------------------
Route::domain(config('app.tenant_domain'))
    ->prefix('{tenant_slug}')
    ->middleware('tenant.path')
    ->name('tenant-public.')
    ->where(['tenant_slug' => '[a-z0-9\-]+'])
    ->group(function () {
        Route::get('/', [TenantHomeController::class, 'index'])->name('home');

        // Public direct-booking flow: guest fills the form on home, we create a
        // pending booking + gateway deposit bill + invoice, then send the pay
        // link via email + WhatsApp.
        Route::post('/book', [PublicBookingController::class, 'store'])
            ->middleware('throttle:booking-create-public')
            ->name('booking.store');
        Route::get('/book/sent/{reference}', [PublicBookingController::class, 'sent'])
            ->name('booking.sent');

        // Guest-facing booking detail reached via a signed magic-link in the
        // confirmation email + WhatsApp. No password — `signed` verifies the
        // HMAC over the URL. {booking} is the public_id (ULID).
        Route::get('/booking/{booking:public_id}', [PublicBookingController::class, 'show'])
            ->middleware('signed')
            ->name('booking.show');

        // Guest submits their bank account for a deposit refund. Signed
        // magic-link minted by the host's "Request bank details" button;
        // {refund} is the refund's public_id (ULID).
        Route::get('/refund/{refund:public_id}/bank', [\App\Http\Controllers\Public\RefundBankController::class, 'show'])
            ->middleware('signed')
            ->name('refund.bank.show');
        Route::post('/refund/{refund:public_id}/bank', [\App\Http\Controllers\Public\RefundBankController::class, 'submit'])
            ->middleware('signed')
            ->name('refund.bank.submit');

        // Guest leaves a testimonial after checkout. Signed magic-link (no
        // password) sent by email + WhatsApp; {booking} is the public_id (ULID).
        // The guest is the ONLY writer — hosts can't create/edit/delete reviews.
        Route::get('/review/{booking}', [\App\Http\Controllers\Public\ReviewController::class, 'show'])
            ->middleware('signed')
            ->name('review.create');
        Route::post('/review/{booking}', [\App\Http\Controllers\Public\ReviewController::class, 'store'])
            ->middleware('signed')
            ->name('review.store');
    });
