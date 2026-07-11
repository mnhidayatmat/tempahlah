<?php

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\Tenancy\RequireTenant;
use App\Http\Middleware\Tenancy\ResolveTenantFromPath;
use App\Http\Middleware\Tenancy\ResolveTenantFromSubdomain;
use App\Http\Middleware\Tenancy\SetTenantContext;
use App\Http\Middleware\VerifyWhatsappWebhook;
use App\Support\Http\DownloadToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.context' => SetTenantContext::class,
            'tenant.require' => RequireTenant::class,
            'tenant.subdomain' => ResolveTenantFromSubdomain::class,
            'tenant.path' => ResolveTenantFromPath::class,
            'wa.webhook' => VerifyWhatsappWebhook::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            SetTenantContext::class,
        ]);

        $middleware->api(append: [
            SetLocale::class,
            SetTenantContext::class,
        ]);

        // SecurePay POSTs its payment result to the guest's browser at
        // `redirect_url`, so that request carries no CSRF token of ours.
        // Safe to exempt: PaymentReturnController never trusts the posted
        // body — it re-checks the payment state with the gateway directly.
        $middleware->validateCsrfTokens(except: [
            'payments/return/*',
        ]);

        // Echoed straight back to the page that asked for a download, so it can
        // tell when the file started arriving and stop its spinner. Encrypting it
        // would hand the browser a ciphertext it cannot compare. Carries no secret
        // — it is the client's own nonce. See App\Support\Http\DownloadToken.
        $middleware->encryptCookies(except: [
            DownloadToken::COOKIE,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
