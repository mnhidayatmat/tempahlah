<?php

namespace App\Providers;

use App\Listeners\HaltMailToSuppressed;
use App\Services\WhatsApp\RecipientGuard;
use App\Services\WhatsApp\Sidecar\SidecarClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->singleton(SidecarClient::class, fn () => SidecarClient::fromConfig());
        $this->app->singleton(RecipientGuard::class, fn () => new RecipientGuard());
    }

    public function boot(): void
    {
        $this->configureRateLimiters();

        // Send already-authenticated visitors who hit a `guest` route (e.g. /login,
        // /register) to their dashboard instead of the public homepage. Without this,
        // a logged-in user clicking "Log masuk" gets bounced to `/` — which still shows
        // the "Log masuk" button — so login looks broken even though they're signed in.
        // SetTenantContext falls back to the user's first active tenant when the session
        // has no current_tenant_public_id, and RequireTenant routes tenant-less users to
        // onboarding, so this target is safe for every authenticated user.
        RedirectIfAuthenticated::redirectUsing(fn () => route('tenant.dashboard'));

        // Never send email to an address SES flagged as a hard bounce or a spam
        // complaint. One listener on the framework's send event covers every
        // mailable — see App\Listeners\HaltMailToSuppressed.
        Event::listen(MessageSending::class, [HaltMailToSuppressed::class, 'handle']);
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('auth-login', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));

        RateLimiter::for('auth-otp-send', fn (Request $r) => [
            Limit::perMinute(5)->by($r->input('phone', $r->ip())),
            Limit::perMinute(5)->by($r->ip()),
        ]);

        RateLimiter::for('marketplace-search', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));

        RateLimiter::for('booking-create-public', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));

        RateLimiter::for('api-read', fn (Request $r) => Limit::perMinute(60)->by(optional($r->user())->id ?: $r->ip()));

        RateLimiter::for('api-write', fn (Request $r) => Limit::perMinute(30)->by(optional($r->user())->id ?: $r->ip()));

        RateLimiter::for('webhook-toyyibpay', fn (Request $r) => Limit::perMinute(100)->by($r->ip()));

        RateLimiter::for('webhook-billplz', fn (Request $r) => Limit::perMinute(100)->by($r->ip()));

        RateLimiter::for('webhook-securepay', fn (Request $r) => Limit::perMinute(100)->by($r->ip()));

        // Platform subscription billing callback (a tenant paying us RM 49/mo).
        RateLimiter::for('webhook-subscription', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));

        RateLimiter::for('webhook-ses', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));

        RateLimiter::for('password-reset', fn (Request $r) => Limit::perHour(3)->by($r->input('email', $r->ip())));

        // Public per-room iCal busy-feed. OTA crawlers poll it a few times an
        // hour at most; 30/min/IP is generous while blunting scraping/abuse.
        RateLimiter::for('ical-feed', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
    }
}
