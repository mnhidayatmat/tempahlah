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
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="antialiased" style="background: var(--bg); color: var(--ink);">
    @auth
        @php
            $tenant = app(\App\Support\Tenancy\TenantContext::class)->current();
            $plan = $tenant?->subscription?->plan ?? 'free';
        @endphp
        <div style="display:flex; height:100vh; overflow:hidden;">
            @include('partials.sidebar', ['plan' => $plan, 'tenant' => $tenant])
            <div style="flex:1; display:flex; flex-direction:column; min-width:0;">
                @include('partials.topbar', ['title' => $title ?? null])
                <main style="flex:1; overflow-y:auto; padding: 20px 28px 40px;">
                    @if (session('status'))
                        <div class="hauz-card" style="padding:12px 16px; margin-bottom:16px; background: var(--ok-tint); border-color: var(--ok); color: var(--ok);">
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
        <header style="background: var(--bg-elev); border-bottom: .5px solid var(--line);">
            <div class="container mx-auto" style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px;">
                <a href="{{ url('/') }}" style="font-weight:600; color: var(--primary); text-decoration:none;">
                    {{ config('app.name') }}
                </a>
                <nav style="display:flex; align-items:center; gap:12px; font-size:13px;">
                    @php $current = app()->getLocale(); @endphp
                    <div style="display:inline-flex; border:.5px solid var(--line-2); border-radius:6px; padding:2px; background: var(--bg-sunk);">
                        <a href="{{ route('locale.switch', 'ms') }}"
                           style="padding:4px 10px; border-radius:4px; font-size:11px; font-weight:500; text-decoration:none; {{ $current === 'ms' ? 'background:var(--bg-elev); color:var(--primary); box-shadow:var(--sh-1);' : 'color:var(--ink-3);' }}">BM</a>
                        <a href="{{ route('locale.switch', 'en') }}"
                           style="padding:4px 10px; border-radius:4px; font-size:11px; font-weight:500; text-decoration:none; {{ $current === 'en' ? 'background:var(--bg-elev); color:var(--primary); box-shadow:var(--sh-1);' : 'color:var(--ink-3);' }}">EN</a>
                    </div>
                    <a href="{{ route('login') }}" style="color:var(--ink-2); text-decoration:none;">{{ __('Login') }}</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">{{ __('Sign up') }}</a>
                </nav>
            </div>
        </header>
        <main class="container mx-auto" style="padding: 32px 16px;">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    @endauth

    @livewireScripts
    @stack('scripts')
</body>
</html>
