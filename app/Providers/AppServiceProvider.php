<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
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

        RateLimiter::for('password-reset', fn (Request $r) => Limit::perHour(3)->by($r->input('email', $r->ip())));
    }
}
