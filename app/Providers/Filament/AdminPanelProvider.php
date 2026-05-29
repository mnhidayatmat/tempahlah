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
            ->brandName('Tempahlah Admin')
            ->brandLogo(fn (): HtmlString => new HtmlString(<<<'HTML'
                <span style="display:inline-flex; align-items:center; gap:10px; font-family:'Geist',ui-sans-serif,system-ui,sans-serif;">
                    <img src="/icons/logo.svg" alt="Tempahlah" width="37" height="32" style="display:block;"/>
                    <span style="font-weight:700; letter-spacing:-0.01em; color:#0f1928;">Tempahlah <span style="color:#1a6a96; font-weight:600;">Admin</span></span>
                </span>
            HTML))
            ->brandLogoHeight('2rem')
            ->favicon(asset('icons/logo.svg'))
            ->authGuard('super_admin')
            ->colors([
                'primary' => Color::hex('#2596c6'),
                'gray'    => Color::hex('#4b5563'),
                'danger'  => Color::hex('#b94a3a'),
                'success' => Color::hex('#3f8b6a'),
                'warning' => Color::hex('#e8b94a'),
                'info'    => Color::hex('#2cb8c4'),
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
