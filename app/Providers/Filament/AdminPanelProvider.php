<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Http\Middleware\SetLocale;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('super-admin')
            ->login()
            ->brandName('HomestayMY Admin')
            ->brandLogo(fn (): HtmlString => new HtmlString(<<<'SVG'
                <span style="display:inline-flex; align-items:center; gap:10px; font-family:'Geist',ui-sans-serif,system-ui,sans-serif;">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                        <rect x="0" y="0" width="32" height="32" rx="8" fill="#d97757"/>
                        <path d="M7 17 L16 9 L25 17 V23 H7 Z" fill="#faf6ef"/>
                        <rect x="13" y="17" width="6" height="6" fill="#a8401e"/>
                    </svg>
                    <span style="font-weight:700; letter-spacing:-0.01em; color:#2c2622;">HomestayMY <span style="color:#a8401e; font-weight:600;">Admin</span></span>
                </span>
            SVG))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.ico'))
            ->authGuard('super_admin')
            ->colors([
                'primary' => Color::hex('#d97757'),
                'gray'    => Color::hex('#5b4f47'),
                'danger'  => Color::hex('#b94a3a'),
                'success' => Color::hex('#6a8b3f'),
                'warning' => Color::hex('#d4a437'),
                'info'    => Color::hex('#4a82a8'),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.admin.brand-styles')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.admin.auth.login-intro')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.admin.locale-toggle')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
