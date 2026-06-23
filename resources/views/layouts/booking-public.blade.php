<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <meta name="theme-color" content="#2596c6">
    @include('partials.pwa')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
    {{-- Public layout doesn't load Livewire, so Alpine is bundled + served from
         our own origin (resources/js/public-alpine.js) — NOT a third-party CDN.
         A CDN failure used to leave Alpine-driven content blank. --}}
    @vite(['resources/css/app.css', 'resources/css/booking-public.css', 'resources/js/app.js', 'resources/js/public-alpine.js'])
    @stack('head')
</head>
<body class="antialiased" data-public-booking>

    {{-- Desktop / web header (hidden on mobile via CSS) --}}
    <header class="bp-pub-header">
        <a href="{{ route('marketplace.search') }}" class="bp-pub-header-logo">
            <img src="{{ asset('icons/logo.svg') }}" alt="Tempahlah" class="bp-pub-header-mark" width="46" height="40" style="display:block; filter: var(--logo-filter, none);"/>
            <div>
                <div class="bp-pub-header-name">{{ config('app.name', 'Tempahlah') }}</div>
                <div class="bp-pub-header-host">tempahlah.com</div>
            </div>
        </a>
        <nav class="bp-pub-nav">
            <a href="{{ route('marketplace.search') }}">{{ __('Properties') }}</a>
            <a href="#">{{ __('FAQ') }}</a>
            <span class="sep"></span>
            @php $loc = app()->getLocale(); @endphp
            <a href="{{ route('locale.switch', $loc === 'ms' ? 'en' : 'ms') }}">{{ strtoupper($loc) }} ▾</a>
            @guest
                <a href="{{ route('login') }}">{{ __('Login') }}</a>
            @endguest
        </nav>
    </header>

    {{-- Page content --}}
    {{ $slot ?? '' }}
    @yield('content')

    {{-- Desktop footer (hidden on mobile via CSS) --}}
    <footer class="bp-footer">
        <div class="bp-footer-grid">
            <div>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <img src="{{ asset('icons/logo.svg') }}" alt="Tempahlah" width="32" height="28" style="display:block; filter: var(--logo-filter, none);"/>
                    <div style="font-size:14px; font-weight:600;">{{ config('app.name', 'Tempahlah') }}</div>
                </div>
                <p style="font-size:13px; color:var(--ink-3); line-height:1.6; max-width:360px; margin:0;">
                    {{ __('Family-run homestays across Malaysia. SSM-registered. Tourism-tax compliant.') }}
                </p>
                <div style="display:flex; gap:10px; margin-top:14px;">
                    <span class="pill" style="background:var(--bg-elev); border:.5px solid var(--line);">{{ __('SSM verified') }}</span>
                    <span class="pill" style="background:var(--bg-elev); border:.5px solid var(--line);">{{ __('Halal-friendly') }}</span>
                </div>
            </div>
            <div>
                <div class="kicker" style="margin-bottom:12px;">{{ __('Stays') }}</div>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">
                    <li><a href="{{ route('marketplace.search', ['cover' => 'beach']) }}" style="font-size:13px; color:var(--ink-2); text-decoration:none;">{{ __('Beachfront') }}</a></li>
                    <li><a href="{{ route('marketplace.search', ['cover' => 'highland']) }}" style="font-size:13px; color:var(--ink-2); text-decoration:none;">{{ __('Highland') }}</a></li>
                    <li><a href="{{ route('marketplace.search', ['cover' => 'kampung']) }}" style="font-size:13px; color:var(--ink-2); text-decoration:none;">{{ __('Kampung') }}</a></li>
                    <li><a href="{{ route('marketplace.search', ['cover' => 'heritage']) }}" style="font-size:13px; color:var(--ink-2); text-decoration:none;">{{ __('Heritage') }}</a></li>
                </ul>
            </div>
            <div>
                <div class="kicker" style="margin-bottom:12px;">{{ __('Help') }}</div>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">
                    <li><a href="#" style="font-size:13px; color:var(--ink-2); text-decoration:none;">{{ __('Contact host') }}</a></li>
                    <li><a href="#" style="font-size:13px; color:var(--ink-2); text-decoration:none;">{{ __('FAQ') }}</a></li>
                    <li><a href="#" style="font-size:13px; color:var(--ink-2); text-decoration:none;">{{ __('Cancellation policy') }}</a></li>
                </ul>
            </div>
            <div>
                <div class="kicker" style="margin-bottom:12px;">{{ __('Powered by') }}</div>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">
                    <li><a href="{{ route('register') }}" style="font-size:13px; color:var(--ink-2); text-decoration:none;">{{ __('Become a host') }}</a></li>
                    <li><a href="{{ route('login') }}" style="font-size:13px; color:var(--ink-2); text-decoration:none;">{{ __('Login') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="bp-footer-bar">
            <span>© {{ date('Y') }} {{ config('app.name') }} · {{ __('All rights reserved') }}</span>
            <span>{{ __('Booked direct, no middleman') }}</span>
        </div>
    </footer>

    {{-- Mobile bottom nav (only shown ≤640px via CSS) --}}
    <nav class="bp-bottom-nav" aria-label="Primary">
        <div class="bp-bottom-nav-inner">
            <a href="{{ route('marketplace.search') }}" class="bp-bottom-nav-item {{ request()->routeIs('marketplace.search') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M9 20v-6h6v6"/></svg>
                <span>{{ __('Stay') }}</span>
            </a>
            <a href="#" class="bp-bottom-nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1Z"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M3 13h18"/></svg>
                <span>{{ __('Trips') }}</span>
            </a>
            <a href="#" class="bp-bottom-nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span>{{ __('Saved') }}</span>
            </a>
            @auth
                <a href="{{ route('tenant.dashboard') }}" class="bp-bottom-nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <span>{{ __('Account') }}</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="bp-bottom-nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <span>{{ __('Login') }}</span>
                </a>
            @endauth
        </div>
    </nav>

    @stack('scripts')
</body>
</html>
