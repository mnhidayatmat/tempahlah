<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
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
        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" style="display:flex; height:100vh; overflow:hidden; background: var(--bg);">
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
                    {{ $slot ?? '' }}
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        {{-- Public / auth pages keep simple chrome --}}
        <header style="background: color-mix(in oklab, var(--bg) 88%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); position: sticky; top:0; z-index:30;">
            <div style="max-width: 1200px; margin: 0 auto; display:flex; align-items:center; justify-content:space-between; padding:14px 24px;">
                <a href="{{ url('/') }}" style="display:inline-flex; align-items:center; gap:10px; font-weight:700; color: var(--primary); text-decoration:none; font-size:16px;">
                    <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
                        <defs>
                            <linearGradient id="hauz-grad-pub" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#f29268"/>
                                <stop offset="100%" stop-color="#d97757"/>
                            </linearGradient>
                        </defs>
                        <rect width="32" height="32" rx="9" fill="url(#hauz-grad-pub)"/>
                        <path d="M7 17 L16 9 L25 17 V23 H7 Z" fill="#ffffff" opacity="0.96"/>
                        <rect x="13.5" y="17.5" width="5" height="5.5" rx="0.5" fill="#d97757"/>
                    </svg>
                    {{ config('app.name', 'Hauz') }}
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
</body>
</html>
