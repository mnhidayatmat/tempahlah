{{--
    Shared chrome for Tempahlah's public legal pages (/terms, /privacy).
    Self-contained styles so it renders without the built app CSS and needs no
    asset rebuild. Theme-aware + BM/EN via the app locale.
--}}
@php $isBM = app()->getLocale() === 'ms'; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title') · {{ config('app.name', 'Tempahlah') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <meta name="theme-color" content="#0e2a3a">
    @include('partials.pwa')

    <style>
        :root {
            --lg-bg:      #fafaf7;
            --lg-surface: #ffffff;
            --lg-ink:     #1a1614;
            --lg-ink-2:   #4a4540;
            --lg-ink-3:   #8a857f;
            --lg-line:    rgba(26,22,20,0.10);
            --lg-teal:    #2596c6;
            --lg-deep:    #0e2a3a;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --lg-bg:      #14100e;
                --lg-surface: #1c1815;
                --lg-ink:     #f2ede8;
                --lg-ink-2:   #c4bdb5;
                --lg-ink-3:   #8f877e;
                --lg-line:    rgba(255,255,255,0.10);
                --lg-teal:    #4bb4e0;
            }
        }
        :root[data-theme="light"] {
            --lg-bg: #fafaf7; --lg-surface: #ffffff; --lg-ink: #1a1614;
            --lg-ink-2: #4a4540; --lg-ink-3: #8a857f; --lg-line: rgba(26,22,20,0.10); --lg-teal: #2596c6;
        }
        :root[data-theme="dark"] {
            --lg-bg: #14100e; --lg-surface: #1c1815; --lg-ink: #f2ede8;
            --lg-ink-2: #c4bdb5; --lg-ink-3: #8f877e; --lg-line: rgba(255,255,255,0.10); --lg-teal: #4bb4e0;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--lg-bg); color: var(--lg-ink);
            font-family: 'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 15.5px; line-height: 1.7; -webkit-font-smoothing: antialiased;
        }
        .lg-top {
            position: sticky; top: 0; z-index: 10;
            background: color-mix(in srgb, var(--lg-bg) 88%, transparent);
            backdrop-filter: blur(12px);
            border-bottom: .5px solid var(--lg-line);
        }
        .lg-top-inner {
            max-width: 760px; margin: 0 auto; padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
        }
        .lg-brand { display: flex; align-items: center; gap: 9px; text-decoration: none; color: var(--lg-ink); }
        .lg-brand img { width: 26px; height: 26px; }
        .lg-brand span { font-family: 'Fraunces', serif; font-weight: 600; font-size: 18px; }
        .lg-back { font-size: 13.5px; color: var(--lg-ink-3); text-decoration: none; white-space: nowrap; }
        .lg-back:hover { color: var(--lg-teal); }

        main { max-width: 760px; margin: 0 auto; padding: 40px 24px 96px; }
        .lg-eyebrow {
            text-transform: uppercase; letter-spacing: .14em; font-size: 11px; font-weight: 600;
            color: var(--lg-teal); margin: 0 0 10px;
        }
        h1 {
            font-family: 'Fraunces', serif; font-weight: 600; font-size: clamp(30px, 6vw, 42px);
            line-height: 1.12; letter-spacing: -.01em; margin: 0 0 8px;
        }
        .lg-meta { color: var(--lg-ink-3); font-size: 13.5px; margin: 0 0 14px; }
        .lg-intro { color: var(--lg-ink-2); font-size: 16.5px; margin: 0 0 8px; }

        h2 {
            font-family: 'Fraunces', serif; font-weight: 600; font-size: 22px; line-height: 1.25;
            margin: 40px 0 4px; padding-top: 20px; border-top: .5px solid var(--lg-line);
            scroll-margin-top: 80px;
        }
        h2 .lg-num { color: var(--lg-teal); font-variant-numeric: tabular-nums; margin-right: 10px; }
        h3 { font-size: 16px; font-weight: 650; margin: 24px 0 4px; }
        p { margin: 12px 0; color: var(--lg-ink-2); }
        strong { color: var(--lg-ink); font-weight: 600; }
        a { color: var(--lg-teal); }
        ul { margin: 12px 0; padding-left: 22px; color: var(--lg-ink-2); }
        li { margin: 7px 0; }

        .lg-callout {
            background: var(--lg-surface); border: .5px solid var(--lg-line);
            border-left: 3px solid var(--lg-teal); border-radius: 10px;
            padding: 16px 18px; margin: 22px 0; font-size: 14.5px; color: var(--lg-ink-2);
        }
        .lg-foot {
            max-width: 760px; margin: 0 auto; padding: 28px 24px 48px;
            border-top: .5px solid var(--lg-line); color: var(--lg-ink-3); font-size: 13px;
            display: flex; flex-wrap: wrap; gap: 6px 18px; align-items: center; justify-content: space-between;
        }
        .lg-foot a { color: var(--lg-ink-3); text-decoration: none; }
        .lg-foot a:hover { color: var(--lg-teal); }
        .lg-foot-links { display: flex; gap: 18px; }

        @media (max-width: 640px) {
            body { font-size: 15px; }
            main { padding: 28px 18px 72px; }
            .lg-top-inner, .lg-foot { padding-left: 18px; padding-right: 18px; }
        }
    </style>
</head>
<body>
    <header class="lg-top">
        <div class="lg-top-inner">
            <a class="lg-brand" href="{{ url('/') }}">
                <img src="{{ asset('icons/logo.svg') }}" alt="Tempahlah">
                <span>Tempahlah</span>
            </a>
            <a class="lg-back" href="{{ url()->previous() && str_contains(url()->previous(), 'register') ? url('/register') : url('/') }}">
                &larr; {{ $isBM ? 'Kembali' : 'Back' }}
            </a>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="lg-foot">
        <span>&copy; {{ now()->year }} Tempahlah</span>
        <span class="lg-foot-links">
            <a href="{{ url('/terms') }}">{{ $isBM ? 'Terma Perkhidmatan' : 'Terms of Service' }}</a>
            <a href="{{ url('/privacy') }}">{{ $isBM ? 'Polisi PDPA' : 'PDPA Privacy Policy' }}</a>
            <a href="mailto:hello@tempahlah.com">hello@tempahlah.com</a>
        </span>
    </footer>
</body>
</html>
