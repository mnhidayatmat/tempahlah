{{--
    Tempahlah login — "The Innkeeper's Ledger"
    Self-contained, deliberately not extending layouts.app so the editorial
    split-screen treatment can run full-bleed. Aesthetic: hotel-concierge
    homecoming. Deep teal atmospheric panel left, warm cream form right.
    Mobile stacks the panel as a compact hero on top.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Sign in') }} · {{ config('app.name', 'Tempahlah') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,500&family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <meta name="theme-color" content="#0e2a3a">

    @php $loc = app()->getLocale(); $isBM = $loc === 'ms'; @endphp

    <style>
        :root {
            --ink-deep:     #0e2a3a;
            --ink-mid:      #1a4a66;
            --teal:         #2596c6;
            --teal-bright:  #2cb8c4;
            --brass:        #e8b94a;
            --brass-soft:   #f4d68a;
            --cream:        #fafaf7;
            --cream-warm:   #f4ecdf;
            --paper:        #fdfcf8;
            --ink:          #1a1614;
            --ink-2:        #4a4540;
            --ink-3:        #8a857f;
            --line:         rgba(26,22,20,0.08);
            --line-2:       rgba(26,22,20,0.14);
            --sh-card:      0 2px 4px rgba(14,42,58,0.04), 0 24px 48px -16px rgba(14,42,58,0.12);
        }

        * { box-sizing: border-box; -webkit-font-smoothing: antialiased; }
        html, body { margin: 0; padding: 0; height: 100%; }
        body {
            font-family: 'Geist', system-ui, sans-serif;
            color: var(--ink);
            background: var(--cream);
            overflow-x: hidden;
        }

        /* ── Shell — split screen ─────────────────────────────────── */
        .ledger {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            min-height: 100dvh;
        }
        @media (max-width: 880px) {
            .ledger { grid-template-columns: 1fr; }
        }

        /* ── LEFT: Atmospheric panel ──────────────────────────────── */
        .stage {
            position: relative;
            overflow: hidden;
            color: #fff;
            padding: 56px 56px 48px;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(900px 700px at 70% 110%, rgba(44,184,196,0.22) 0%, transparent 55%),
                radial-gradient(700px 500px at 15% 20%, rgba(37,150,198,0.20) 0%, transparent 60%),
                linear-gradient(165deg, #0a1f2c 0%, #143a52 45%, #1c5b7d 100%);
            isolation: isolate;
        }
        /* Mobile: the atmospheric stage adds nothing useful for someone
           who's already at the door trying to sign in. Hide it entirely
           and give the form the full viewport. */
        @media (max-width: 880px) {
            .stage { display: none; }
        }

        /* Mobile-only top strip: minimal brand + locale toggle that
           replaces the missing stage chrome. Desktop: hidden. */
        .mobile-top {
            display: none;
        }
        @media (max-width: 880px) {
            .mobile-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 18px 22px;
                background: var(--paper);
                border-bottom: 1px solid var(--line);
                position: sticky;
                top: 0;
                z-index: 10;
            }
            .mobile-top .brand-mark {
                width: 32px; height: 32px;
                background: linear-gradient(155deg, #1c5b7d, #0e2a3a);
                padding: 4px;
            }
            .mobile-top .brand-name {
                font-family: 'Fraunces', serif;
                font-style: italic;
                font-weight: 500;
                font-size: 18px;
                color: var(--ink);
                letter-spacing: -0.01em;
            }
            .mobile-top .brand { color: var(--ink); }
            .mobile-top .locale {
                background: var(--cream-warm);
                border-color: var(--line-2);
                backdrop-filter: none;
            }
            .mobile-top .locale a { color: var(--ink-3); }
            .mobile-top .locale a.active {
                background: var(--ink-deep);
                color: var(--brass-soft);
                box-shadow: 0 2px 6px -2px rgba(14,42,58,0.35);
            }
        }

        /* Grain overlay — generated SVG noise, no external asset */
        .stage::before {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' seed='3' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 0.13 0'/></filter><rect width='100%25' height='100%25' filter='url(%23n)'/></svg>");
            opacity: 0.45;
            mix-blend-mode: overlay;
            pointer-events: none;
            z-index: 1;
        }
        /* Diagonal hairline threads (decorative) */
        .stage::after {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(115deg, transparent 0%, transparent 49.93%, rgba(232,185,74,0.18) 50%, transparent 50.07%, transparent 100%),
                linear-gradient(115deg, transparent 0%, transparent 71.94%, rgba(232,185,74,0.10) 72%, transparent 72.06%, transparent 100%);
            background-size: 100% 100%;
            pointer-events: none;
            z-index: 1;
        }
        .stage > * { position: relative; z-index: 2; }

        /* Top row: brand + locale */
        .stage-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .brand {
            display: inline-flex; align-items: center; gap: 11px;
            color: #fff; text-decoration: none;
        }
        .brand-mark {
            width: 38px; height: 38px;
            background: #fff;
            border-radius: 9px;
            padding: 5px;
            display: grid; place-items: center;
            box-shadow: 0 6px 18px -6px rgba(0,0,0,0.35);
        }
        .brand-mark img { width: 100%; height: 100%; display: block; }
        .brand-word { display: flex; flex-direction: column; line-height: 1; }
        .brand-name {
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-weight: 500;
            font-size: 21px;
            letter-spacing: -0.01em;
        }
        .brand-tag {
            font-family: 'Geist Mono', monospace;
            font-size: 9.5px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.55);
            margin-top: 5px;
        }

        .locale {
            display: inline-flex;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.16);
            border-radius: 999px;
            padding: 3px;
            backdrop-filter: blur(8px);
        }
        .locale a {
            padding: 5px 11px;
            font-family: 'Geist Mono', monospace;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.08em;
            border-radius: 999px;
            color: rgba(255,255,255,0.65);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .locale a.active {
            background: var(--brass);
            color: var(--ink-deep);
            box-shadow: 0 2px 8px -2px rgba(232,185,74,0.5);
        }
        .locale a:not(.active):hover { color: rgba(255,255,255,0.95); }

        /* Plaque — vintage hotel room-key tag */
        .plaque-block {
            margin-top: 64px;
            display: flex;
            align-items: center;
            gap: 18px;
            opacity: 0;
            transform: translateY(-12px);
            animation: drop 0.8s cubic-bezier(.22,1,.36,1) 0.25s forwards;
        }
        @media (max-width: 880px) { .plaque-block { margin-top: 32px; } }
        .plaque {
            width: 78px; height: 86px;
            background: linear-gradient(155deg, var(--brass-soft) 0%, var(--brass) 60%, #c89a30 100%);
            border-radius: 14px 14px 9px 9px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,0.55),
                inset 0 -10px 18px rgba(140,90,10,0.2),
                0 12px 24px -8px rgba(0,0,0,0.45);
            position: relative;
            font-family: 'Fraunces', serif;
            color: var(--ink-deep);
        }
        .plaque::before {
            content: '';
            position: absolute; top: 10px; left: 50%; transform: translateX(-50%);
            width: 11px; height: 11px;
            background: var(--ink-deep);
            border-radius: 50%;
            box-shadow: inset 0 1px 2px rgba(255,255,255,0.18);
        }
        .plaque-num {
            font-style: italic;
            font-weight: 700;
            font-size: 32px;
            line-height: 1;
            margin-top: 18px;
            letter-spacing: -0.02em;
        }
        .plaque-label {
            font-family: 'Geist Mono', monospace;
            font-size: 7.5px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            margin-top: 4px;
            opacity: 0.75;
        }
        .plaque-keychain {
            font-family: 'Geist Mono', monospace;
            font-size: 10px;
            letter-spacing: 0.16em;
            color: rgba(255,255,255,0.45);
            text-transform: uppercase;
        }
        .plaque-keychain strong {
            display: block;
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-weight: 500;
            font-size: 15px;
            color: rgba(255,255,255,0.85);
            margin-top: 3px;
            letter-spacing: -0.005em;
            text-transform: none;
        }

        /* Hero copy block */
        .hero {
            margin-top: auto;
            padding-top: 56px;
        }
        @media (max-width: 880px) { .hero { padding-top: 28px; } }
        .hero-kicker {
            font-family: 'Geist Mono', monospace;
            font-size: 10.5px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--brass-soft);
            margin-bottom: 14px;
            opacity: 0;
            animation: rise 0.7s cubic-bezier(.22,1,.36,1) 0.45s forwards;
        }
        .hero-title {
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-weight: 400;
            font-size: clamp(38px, 5.4vw, 64px);
            line-height: 1.02;
            letter-spacing: -0.025em;
            color: #fff;
            margin: 0;
        }
        .hero-title .word {
            display: inline-block;
            opacity: 0;
            transform: translateY(18px);
            animation: rise 0.8s cubic-bezier(.22,1,.36,1) forwards;
        }
        .hero-title .word.brass { color: var(--brass-soft); font-weight: 500; }
        .hero-sub {
            margin-top: 22px;
            font-family: 'Geist', sans-serif;
            font-size: 15px;
            line-height: 1.65;
            color: rgba(255,255,255,0.72);
            max-width: 440px;
            opacity: 0;
            animation: rise 0.8s cubic-bezier(.22,1,.36,1) 0.95s forwards;
        }

        /* Footer aside — concierge note */
        .stage-bottom {
            margin-top: 56px;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            opacity: 0;
            animation: rise 0.8s cubic-bezier(.22,1,.36,1) 1.15s forwards;
        }
        @media (max-width: 880px) { .stage-bottom { display: none; } }
        .stage-bottom-l {
            font-family: 'Geist Mono', monospace;
            font-size: 10.5px;
            letter-spacing: 0.14em;
            color: rgba(255,255,255,0.45);
            text-transform: uppercase;
        }
        .pulse-dot {
            display: inline-block;
            width: 7px; height: 7px;
            background: var(--brass);
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
            box-shadow: 0 0 0 0 rgba(232,185,74,0.6);
            animation: pulse 2.2s ease-in-out infinite;
        }
        .stage-bottom-r {
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-size: 13px;
            color: rgba(255,255,255,0.62);
        }

        /* ── RIGHT: Form panel ────────────────────────────────────── */
        .desk {
            position: relative;
            background: var(--paper);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px 56px;
            opacity: 0;
            animation: rise 0.8s cubic-bezier(.22,1,.36,1) 0.6s forwards;
        }
        @media (max-width: 880px) {
            .desk {
                padding: 32px 22px 40px;
                min-height: calc(100dvh - 70px); /* offset for the sticky mobile-top strip */
                justify-content: flex-start;
                animation: rise 0.5s cubic-bezier(.22,1,.36,1) 0.05s forwards;
            }
            .heading { margin-bottom: 28px; }
            .heading-title { font-size: 32px; }
        }
        /* Paper texture: subtle horizontal lines like a ledger */
        .desk::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 100%;
            background-image:
                repeating-linear-gradient(0deg, transparent 0px, transparent 32px, rgba(26,22,20,0.025) 32px, rgba(26,22,20,0.025) 33px);
            pointer-events: none;
            opacity: 0.7;
        }
        .desk > * { position: relative; z-index: 2; }

        .form-wrap { max-width: 440px; width: 100%; margin: 0 auto; }

        .heading {
            margin-bottom: 36px;
        }
        .heading-kicker {
            font-family: 'Geist Mono', monospace;
            font-size: 10.5px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--teal);
            margin-bottom: 10px;
        }
        .heading-title {
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-weight: 500;
            font-size: 38px;
            line-height: 1.1;
            letter-spacing: -0.02em;
            color: var(--ink);
            margin: 0;
        }
        .heading-sub {
            font-size: 14px;
            color: var(--ink-3);
            margin: 12px 0 0;
            line-height: 1.55;
        }

        .flash-ok, .flash-err {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 22px;
        }
        .flash-ok  { background: #ecfaf6; color: #1c6b53; border-left: 3px solid #2cb88a; }
        .flash-err { background: #fdf1ef; color: #a44032; border-left: 3px solid #c8554a; }

        .field {
            margin-bottom: 20px;
        }
        .field-row {
            display: block;
            margin-bottom: 6px;
        }
        .field-label {
            display: block;
            font-family: 'Geist', sans-serif;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink-2);
        }
        .field-input {
            display: block;
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--line-2);
            border-radius: 8px;
            background: #fff;
            font-family: 'Geist', sans-serif;
            font-size: 15px;
            font-weight: 500;
            line-height: 1.4;
            color: var(--ink);
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .field-input::placeholder {
            color: var(--ink-3);
            font-weight: 400;
        }
        .field-input:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--teal) 14%, transparent);
        }
        .field-err {
            display: block;
            font-size: 12px;
            color: #c8554a;
            margin-top: 7px;
            font-family: 'Geist', sans-serif;
        }
        .field-aux {
            font-family: 'Geist', sans-serif;
            font-size: 12.5px;
            color: var(--teal);
            text-decoration: none;
            transition: color 0.15s ease;
        }
        .field-aux:hover { color: var(--ink-deep); }

        .field-row-between { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin-bottom: 6px; }

        /* Custom remember-me */
        .remember {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 6px 0 32px;
            cursor: pointer;
            user-select: none;
        }
        .remember input { position: absolute; opacity: 0; pointer-events: none; }
        .remember-box {
            width: 18px; height: 18px;
            border: 1.5px solid var(--line-2);
            border-radius: 5px;
            background: var(--paper);
            display: grid; place-items: center;
            transition: all 0.18s ease;
            flex-shrink: 0;
        }
        .remember input:checked ~ .remember-box {
            background: var(--teal);
            border-color: var(--teal);
        }
        .remember-check {
            width: 11px; height: 11px;
            stroke: #fff;
            stroke-width: 3;
            fill: none;
            opacity: 0;
            transition: opacity 0.18s ease;
        }
        .remember input:checked ~ .remember-box .remember-check { opacity: 1; }
        .remember-label {
            font-family: 'Geist', sans-serif;
            font-size: 13px;
            color: var(--ink-2);
        }
        .remember-label em {
            font-family: 'Fraunces', serif;
            font-style: italic;
            color: var(--ink);
        }

        /* CTA button with bilingual hover-shift */
        .cta {
            position: relative;
            width: 100%;
            padding: 16px 22px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(160deg, #2596c6 0%, #1a6a96 100%);
            color: #fff;
            font-family: 'Geist', sans-serif;
            font-size: 14.5px;
            font-weight: 600;
            letter-spacing: 0.02em;
            cursor: pointer;
            overflow: hidden;
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,0.18),
                0 8px 18px -6px rgba(37,150,198,0.45);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .cta:hover {
            transform: translateY(-1.5px);
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,0.22),
                0 14px 26px -6px rgba(37,150,198,0.55);
        }
        .cta:active { transform: translateY(0); }
        .cta-label {
            display: inline-flex; align-items: center; gap: 10px;
            position: relative; z-index: 2;
            transition: transform 0.3s cubic-bezier(.6,0,.4,1);
        }
        .cta-label .arrow {
            display: inline-block;
            transition: transform 0.3s cubic-bezier(.6,0,.4,1);
        }
        .cta:hover .cta-label .arrow { transform: translateX(4px); }
        .cta-alt {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-weight: 500;
            font-size: 16px;
            letter-spacing: -0.01em;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity 0.3s ease, transform 0.3s cubic-bezier(.6,0,.4,1);
            color: #fff;
            pointer-events: none;
        }
        .cta:hover .cta-label { transform: translateY(-32px); opacity: 0.0; }
        .cta:hover .cta-alt { opacity: 1; transform: translateY(0); }

        /* "or" separator between password sign-in and Google. */
        .or-sep {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 20px 0;
            color: var(--ink-3);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-family: 'Geist Mono', ui-monospace, monospace;
        }
        .or-sep::before, .or-sep::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--line-2);
        }

        /* "Continue with Google" button — neutral surface so the Google
           multi-colour mark is the visual anchor; matches Google's brand
           guidelines (white background, 1px outline, dark text). */
        .google-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 14px 20px;
            background: #ffffff;
            color: var(--ink);
            border: 1px solid var(--line-2);
            border-radius: 14px;
            font-family: 'Geist', system-ui, sans-serif;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.005em;
            text-decoration: none;
            cursor: pointer;
            transition: border-color 0.18s ease, background 0.18s ease, transform 0.1s ease;
            box-shadow: 0 1px 2px rgba(14, 42, 58, 0.04);
        }
        .google-cta:hover {
            border-color: var(--ink-3);
            background: #fafafa;
        }
        .google-cta:active { transform: scale(0.985); }
        .google-cta svg { flex-shrink: 0; }

        .signup-line {
            margin: 28px 0 0;
            text-align: center;
            font-size: 13px;
            color: var(--ink-3);
        }
        .signup-line a {
            color: var(--teal);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1.5px solid transparent;
            padding-bottom: 1px;
            transition: border-color 0.2s ease;
        }
        .signup-line a:hover { border-bottom-color: var(--teal); }

        .desk-footer {
            margin-top: 64px;
            padding-top: 24px;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Geist Mono', monospace;
            font-size: 10px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--ink-3);
            max-width: 440px;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
        }
        .desk-footer .sep { color: var(--line-2); }

        /* ── Animations ───────────────────────────────────────────── */
        @keyframes drop {
            from { opacity: 0; transform: translateY(-14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(232,185,74,0.55); }
            50%      { box-shadow: 0 0 0 7px rgba(232,185,74,0); }
        }
        @keyframes sway {
            0%, 100% { transform: rotate(-2deg); }
            50%      { transform: rotate(2deg); }
        }
        .plaque { animation: sway 5s ease-in-out infinite; transform-origin: top center; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .plaque-block, .hero-kicker, .hero-title .word, .hero-sub, .stage-bottom, .desk { opacity: 1 !important; transform: none !important; }
        }
    </style>
</head>
<body>
    {{-- Mobile-only top strip — slim brand + locale toggle that replaces
         the hidden atmospheric stage on small screens. --}}
    <div class="mobile-top" aria-hidden="false">
        <a href="{{ url('/') }}" class="brand">
            <span class="brand-mark"><img src="{{ asset('icons/logo.svg') }}" alt=""></span>
            <span class="brand-name">Tempahlah</span>
        </a>
        <div class="locale" role="group" aria-label="Language">
            <a href="{{ route('locale.switch', 'ms') }}" class="{{ $isBM ? 'active' : '' }}">BM</a>
            <a href="{{ route('locale.switch', 'en') }}" class="{{ ! $isBM ? 'active' : '' }}">EN</a>
        </div>
    </div>

    <div class="ledger">

        {{-- ───── LEFT: Atmospheric stage ─────────────────────────── --}}
        <aside class="stage">
            <div class="stage-top">
                <a href="{{ url('/') }}" class="brand">
                    <span class="brand-mark"><img src="{{ asset('icons/logo.svg') }}" alt=""></span>
                    <span class="brand-word">
                        <span class="brand-name">Tempahlah</span>
                        <span class="brand-tag">Est. 2026 · Kuala Lumpur</span>
                    </span>
                </a>
                <div class="locale" role="group" aria-label="Language">
                    <a href="{{ route('locale.switch', 'ms') }}" class="{{ $isBM ? 'active' : '' }}">BM</a>
                    <a href="{{ route('locale.switch', 'en') }}" class="{{ ! $isBM ? 'active' : '' }}">EN</a>
                </div>
            </div>

            <div class="plaque-block">
                <div class="plaque" aria-hidden="true">
                    <div class="plaque-num">12</div>
                    <div class="plaque-label">{{ $isBM ? 'Bilik' : 'Suite' }}</div>
                </div>
                <div class="plaque-keychain">
                    {{ $isBM ? 'Kunci tetamu' : 'Guest key' }}
                    <strong>{{ $isBM ? 'Selamat pulang' : 'Welcome back' }}</strong>
                </div>
            </div>

            <div class="hero">
                <div class="hero-kicker">{{ $isBM ? '— Pintu dibuka semula' : '— The door is open' }}</div>
                <h1 class="hero-title">
                    @if ($isBM)
                        <span class="word" style="animation-delay:.55s">Kembali</span>
                        <span class="word brass" style="animation-delay:.62s">menjaga</span><br>
                        <span class="word" style="animation-delay:.70s">homestay</span>
                        <span class="word" style="animation-delay:.78s">anda.</span>
                    @else
                        <span class="word" style="animation-delay:.55s">Step</span>
                        <span class="word brass" style="animation-delay:.62s">back</span>
                        <span class="word" style="animation-delay:.70s">behind</span><br>
                        <span class="word" style="animation-delay:.78s">the</span>
                        <span class="word brass" style="animation-delay:.86s">counter.</span>
                    @endif
                </h1>
                <p class="hero-sub">
                    {{ $isBM
                        ? 'Tempahan, kalendar, dan tetamu anda sudah menunggu. Cuma daftar masuk — tiada apa-apa yang hilang sejak kali terakhir anda di sini.'
                        : 'Your bookings, calendars, and guests are right where you left them. Sign in to pick up the keys again.' }}
                </p>
            </div>

            <div class="stage-bottom">
                <div class="stage-bottom-l">
                    <span class="pulse-dot"></span>
                    {{ $isBM ? 'Sistem dalam talian' : 'All systems lit' }}
                </div>
                <div class="stage-bottom-r">
                    {{ $isBM ? '“Tempah terus, tanpa komisen.”' : '“Skip the queue. Book direct.”' }}
                </div>
            </div>
        </aside>

        {{-- ───── RIGHT: Ledger / form ───────────────────────────── --}}
        <main class="desk">
            <div class="form-wrap">
                <div class="heading">
                    <div class="heading-kicker">{{ $isBM ? 'Daftar masuk · Tuan rumah' : 'Sign in · Host portal' }}</div>
                    <h2 class="heading-title">
                        {{ $isBM ? 'Mari sambung di mana terhenti.' : 'Pick up where you left off.' }}
                    </h2>
                    <p class="heading-sub">
                        {{ $isBM
                            ? 'Sila masukkan e-mel dan kata laluan yang anda guna semasa daftar.'
                            : 'Enter the email and password you registered with.' }}
                    </p>
                </div>

                @if (session('status'))
                    <div class="flash-ok">{{ session('status') }}</div>
                @endif
                @if ($errors->any() && ! $errors->has('email') && ! $errors->has('password'))
                    <div class="flash-err">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf

                    <div class="field">
                        <label for="email" class="field-label">{{ __('Email') }}</label>
                        <input id="email" name="email" type="email" required autocomplete="email" autofocus
                               value="{{ old('email') }}"
                               class="field-input"
                               placeholder="anda@homestay.com">
                        @error('email') <span class="field-err">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <div class="field-row-between">
                            <label for="password" class="field-label">{{ __('Password') }}</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="field-aux">{{ $isBM ? 'Lupa kata laluan?' : 'Forgot password?' }}</a>
                            @endif
                        </div>
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               class="field-input"
                               placeholder="••••••••">
                        @error('password') <span class="field-err">{{ $message }}</span> @enderror
                    </div>

                    <label class="remember">
                        <input name="remember" type="checkbox" value="1">
                        <span class="remember-box">
                            <svg class="remember-check" viewBox="0 0 12 12"><polyline points="2.5,6.5 5,9 9.5,3.5"/></svg>
                        </span>
                        <span class="remember-label">
                            {{ $isBM ? 'Ingat saya — ' : 'Remember me — ' }}<em>{{ $isBM ? 'peranti ini boleh dipercayai' : 'this device is familiar' }}</em>
                        </span>
                    </label>

                    <button type="submit" class="cta">
                        <span class="cta-label">
                            {{ __('Sign in') }}
                            <span class="arrow" aria-hidden="true">→</span>
                        </span>
                        <span class="cta-alt" aria-hidden="true">
                            {{ $isBM ? 'Selamat datang kembali ✦' : 'Welcome home ✦' }}
                        </span>
                    </button>

                    <div class="or-sep" aria-hidden="true">
                        <span>{{ $isBM ? 'atau' : 'or' }}</span>
                    </div>

                    {{-- Continue with Google — kicks off the OAuth dance.
                         No form here; this is a plain link, the server signs
                         a CSRF-safe state in the redirect. --}}
                    <a class="google-cta" href="{{ route('auth.google.start', ['intent' => 'login']) }}">
                        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill="#4285F4" d="M17.64 9.205c0-.638-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/>
                            <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/>
                            <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>
                            <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>
                        </svg>
                        <span>{{ $isBM ? 'Teruskan dengan Google' : 'Continue with Google' }}</span>
                    </a>

                    <p class="signup-line">
                        {{ $isBM ? 'Belum ada akaun? ' : "Don't have an account? " }}
                        <a href="{{ route('register') }}">{{ $isBM ? 'Daftar di sini' : 'Sign up here' }}</a>
                    </p>
                </form>

                <div class="desk-footer">
                    <span>© {{ date('Y') }} Tempahlah</span>
                    <span class="sep">·</span>
                    <span>{{ $isBM ? 'Dibuat di Malaysia' : 'Made in Malaysia' }}</span>
                </div>
            </div>
        </main>
    </div>

    {{-- Lightweight parallax on the atmospheric panel — mouse-follow gradient blob --}}
    <script>
        (function () {
            const stage = document.querySelector('.stage');
            if (!stage || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            stage.addEventListener('mousemove', (e) => {
                const r = stage.getBoundingClientRect();
                const x = ((e.clientX - r.left) / r.width  * 100).toFixed(1);
                const y = ((e.clientY - r.top)  / r.height * 100).toFixed(1);
                stage.style.background = `
                    radial-gradient(700px 600px at ${x}% ${y}%, rgba(44,184,196,0.32) 0%, transparent 55%),
                    radial-gradient(700px 500px at 15% 20%, rgba(37,150,198,0.20) 0%, transparent 60%),
                    linear-gradient(165deg, #0a1f2c 0%, #143a52 45%, #1c5b7d 100%)`;
            });
        })();
    </script>
</body>
</html>
