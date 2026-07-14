<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- Theme init — runs BEFORE first paint to set html[data-theme].
         New users default to LIGHT mode regardless of their OS theme; a user
         who toggles dark keeps that choice (persisted in localStorage), so only
         the default for someone who hasn't chosen is light. No flash on load.
         Must stay inline + run synchronously, hence the top-of-head IIFE. --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('tempahlah-theme');
                var theme = saved || 'light';
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) { /* localStorage may be blocked in some contexts; default = light */ }
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <meta name="theme-color" content="#2596c6">
    @include('partials.pwa')
    {{-- Meta Pixel fires ONLY on the post-signup landing (flashed by the register
         controllers) — so the private dashboard isn't tracked on every view.
         Inert until FACEBOOK_PIXEL_ID is set; renders nothing otherwise. --}}
    @if (session('fb_track'))
        @include('partials.facebook-pixel', ['fbEvent' => session('fb_track')])
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @auth
        @php $themeTenant = app(\App\Support\Tenancy\TenantContext::class)->current(); @endphp
        @if ($themeTenant)
            <style id="tenant-theme">:root { {!! $themeTenant->themeCssVariables() !!} }</style>
        @endif
    @endauth
    @stack('head')
</head>
<body class="antialiased" style="background: var(--bg); color: var(--ink);">
    @auth
        @php
            $tenant = app(\App\Support\Tenancy\TenantContext::class)->current();
            $plan = $tenant?->subscription?->plan ?? 'free';
        @endphp
        <div x-data="{ sidebarOpen: false }"
             @keydown.escape.window="sidebarOpen = false"
             @tour-set-sidebar.window="sidebarOpen = $event.detail.open"
             style="display:flex; height:100vh; overflow:hidden; background: var(--bg);">
            <div class="shell-backdrop" :class="{ 'is-open': sidebarOpen }" @click="sidebarOpen = false"></div>
            @include('partials.sidebar', ['plan' => $plan, 'tenant' => $tenant])
            <div style="flex:1; display:flex; flex-direction:column; min-width:0;">
                @include('partials.topbar', [
                    'title' => $title ?? null,
                    'subtitle' => $subtitle ?? null,
                    'breadcrumbs' => $breadcrumbs ?? null,
                ])
                <main class="page-enter shell-main-pad" style="flex:1; overflow-y:auto; padding: 20px 28px 40px;">
                    @if (session('status'))
                        <div class="hauz-card" style="padding:12px 16px; margin-bottom:16px; background: var(--ok-tint); border-color: color-mix(in oklab, var(--ok) 30%, transparent); color: var(--ok);">
                            {{ session('status') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="hauz-card" style="padding:12px 16px; margin-bottom:16px; background: var(--err-tint); border-color: color-mix(in oklab, var(--err) 30%, transparent); color: var(--err);">
                            {{ session('error') }}
                        </div>
                    @endif
                    {{ $slot ?? '' }}
                    @yield('content')
                </main>
            </div>

            {{-- Mobile-only bottom nav. Hidden on desktop via CSS in
                 app.css → .shell-botnav. Mirrors the public booking
                 page's nav pattern so tenants get one-tap access to
                 Utama / Kalendar / Tempahan without opening the drawer. --}}
            @php
                $path = '/'.ltrim(request()->path(), '/');
                $botActive = [
                    'home'     => $path === '/dashboard' || $path === '/dashboard/',
                    'cal'      => str_starts_with($path, '/dashboard/calendar'),
                    'bookings' => str_starts_with($path, '/dashboard/bookings'),
                ];
            @endphp
            <nav class="shell-botnav" aria-label="{{ __('Primary navigation') }}">
                <a href="{{ route('tenant.dashboard') }}"
                   class="{{ $botActive['home'] ? 'is-active' : '' }}"
                   aria-current="{{ $botActive['home'] ? 'page' : 'false' }}">
                    <span class="botnav-ico">🏠</span>
                    <span>{{ __('Utama') }}</span>
                </a>
                <a href="{{ route('tenant.calendar') }}"
                   class="{{ $botActive['cal'] ? 'is-active' : '' }}"
                   aria-current="{{ $botActive['cal'] ? 'page' : 'false' }}">
                    <span class="botnav-ico">📅</span>
                    <span>{{ __('Kalendar') }}</span>
                </a>
                <a href="{{ route('tenant.bookings.index') }}"
                   class="{{ $botActive['bookings'] ? 'is-active' : '' }}"
                   aria-current="{{ $botActive['bookings'] ? 'page' : 'false' }}">
                    <span class="botnav-ico">📋</span>
                    <span>{{ __('Tempahan') }}</span>
                </a>
                <button type="button"
                        @click.stop="sidebarOpen = true"
                        aria-label="{{ __('Open menu') }}">
                    <span class="botnav-ico">☰</span>
                    <span>{{ __('Menu') }}</span>
                </button>
            </nav>

            {{-- First-time welcome walkthrough. Renders once per user;
                 hidden forever after dismiss/finish (writes
                 users.tour_completed_at).

                 Scoped to the dashboard HOME only. The tour is a full-viewport
                 modal whose dimmed backdrop swallows the first click on the page
                 beneath it — so when it rendered on every dashboard page, a new
                 user's first click on (say) the Photos tab's "Upload photos"
                 button just dismissed the tour and did nothing, reading as
                 "upload is broken". The walkthrough only ever runs on the home
                 screen anyway, so confine the overlay there. --}}
            @if (auth()->user() && auth()->user()->tour_completed_at === null && request()->routeIs('tenant.dashboard'))
                @include('partials.onboarding-tour')
            @endif
        </div>
    @else
        {{-- Public / auth pages keep simple chrome --}}
        <header style="background: color-mix(in oklab, var(--bg) 88%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); position: sticky; top:0; z-index:30;">
            <div style="max-width: 1200px; margin: 0 auto; display:flex; align-items:center; justify-content:space-between; padding:14px 24px;">
                <a href="{{ url('/') }}" style="display:inline-flex; align-items:center; gap:9px; font-weight:700; color: var(--ink); text-decoration:none; font-size:15px; letter-spacing:-0.005em;">
                    <img src="{{ asset('icons/logo.svg') }}" alt="Tempahlah" width="36" height="31" style="display:block; filter: var(--logo-filter, none);"/>
                    {{ config('app.name', 'Tempahlah') }}
                </a>
                <nav style="display:flex; align-items:center; gap:12px; font-size:13px;">
                    @php $current = app()->getLocale(); @endphp
                    <div style="display:inline-flex; border: 1px solid var(--line-2); border-radius: var(--r-pill); padding:2px; background: var(--bg-sunk);">
                        <a href="{{ route('locale.switch', 'ms') }}"
                           style="padding:4px 10px; border-radius: var(--r-pill); font-size:11px; font-weight:600; text-decoration:none; {{ $current === 'ms' ? 'background:var(--bg-elev); color:var(--primary); box-shadow:var(--sh-1);' : 'color:var(--ink-3);' }}">BM</a>
                        <a href="{{ route('locale.switch', 'en') }}"
                           style="padding:4px 10px; border-radius: var(--r-pill); font-size:11px; font-weight:600; text-decoration:none; {{ $current === 'en' ? 'background:var(--bg-elev); color:var(--primary); box-shadow:var(--sh-1);' : 'color:var(--ink-3);' }}">EN</a>
                    </div>
                    <a href="{{ route('login') }}" style="color:var(--ink-2); text-decoration:none; padding: 6px 10px;">{{ __('Login') }}</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">{{ __('Sign up') }}</a>
                </nav>
            </div>
        </header>
        <main class="public-shell-main" style="max-width: 1200px; margin: 0 auto; padding: 32px 24px;">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    @endauth

    @livewireScripts
    @stack('scripts')
    @include('partials.phone-input')
</body>
</html>
