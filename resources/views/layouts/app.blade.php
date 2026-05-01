<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="bg-white border-b border-slate-200">
        <div class="container mx-auto flex items-center justify-between px-4 py-3">
            <a href="{{ url('/') }}" class="font-semibold text-sky-600">{{ config('app.name') }}</a>
            <nav class="flex items-center gap-3 text-sm">
                <div class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-0.5 text-xs font-medium">
                    @php $current = app()->getLocale(); @endphp
                    <a href="{{ route('locale.switch', 'ms') }}"
                       class="px-2.5 py-1 rounded {{ $current === 'ms' ? 'bg-white shadow-sm text-sky-700' : 'text-slate-500 hover:text-slate-900' }}"
                       aria-label="Bahasa Melayu" title="Bahasa Melayu">BM</a>
                    <a href="{{ route('locale.switch', 'en') }}"
                       class="px-2.5 py-1 rounded {{ $current === 'en' ? 'bg-white shadow-sm text-sky-700' : 'text-slate-500 hover:text-slate-900' }}"
                       aria-label="English" title="English">EN</a>
                </div>
                @auth
                    <span class="text-slate-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-slate-600 hover:text-slate-900">{{ __('Logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900">{{ __('Login') }}</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-sky-600 text-white px-3 py-1.5 hover:bg-sky-700">{{ __('Sign up') }}</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-green-800">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
    @stack('scripts')
</body>
</html>
