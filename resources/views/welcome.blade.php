@php
    $locale = app()->getLocale();
    $isMs = $locale === 'ms';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2596c6">
    <title>Tempahlah — {{ $isMs ? 'Urus homestay anda tanpa drama' : 'Run your homestay without the drama' }}</title>
    <meta name="description" content="{{ $isMs ? 'SaaS homestay buatan Malaysia. Tempahan terus WhatsApp, AI auto-jawab, sifar komisen. Mula percuma.' : 'A Malaysian homestay SaaS. Direct WhatsApp bookings, AI auto-reply, zero commission. Start free.' }}">

    <link rel="icon" href="/icons/logo.svg" type="image/svg+xml">
    @include('partials.pwa')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400;1,9..144,500;1,9..144,600&family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --ink:          #14202c;
            --ink-2:        #3d4d5e;
            --ink-3:        #6b7a8a;
            --ink-4:        #97a3b0;
            --line:         #e6ebf0;
            --line-2:       #eef2f6;
            --bg:           #ffffff;
            --bg-warm:      #fafaf7;
            --bg-cool:      #f3f6f9;
            --bg-sunk:      #eaf1f6;

            --primary:      #2596c6;
            --primary-hover:#1f7eaf;
            --primary-deep: #14587f;
            --primary-tint: #e0eff7;
            --primary-soft: #f0f7fb;

            --secondary:    #2cb8c4;
            --secondary-tint:#dff5f7;

            --accent:       #e8b94a;
            --accent-deep:  #c99a30;
            --accent-tint:  #faf0d4;

            --success:      #4a8a5e;
            --success-tint: #e1f0e6;
            --danger:       #b94a3a;
            --danger-tint:  #f9e4e0;

            --font-display: 'Fraunces', Georgia, serif;
            --font-body:    'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono:    'Geist Mono', ui-monospace, 'SF Mono', monospace;

            --sh-sm:  0 1px 2px rgba(20,32,44,0.04), 0 2px 6px rgba(20,32,44,0.04);
            --sh-md:  0 4px 12px rgba(20,32,44,0.06), 0 12px 32px -8px rgba(20,32,44,0.08);
            --sh-lg:  0 16px 40px -12px rgba(20,32,44,0.14), 0 8px 20px -4px rgba(20,32,44,0.08);
            --sh-xl:  0 32px 80px -20px rgba(20,87,127,0.18), 0 16px 40px -8px rgba(20,32,44,0.08);
            --sh-glow:0 0 0 1px rgba(37,150,198,0.12), 0 24px 60px -16px rgba(37,150,198,0.32);

            --r-sm: 8px;
            --r-md: 12px;
            --r-lg: 18px;
            --r-xl: 24px;
            --r-2xl: 32px;
            --r-pill: 999px;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.55;
            color: var(--ink);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; cursor: pointer; border: none; background: none; color: inherit; }
        img, svg { display: block; max-width: 100%; }
        ::selection { background: var(--primary); color: #fff; }

        /* ========== TYPE SCALE ========== */
        .tm-kicker {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--ink-3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .tm-kicker.is-amber { color: var(--accent-deep); }
        .tm-kicker.is-primary { color: var(--primary-deep); }
        .tm-kicker::before {
            content: '';
            display: inline-block;
            width: 18px;
            height: 1px;
            background: currentColor;
            opacity: 0.5;
        }
        .tm-kicker.is-center { justify-content: center; }

        .tm-h1 {
            font-family: var(--font-display);
            font-weight: 500;
            font-size: clamp(40px, 6.8vw, 84px);
            line-height: 1.02;
            letter-spacing: -0.025em;
            color: var(--ink);
            margin: 0;
            text-wrap: balance;
        }
        .tm-h1 em {
            font-style: italic;
            font-weight: 500;
            color: var(--primary-deep);
            background: linear-gradient(180deg, transparent 62%, var(--accent-tint) 62%, var(--accent-tint) 88%, transparent 88%);
            padding: 0 4px;
        }
        .tm-h2 {
            font-family: var(--font-display);
            font-weight: 500;
            font-size: clamp(32px, 4.4vw, 56px);
            line-height: 1.06;
            letter-spacing: -0.02em;
            color: var(--ink);
            margin: 0 0 16px;
            text-wrap: balance;
        }
        .tm-h2 em {
            font-style: italic;
            font-weight: 500;
            color: var(--primary-deep);
        }
        .tm-h3 {
            font-family: var(--font-display);
            font-weight: 500;
            font-size: 22px;
            line-height: 1.18;
            letter-spacing: -0.01em;
            color: var(--ink);
            margin: 0 0 8px;
        }
        .tm-lead {
            font-size: 19px;
            line-height: 1.55;
            color: var(--ink-2);
            max-width: 56ch;
            margin: 0;
        }
        .tm-subtitle-en {
            font-family: var(--font-mono);
            font-size: 13px;
            color: var(--ink-3);
            font-style: italic;
            margin: 8px 0 0;
        }
        .tm-mono { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }

        /* ========== BUTTONS ========== */
        .tm-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: var(--r-pill);
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 500;
            letter-spacing: -0.005em;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, color 0.18s ease;
            white-space: nowrap;
            touch-action: manipulation;
            user-select: none;
        }
        .tm-btn-primary {
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-deep) 100%);
            color: #fff;
            box-shadow: 0 1px 0 rgba(255,255,255,0.18) inset, 0 6px 16px -4px rgba(20,87,127,0.4);
        }
        .tm-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 1px 0 rgba(255,255,255,0.2) inset, 0 10px 24px -4px rgba(20,87,127,0.48); }
        .tm-btn-ghost {
            background: transparent;
            color: var(--ink);
            border: 1px solid var(--line);
        }
        .tm-btn-ghost:hover { background: var(--bg-warm); border-color: var(--ink-4); }
        .tm-btn-lg { padding: 16px 28px; font-size: 16px; }
        .tm-btn-xl { padding: 20px 36px; font-size: 18px; border-radius: var(--r-pill); }
        .tm-btn-full { width: 100%; }
        .tm-btn .tm-arrow { transition: transform 0.18s ease; }
        .tm-btn:hover .tm-arrow { transform: translateX(3px); }

        /* ========== LAYOUT ========== */
        .tm-container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }
        .tm-container-narrow { max-width: 760px; }
        .tm-center { text-align: center; }

        /* ========== TICKER ========== */
        .tm-ticker {
            background: var(--ink);
            color: rgba(255,255,255,0.78);
            font-family: var(--font-mono);
            font-size: 12px;
            overflow: hidden;
            position: relative;
        }
        .tm-ticker-track {
            display: inline-flex;
            gap: 48px;
            padding: 10px 0;
            white-space: nowrap;
            animation: tickerSlide 38s linear infinite;
            will-change: transform;
        }
        .tm-ticker-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .tm-ticker-item .dot {
            width: 6px; height: 6px;
            background: #4ad68e;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(74, 214, 142, 0.6);
            animation: pulseDot 1.8s ease-in-out infinite;
        }
        .tm-ticker-item .sep { color: rgba(255,255,255,0.32); }
        @keyframes tickerSlide {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }
        @keyframes pulseDot {
            0%, 100% { box-shadow: 0 0 0 0 rgba(74, 214, 142, 0.6); }
            50% { box-shadow: 0 0 0 6px rgba(74, 214, 142, 0); }
        }

        /* ========== NAV ========== */
        .tm-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255, 255, 255, 0.78);
            -webkit-backdrop-filter: saturate(180%) blur(18px);
            backdrop-filter: saturate(180%) blur(18px);
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .tm-nav.is-scrolled {
            border-bottom-color: var(--line);
            background: rgba(255, 255, 255, 0.92);
        }
        .tm-nav-inner {
            max-width: 1240px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .tm-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-display);
            font-weight: 500;
            font-size: 22px;
            letter-spacing: -0.02em;
            color: var(--ink);
        }
        .tm-brand img { width: 32px; height: 32px; }
        .tm-brand-domain {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--ink-3);
            margin-left: -4px;
            margin-top: 6px;
            display: none;
        }
        @media (min-width: 768px) {
            .tm-brand-domain { display: inline; }
        }
        .tm-nav-links {
            display: none;
            gap: 28px;
            margin-left: 32px;
        }
        @media (min-width: 900px) {
            .tm-nav-links { display: flex; }
        }
        .tm-nav-links a {
            font-size: 14px;
            color: var(--ink-2);
            font-weight: 500;
            transition: color 0.15s ease;
        }
        .tm-nav-links a:hover { color: var(--primary-deep); }
        .tm-nav-cta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }
        .tm-locale {
            display: none;
            background: var(--bg-cool);
            border-radius: var(--r-pill);
            padding: 3px;
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 600;
        }
        @media (min-width: 768px) { .tm-locale { display: inline-flex; } }
        .tm-locale a {
            padding: 5px 10px;
            border-radius: var(--r-pill);
            color: var(--ink-3);
            transition: all 0.15s ease;
        }
        .tm-locale a.is-active {
            background: #fff;
            color: var(--ink);
            box-shadow: var(--sh-sm);
        }
        .tm-nav-cta .tm-btn { padding: 9px 18px; font-size: 14px; }
        /* Mobile — keep both Login + Start free visible. Tighten the
           ghost (Login) so brand + locale + 2 buttons fit on 375px.
           Below 380px we drop the border and treat Login as a plain
           text link, and we hide the locale pills to buy more width. */
        @media (max-width: 767px) {
            .tm-nav-cta { gap: 6px; }
            .tm-nav-inner { gap: 10px; padding: 12px 16px; }
            .tm-nav-cta .tm-btn { padding: 8px 12px; font-size: 13px; }
            .tm-nav-cta .tm-btn-ghost { background: transparent; border-color: transparent; padding: 8px 6px; }
        }
        @media (max-width: 380px) {
            .tm-locale { display: none; }
        }

        /* ========== HERO ========== */
        .tm-hero {
            position: relative;
            padding: 64px 0 96px;
            overflow: hidden;
            isolation: isolate;
        }
        .tm-hero-bg {
            position: absolute;
            inset: -40% 0 0 0;
            z-index: -1;
            background:
                radial-gradient(70% 50% at 80% 12%, rgba(232, 185, 74, 0.16) 0%, transparent 60%),
                radial-gradient(60% 50% at 12% 28%, rgba(44, 184, 196, 0.20) 0%, transparent 60%),
                radial-gradient(80% 60% at 50% -10%, rgba(37, 150, 198, 0.10) 0%, transparent 60%);
            pointer-events: none;
        }
        .tm-hero-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 48px;
            align-items: center;
        }
        @media (min-width: 1024px) {
            .tm-hero-grid { grid-template-columns: 1.1fr 0.9fr; gap: 64px; }
            .tm-hero { padding: 96px 0 128px; }
        }
        .tm-hero-copy > * + * { margin-top: 24px; }
        .tm-hero-copy .tm-h1 { margin-top: 18px; }
        .tm-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .tm-hero-trust {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            margin-top: 32px;
        }
        .tm-hero-trust-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .tm-hero-trust-item strong {
            font-family: var(--font-mono);
            font-size: 22px;
            font-weight: 600;
            color: var(--ink);
            letter-spacing: -0.02em;
        }
        .tm-hero-trust-item span {
            font-size: 12px;
            color: var(--ink-3);
            font-family: var(--font-mono);
        }
        .tm-hero-trust .sep {
            width: 1px;
            height: 32px;
            background: var(--line);
        }

        /* ========== PHONE MOCK ========== */
        .tm-hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            min-height: 560px;
        }
        .tm-phone {
            width: 320px;
            height: 640px;
            background: #14202c;
            border-radius: 44px;
            padding: 14px;
            box-shadow:
                0 1px 0 rgba(255,255,255,0.08) inset,
                0 60px 100px -30px rgba(20,87,127,0.45),
                0 30px 60px -20px rgba(20,32,44,0.3);
            position: relative;
            z-index: 2;
            transform: rotate(-3deg);
            transition: transform 0.5s cubic-bezier(.2,.8,.2,1);
        }
        .tm-phone:hover { transform: rotate(-1.5deg) translateY(-4px); }
        .tm-phone::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 24px;
            background: #0a1218;
            border-radius: 14px;
            z-index: 2;
        }
        .tm-phone-screen {
            width: 100%;
            height: 100%;
            background: #ece5dd;
            border-radius: 32px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .tm-wa-header {
            background: linear-gradient(180deg, #075e54, #064d44);
            color: #fff;
            padding: 44px 16px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .tm-wa-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex; align-items: center; justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.2);
        }
        .tm-wa-name { font-size: 14px; font-weight: 600; line-height: 1.2; }
        .tm-wa-status { font-size: 11px; opacity: 0.8; display: flex; align-items: center; gap: 4px; }
        .tm-wa-status::before { content: ''; width: 6px; height: 6px; background: #4ad68e; border-radius: 50%; }
        .tm-wa-messages {
            flex: 1;
            padding: 16px 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background:
                linear-gradient(rgba(236,229,221,0.86), rgba(236,229,221,0.86)),
                repeating-linear-gradient(45deg, rgba(0,0,0,0.02) 0 2px, transparent 2px 12px);
            overflow: hidden;
        }
        .tm-wa-msg {
            max-width: 78%;
            padding: 8px 12px 18px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.4;
            position: relative;
            box-shadow: 0 1px 1px rgba(0,0,0,0.08);
            opacity: 0;
            transform: translateY(8px);
            animation: msgIn 0.4s ease forwards;
        }
        .tm-wa-msg:nth-child(1) { animation-delay: 0.3s; }
        .tm-wa-msg:nth-child(2) { animation-delay: 0.8s; }
        .tm-wa-msg:nth-child(3) { animation-delay: 1.3s; }
        .tm-wa-msg:nth-child(4) { animation-delay: 1.7s; }
        .tm-wa-msg:nth-child(5) { animation-delay: 2.2s; }
        .tm-wa-msg:nth-child(6) { animation-delay: 2.6s; }
        @keyframes msgIn { to { opacity: 1; transform: translateY(0); } }
        .tm-wa-msg .time {
            position: absolute;
            bottom: 4px;
            right: 8px;
            font-size: 9px;
            color: rgba(0,0,0,0.4);
            font-family: var(--font-mono);
        }
        .tm-wa-msg.in {
            background: #fff;
            align-self: flex-start;
            border-top-left-radius: 2px;
        }
        .tm-wa-msg.out {
            background: #dcf8c6;
            align-self: flex-end;
            border-top-right-radius: 2px;
        }
        .tm-wa-msg.out .time { color: rgba(0,80,40,0.5); }
        .tm-wa-msg.out .check { color: #34b7f1; margin-left: 2px; }
        .tm-wa-msg-photos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            margin: -4px -8px 4px;
            padding: 0;
            border-radius: 6px;
            overflow: hidden;
        }
        .tm-wa-photo {
            aspect-ratio: 1;
            background: linear-gradient(135deg, #c9a876, #8b6f4d);
            position: relative;
        }
        .tm-wa-photo:nth-child(2) { background: linear-gradient(135deg, #5fa68f, #2f6e5c); }
        .tm-wa-photo:nth-child(3) { background: linear-gradient(135deg, #d4a374, #9c7548); }
        .tm-wa-photo:nth-child(4) { background: linear-gradient(135deg, #6d8eb0, #3a5d80); position: relative; }
        .tm-wa-photo:nth-child(4)::after {
            content: '+3';
            position: absolute;
            inset: 0;
            display: flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,0.5);
            color: #fff;
            font-weight: 600;
            font-size: 14px;
        }
        .tm-wa-bot-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 9px;
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--primary-deep);
            background: var(--primary-tint);
            padding: 2px 6px;
            border-radius: 4px;
            margin-bottom: 4px;
        }
        .tm-wa-input {
            padding: 8px 10px;
            background: rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tm-wa-input .field {
            flex: 1;
            background: #fff;
            border-radius: var(--r-pill);
            padding: 6px 12px;
            font-size: 11px;
            color: var(--ink-3);
        }

        /* Floating chips around phone */
        .tm-chip {
            position: absolute;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--r-md);
            padding: 10px 14px;
            box-shadow: var(--sh-md);
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 3;
            animation: chipFloat 4s ease-in-out infinite;
        }
        .tm-chip-1 {
            top: 40px;
            left: -8%;
            animation-delay: 0s;
        }
        .tm-chip-2 {
            top: 38%;
            right: -10%;
            animation-delay: 0.6s;
        }
        .tm-chip-3 {
            bottom: 60px;
            left: 4%;
            animation-delay: 1.2s;
        }
        @keyframes chipFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        .tm-chip .tm-mono { color: var(--primary-deep); font-weight: 600; }
        .tm-chip .pulse {
            width: 8px; height: 8px;
            background: var(--secondary);
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(44,184,196,0.6);
            animation: pulseDot 1.8s ease-in-out infinite;
        }
        .tm-chip .check {
            width: 18px; height: 18px;
            background: var(--success);
            border-radius: 50%;
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
        }
        @media (max-width: 600px) {
            .tm-hero-visual { transform: scale(0.86); min-height: 480px; margin-top: 24px; }
            .tm-chip { font-size: 11px; padding: 8px 12px; }
        }

        /* ========== STAGGER REVEAL ========== */
        .tm-reveal { opacity: 0; transform: translateY(16px); animation: revealUp 0.7s cubic-bezier(.2,.8,.2,1) forwards; }
        .tm-reveal-1 { animation-delay: 0.05s; }
        .tm-reveal-2 { animation-delay: 0.15s; }
        .tm-reveal-3 { animation-delay: 0.25s; }
        .tm-reveal-4 { animation-delay: 0.35s; }
        .tm-reveal-5 { animation-delay: 0.45s; }
        @keyframes revealUp { to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
            .tm-reveal { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>

{{-- ============ TICKER ============ --}}
<div class="tm-ticker" aria-hidden="true">
    <div class="tm-ticker-track">
        @php
            $tick = $isMs ? [
                ['Aisha', 'tempah Wafa Homestay Kluang', '2 min'],
                ['Wan', 'check-in di Pondok Pak Su, Cameron', '5 min'],
                ['Faridah', 'terima RM 480 di Villa Tepi Sungai, Hulu Langat', '8 min'],
                ['Rizal', 'tempah D\'Saujana Stay, Melaka', '12 min'],
                ['Nora', 'hantar gambar bilik (AI Agent)', '15 min'],
                ['Hafiz', 'check-in di Anjung Pantai, Terengganu', '18 min'],
                ['Sarah', 'tempah Rumah Sawah Sekinchan', '22 min'],
            ] : [
                ['Aisha', 'booked Wafa Homestay Kluang', '2 min'],
                ['Wan', 'checked in at Pondok Pak Su, Cameron', '5 min'],
                ['Faridah', 'received RM 480 at Villa Tepi Sungai', '8 min'],
                ['Rizal', 'booked D\'Saujana Stay, Melaka', '12 min'],
                ['Nora', 'sent room photos via AI Agent', '15 min'],
                ['Hafiz', 'checked in at Anjung Pantai, Terengganu', '18 min'],
                ['Sarah', 'booked Rumah Sawah Sekinchan', '22 min'],
            ];
        @endphp
        @for ($i = 0; $i < 2; $i++)
            @foreach ($tick as $t)
                <span class="tm-ticker-item">
                    <span class="dot"></span>
                    <span>{{ $t[0] }} {{ $t[1] }}</span>
                    <span class="sep">·</span>
                    <span>{{ $t[2] }} {{ $isMs ? 'lalu' : 'ago' }}</span>
                </span>
                <span class="sep">◆</span>
            @endforeach
        @endfor
    </div>
</div>

{{-- ============ NAV ============ --}}
<header class="tm-nav" id="tmNav">
    <div class="tm-nav-inner">
        <a class="tm-brand" href="{{ route('hosts') }}">
            <img src="/icons/logo.svg" alt="">
            <span>tempahlah</span>
            <span class="tm-brand-domain">.com</span>
        </a>
        <nav class="tm-nav-links">
            <a href="#features">{{ $isMs ? 'Ciri-ciri' : 'Features' }}</a>
            <a href="#how">{{ $isMs ? 'Bagaimana' : 'How it works' }}</a>
            <a href="#pricing">{{ $isMs ? 'Harga' : 'Pricing' }}</a>
            <a href="{{ route('marketplace.search') }}">{{ $isMs ? 'Cari homestay' : 'Browse homestays' }}</a>
        </nav>
        <div class="tm-nav-cta">
            <div class="tm-locale" role="group" aria-label="Language">
                <a href="{{ route('locale.switch', 'ms') }}" class="{{ $isMs ? 'is-active' : '' }}">MS</a>
                <a href="{{ route('locale.switch', 'en') }}" class="{{ !$isMs ? 'is-active' : '' }}">EN</a>
            </div>
            @auth
                <a href="{{ route('tenant.dashboard') }}" class="tm-btn tm-btn-primary">
                    {{ $isMs ? 'Papan pemuka' : 'Dashboard' }}
                    <span class="tm-arrow">→</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="tm-btn tm-btn-ghost">{{ $isMs ? 'Log masuk' : 'Sign in' }}</a>
                <a href="{{ route('register') }}" class="tm-btn tm-btn-primary">
                    {{ $isMs ? 'Mula percuma' : 'Start free' }}
                    <span class="tm-arrow">→</span>
                </a>
            @endauth
        </div>
    </div>
</header>

{{-- ============ HERO ============ --}}
<section class="tm-hero">
    <div class="tm-hero-bg"></div>
    <div class="tm-container">
        <div class="tm-hero-grid">
            <div class="tm-hero-copy">
                <span class="tm-kicker tm-reveal tm-reveal-1">
                    {{ $isMs ? 'SAAS HOMESTAY · BUATAN MALAYSIA' : 'HOMESTAY SAAS · MADE IN MALAYSIA' }}
                </span>
                <h1 class="tm-h1 tm-reveal tm-reveal-2">
                    @if ($isMs)
                        Urus homestay anda <em>tanpa drama</em>.
                    @else
                        Run your homestay, <em>without the drama</em>.
                    @endif
                </h1>
                <p class="tm-lead tm-reveal tm-reveal-3">
                    @if ($isMs)
                        Tempahan terus dari pelanggan. AI balas WhatsApp 24 jam. Sifar komisen — duit masuk akaun anda sendiri, bukan Airbnb.
                    @else
                        Direct bookings from guests. AI replies to WhatsApp around the clock. Zero commission — payments land in your account, not Airbnb's.
                    @endif
                </p>
                <div class="tm-hero-actions tm-reveal tm-reveal-4">
                    <a href="{{ route('register') }}" class="tm-btn tm-btn-primary tm-btn-lg">
                        {{ $isMs ? 'Mula percuma · RM 0' : 'Start free · RM 0' }}
                        <span class="tm-arrow">→</span>
                    </a>
                    <a href="#how" class="tm-btn tm-btn-ghost tm-btn-lg">
                        <span style="display:inline-flex;width:18px;height:18px;background:var(--primary-tint);border-radius:50%;align-items:center;justify-content:center;color:var(--primary-deep);font-size:9px;">▶</span>
                        {{ $isMs ? 'Tengok demo 2 minit' : 'Watch 2-min demo' }}
                    </a>
                </div>
                <div class="tm-hero-trust tm-reveal tm-reveal-5">
                    <div class="tm-hero-trust-item">
                        <strong>200+</strong>
                        <span>{{ $isMs ? 'tuan rumah' : 'hosts' }}</span>
                    </div>
                    <div class="sep"></div>
                    <div class="tm-hero-trust-item">
                        <strong>14</strong>
                        <span>{{ $isMs ? 'negeri' : 'states' }}</span>
                    </div>
                    <div class="sep"></div>
                    <div class="tm-hero-trust-item">
                        <strong>RM 0</strong>
                        <span>{{ $isMs ? 'komisen' : 'commission' }}</span>
                    </div>
                    <div class="sep"></div>
                    <div class="tm-hero-trust-item">
                        <strong>★ 4.9</strong>
                        <span>{{ $isMs ? 'penilaian' : 'rating' }}</span>
                    </div>
                </div>
            </div>

            <div class="tm-hero-visual tm-reveal tm-reveal-3" aria-hidden="true">
                <div class="tm-phone">
                    <div class="tm-phone-screen">
                        <div class="tm-wa-header">
                            <div class="tm-wa-avatar">W</div>
                            <div>
                                <div class="tm-wa-name">Wafa Homestay Kluang</div>
                                <div class="tm-wa-status">{{ $isMs ? 'pembantu AI · dalam talian' : 'AI assistant · online' }}</div>
                            </div>
                        </div>
                        <div class="tm-wa-messages">
                            <div class="tm-wa-msg in">
                                {{ $isMs ? 'Hai, ada bilik untuk 3 orang 28–30 Jun?' : 'Hi, any rooms for 3 pax 28–30 Jun?' }}
                                <span class="time">2:14 PM</span>
                            </div>
                            <div class="tm-wa-msg out">
                                <span class="tm-wa-bot-tag">★ AI</span><br>
                                {{ $isMs ? 'Salam! 28–30 Jun ada — RM 480 untuk 2 malam (whole house, 6 orang). Nak saya tahan slot?' : 'Salam! 28–30 Jun is open — RM 480 for 2 nights (whole house, sleeps 6). Want me to hold it?' }}
                                <span class="time">2:14 PM <span class="check">✓✓</span></span>
                            </div>
                            <div class="tm-wa-msg in">
                                {{ $isMs ? 'Ada gambar?' : 'Got photos?' }}
                                <span class="time">2:15 PM</span>
                            </div>
                            <div class="tm-wa-msg out">
                                <div class="tm-wa-msg-photos">
                                    <div class="tm-wa-photo"></div>
                                    <div class="tm-wa-photo"></div>
                                    <div class="tm-wa-photo"></div>
                                    <div class="tm-wa-photo"></div>
                                </div>
                                {{ $isMs ? '6 keping gambar dihantar' : '6 photos sent' }}
                                <span class="time">2:15 PM <span class="check">✓✓</span></span>
                            </div>
                            <div class="tm-wa-msg in">
                                {{ $isMs ? 'Best! Boleh tempah' : 'Looks great! I\'ll book' }}
                                <span class="time">2:16 PM</span>
                            </div>
                            <div class="tm-wa-msg out">
                                {{ $isMs ? 'Deposit RM 96 di sini ▸ toyyibpay.com/abc' : 'Deposit RM 96 here ▸ toyyibpay.com/abc' }}
                                <span class="time">2:16 PM <span class="check">✓✓</span></span>
                            </div>
                        </div>
                        <div class="tm-wa-input">
                            <span class="field">{{ $isMs ? 'Taip mesej…' : 'Type a message…' }}</span>
                        </div>
                    </div>
                </div>
                <div class="tm-chip tm-chip-1">
                    <span class="pulse"></span>
                    {{ $isMs ? 'Tempahan baru' : 'New booking' }}
                </div>
                <div class="tm-chip tm-chip-2">
                    <span class="tm-mono">RM 480</span> {{ $isMs ? 'masuk' : 'received' }}
                </div>
                <div class="tm-chip tm-chip-3">
                    <span class="check">✓</span>
                    {{ $isMs ? 'Disahkan' : 'Confirmed' }}
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============ LOGO STRIP ============ --}}
<section class="tm-logos" aria-label="Trusted by">
    <div class="tm-container">
        <p class="tm-kicker is-center">{{ $isMs ? 'DIPERCAYAI OLEH HOMESTAY DI SELURUH MALAYSIA' : 'TRUSTED BY HOSTS ACROSS MALAYSIA' }}</p>
        <div class="tm-logos-row">
            @foreach ([
                ['Wafa Homestay', 'Kluang, Johor', 'Fraunces'],
                ['D\'Pondok Sawah', 'Sekinchan, Selangor', 'Geist'],
                ['Anjung Pantai', 'Marang, Terengganu', 'Fraunces'],
                ['Villa Bukit Tinggi', 'Genting, Pahang', 'Geist'],
                ['Rumah Nenek', 'Melaka', 'Fraunces'],
                ['Pondok Pak Su', 'Cameron, Pahang', 'Geist'],
                ['D\'Saujana Stay', 'Putrajaya', 'Fraunces'],
            ] as $h)
                <div class="tm-logo-item" style="font-family: {{ $h[2] === 'Fraunces' ? 'var(--font-display)' : 'var(--font-body)' }};">
                    <span class="name">{{ $h[0] }}</span>
                    <span class="loc">{{ $h[1] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ PROBLEM ============ --}}
<section class="tm-problem">
    <div class="tm-container">
        <div class="tm-problem-head">
            <span class="tm-kicker">{{ $isMs ? 'MASALAH BIASA' : 'COMMON PAIN' }}</span>
            <h2 class="tm-h2">
                @if ($isMs)
                    Tuan rumah ramai <em>pening</em><br>dengan benda yang sama.
                @else
                    Most hosts get <em>burned out</em><br>by the same four things.
                @endif
            </h2>
        </div>
        <div class="tm-problem-grid">
            @php
                $problems = $isMs ? [
                    ['💸', 'Airbnb potong 15%', 'Setiap RM 500 tempahan, Airbnb ambil RM 75. Setahun? RM 9,000 hilang.'],
                    ['📱', 'WhatsApp tak putus', 'Pukul 2 pagi pun ada orang tanya harga. Letih balas satu-satu.'],
                    ['📅', 'Double-booking', 'Airbnb confirm, dah ada orang tempah terus. Mesti minta maaf, hilang trust.'],
                    ['📄', 'Resit tulis tangan', 'Screenshot bayaran WhatsApp, tulis dalam buku. Bila nak audit?'],
                ] : [
                    ['💸', '15% commission', 'Every RM 500 booking, Airbnb takes RM 75. Over a year? RM 9,000 gone.'],
                    ['📱', 'WhatsApp 24/7', 'Even at 2am someone asks for rates. Replying one by one is exhausting.'],
                    ['📅', 'Double-bookings', 'Airbnb confirms while someone books direct. Awkward refunds, lost trust.'],
                    ['📄', 'Handwritten receipts', 'Payment screenshot, ledger entry. Auditing is a nightmare.'],
                ];
            @endphp
            @foreach ($problems as $p)
                <article class="tm-problem-card">
                    <div class="tm-problem-icon">{{ $p[0] }}</div>
                    <h3 class="tm-h3">{{ $p[1] }}</h3>
                    <p>{{ $p[2] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ FEATURES GRID ============ --}}
<section class="tm-features" id="features">
    <div class="tm-container">
        <div class="tm-section-head">
            <span class="tm-kicker is-center">{{ $isMs ? 'SEMUA YANG ANDA PERLU' : 'EVERYTHING YOU NEED' }}</span>
            <h2 class="tm-h2 tm-center">
                @if ($isMs)
                    Satu platform.<br><em>Habis cerita.</em>
                @else
                    One platform.<br><em>End of story.</em>
                @endif
            </h2>
            <p class="tm-lead tm-center" style="margin-left:auto;margin-right:auto;">
                {{ $isMs ? 'Tiada lagi 5 aplikasi yang berbeza. Semua tools homestay anda di satu tempat — direka khas untuk Malaysia.' : 'No more juggling 5 different apps. Every homestay tool you need, built for Malaysia.' }}
            </p>
        </div>

        <div class="tm-feat-grid">
            {{-- Feature 1: Direct WhatsApp booking --}}
            <article class="tm-feat tm-feat-tall">
                <header>
                    <span class="tm-feat-num">01</span>
                    <h3 class="tm-h3">{{ $isMs ? 'Tempahan terus WhatsApp' : 'Direct WhatsApp bookings' }}</h3>
                    <p>{{ $isMs ? 'Pelanggan mesej, pelanggan bayar, duit masuk akaun anda. Sifar perantara.' : 'Guests message, pay, and the money lands in your account. Zero middlemen.' }}</p>
                </header>
                <div class="tm-feat-visual tm-feat-vis-wa">
                    <div class="tm-mini-msg in">{{ $isMs ? 'Ada bilik untuk 2?' : 'Room for 2?' }}</div>
                    <div class="tm-mini-msg out">{{ $isMs ? 'Ada! Berapa malam?' : 'Yes! How many nights?' }}</div>
                    <div class="tm-mini-msg in">2</div>
                    <div class="tm-mini-msg out">RM 320 · <span class="tm-mono">toyyibpay.com/x</span></div>
                </div>
            </article>

            {{-- Feature 2: AI Agent (wide pro feature) --}}
            <article class="tm-feat tm-feat-wide tm-feat-pro">
                <span class="tm-pro-pill">★ PRO</span>
                <header>
                    <span class="tm-feat-num">02</span>
                    <h3 class="tm-h3">{{ $isMs ? 'Pembantu AI auto-jawab' : 'AI agent auto-replies' }}</h3>
                    <p>{{ $isMs ? 'Bot bercakap macam manusia. Tahu harga, tarikh kosong, hantar gambar — automatik. Bila masalah sensitif, escalate ke anda.' : 'A bot that sounds human. Knows your rates, open dates, sends photos — auto. When things get sensitive, it hands off to you.' }}</p>
                </header>
                <div class="tm-feat-visual tm-feat-vis-ai">
                    <div class="tm-ai-row">
                        <span class="tm-ai-tool">🛏️</span>
                        <span class="tm-mono">check_availability</span>
                        <span class="tm-ai-result">28–30 Jun ✓</span>
                    </div>
                    <div class="tm-ai-row">
                        <span class="tm-ai-tool">💰</span>
                        <span class="tm-mono">get_quote</span>
                        <span class="tm-ai-result">RM 480.00</span>
                    </div>
                    <div class="tm-ai-row">
                        <span class="tm-ai-tool">📸</span>
                        <span class="tm-mono">send_photos</span>
                        <span class="tm-ai-result">6 sent</span>
                    </div>
                    <div class="tm-ai-row">
                        <span class="tm-ai-tool">🔗</span>
                        <span class="tm-mono">create_deposit_link</span>
                        <span class="tm-ai-result">RM 96 ✓</span>
                    </div>
                </div>
            </article>

            {{-- Feature 3: Private booking page (free path link, Pro subdomain) --}}
            <article class="tm-feat">
                <header>
                    <span class="tm-feat-num">03</span>
                    <h3 class="tm-h3">{{ $isMs ? 'Laman tempahan peribadi' : 'Private booking page' }}</h3>
                    <p>{{ $isMs ? 'Percuma dapat pautan sendiri. Naik taraf Pro untuk subdomain pendek anda sendiri. Tiada iklan pesaing, tiada komisen.' : 'Free gets your own link. Upgrade to Pro for your own short subdomain. No competitor ads, no commission.' }}</p>
                </header>
                <div class="tm-feat-visual tm-feat-vis-url">
                    <div class="tm-url-mock">
                        <span class="dots"><i></i><i></i><i></i></span>
                        <span class="url">
                            <span class="url-tag">{{ $isMs ? 'Percuma' : 'Free' }}</span>
                            <span class="proto">https://</span><span class="dom">tempahlah.com/</span><span class="slug">wafahomestay</span>
                        </span>
                        <span class="url url-pro">
                            <span class="url-tag url-tag-pro">★ Pro</span>
                            <span class="proto">https://</span><span class="slug">wafahomestay</span><span class="dom">.tempahlah.com</span>
                        </span>
                    </div>
                </div>
            </article>

            {{-- Feature 4: Calendar & channel sync --}}
            <article class="tm-feat">
                <header>
                    <span class="tm-feat-num">04</span>
                    <h3 class="tm-h3">{{ $isMs ? 'Sync Airbnb, Booking.com & Google' : 'Airbnb, Booking.com & Google sync' }}</h3>
                    <p>{{ $isMs ? 'Dua arah. Tempahan di Airbnb atau Booking.com terus block tarikh di sini — dan sebaliknya. Tiada lagi double-booking.' : 'Two-way. A booking on Airbnb or Booking.com blocks the dates here — and vice versa. No more double-bookings.' }}</p>
                </header>
                <div class="tm-feat-visual tm-feat-vis-cal">
                    <div class="tm-mini-cal">
                        @for ($i = 1; $i <= 21; $i++)
                            <span class="tm-cal-cell {{ in_array($i, [8,9,15,16]) ? 'is-booked' : (in_array($i, [12,13]) ? 'is-pending' : '') }}">{{ $i }}</span>
                        @endfor
                    </div>
                </div>
            </article>

            {{-- Feature 5: Dynamic pricing --}}
            <article class="tm-feat tm-feat-pro">
                <span class="tm-pro-pill">★ PRO</span>
                <header>
                    <span class="tm-feat-num">05</span>
                    <h3 class="tm-h3">{{ $isMs ? 'Harga dinamik' : 'Dynamic pricing' }}</h3>
                    <p>{{ $isMs ? 'Hujung minggu naik. Cuti sekolah naik lagi. Set sekali — jalan.' : 'Weekends up. School holidays up more. Set it once — done.' }}</p>
                </header>
                <div class="tm-feat-visual tm-feat-vis-price">
                    <div class="tm-price-row"><span>{{ $isMs ? 'Hari biasa' : 'Weekday' }}</span><span class="tm-mono">RM 220</span></div>
                    <div class="tm-price-row tm-up"><span>{{ $isMs ? 'Hujung minggu' : 'Weekend' }}</span><span class="tm-mono">RM 280 ↑</span></div>
                    <div class="tm-price-row tm-up-hi"><span>{{ $isMs ? 'Cuti sekolah' : 'School holiday' }}</span><span class="tm-mono">RM 350 ↑↑</span></div>
                </div>
            </article>

            {{-- Feature 6: Invoices --}}
            <article class="tm-feat">
                <header>
                    <span class="tm-feat-num">06</span>
                    <h3 class="tm-h3">{{ $isMs ? 'Invois & resit auto' : 'Auto invoices & receipts' }}</h3>
                    <p>{{ $isMs ? 'PDF kemas, jenama anda. Email auto kepada tetamu.' : 'Crisp PDFs, your branding. Auto-emailed to guests.' }}</p>
                </header>
                <div class="tm-feat-visual tm-feat-vis-pdf">
                    <div class="tm-pdf-mini">
                        <div class="pdf-head">INVOIS · INV-2026-0481</div>
                        <div class="pdf-row"><span>Wafa Homestay</span><span class="tm-mono">RM 480.00</span></div>
                        <div class="pdf-row sub"><span>SST 8%</span><span class="tm-mono">RM 38.40</span></div>
                        <div class="pdf-row total"><span>{{ $isMs ? 'Jumlah' : 'Total' }}</span><span class="tm-mono">RM 518.40</span></div>
                    </div>
                </div>
            </article>
        </div>

        <div class="tm-feat-more">
            <span>{{ $isMs ? 'Lagi:' : 'Plus:' }}</span>
            @foreach ([
                $isMs ? 'Toyyibpay' : 'Toyyibpay',
                $isMs ? 'Sync Airbnb' : 'Airbnb sync',
                $isMs ? 'Pekerja & jadual' : 'Staff & shifts',
                $isMs ? 'Pengurusan tetamu' : 'Guest manager',
                $isMs ? 'Tugasan housekeeping' : 'Housekeeping tasks',
                $isMs ? 'Senarai inventori' : 'Inventory',
                $isMs ? 'Laporan kewangan' : 'Financial reports',
                $isMs ? 'API access' : 'API access',
            ] as $f)
                <span class="tm-tag">{{ $f }}</span>
            @endforeach
        </div>
    </div>
</section>

<style>
/* ========== LOGOS STRIP ========== */
.tm-logos { padding: 56px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); background: var(--bg-warm); }
.tm-logos .tm-kicker { display: flex; }
.tm-logos-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px 40px;
    margin-top: 28px;
}
@media (min-width: 640px) { .tm-logos-row { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 960px) { .tm-logos-row { grid-template-columns: repeat(7, 1fr); gap: 16px 24px; } }
.tm-logo-item { display: flex; flex-direction: column; gap: 2px; text-align: center; }
.tm-logo-item .name { font-size: 16px; font-weight: 500; color: var(--ink-2); letter-spacing: -0.01em; }
.tm-logo-item .loc { font-family: var(--font-mono); font-size: 10px; color: var(--ink-4); letter-spacing: 0.05em; text-transform: uppercase; }

/* ========== PROBLEM ========== */
.tm-problem { padding: 96px 0; background: var(--bg); }
.tm-problem-head { margin-bottom: 48px; max-width: 720px; }
.tm-problem-head .tm-h2 { margin-top: 16px; }
.tm-problem-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1px;
    background: var(--line);
    border: 1px solid var(--line);
    border-radius: var(--r-xl);
    overflow: hidden;
}
@media (min-width: 640px) { .tm-problem-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .tm-problem-grid { grid-template-columns: repeat(4, 1fr); } }
.tm-problem-card {
    background: #fff;
    padding: 28px 24px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: background 0.18s ease;
}
.tm-problem-card:hover { background: var(--bg-warm); }
.tm-problem-icon { font-size: 28px; }
.tm-problem-card p { color: var(--ink-2); margin: 0; font-size: 14px; line-height: 1.55; }

/* ========== FEATURES ========== */
.tm-features { padding: 112px 0; background: var(--bg-warm); position: relative; }
.tm-features::before {
    content: ''; position: absolute; inset: 0;
    background:
        radial-gradient(50% 40% at 100% 0%, rgba(232, 185, 74, 0.06) 0%, transparent 60%),
        radial-gradient(50% 40% at 0% 100%, rgba(44, 184, 196, 0.06) 0%, transparent 60%);
    pointer-events: none;
}
.tm-section-head { text-align: center; max-width: 680px; margin: 0 auto 56px; }
.tm-section-head .tm-kicker { display: inline-flex; }
.tm-section-head .tm-h2 { margin-top: 16px; }
.tm-section-head .tm-lead { margin-top: 16px; text-align: center; }

.tm-feat-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    position: relative;
}
@media (min-width: 720px) { .tm-feat-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) {
    .tm-feat-grid {
        grid-template-columns: repeat(3, 1fr);
        grid-auto-flow: dense;
    }
    .tm-feat-wide { grid-column: span 2; }
    .tm-feat-tall { grid-row: span 1; }
}

.tm-feat {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: var(--r-xl);
    padding: 28px;
    display: flex;
    flex-direction: column;
    gap: 24px;
    position: relative;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    overflow: hidden;
}
.tm-feat:hover {
    transform: translateY(-3px);
    box-shadow: var(--sh-lg);
    border-color: transparent;
}
.tm-feat header { display: flex; flex-direction: column; gap: 8px; }
.tm-feat header p { color: var(--ink-2); margin: 0; font-size: 14.5px; line-height: 1.55; }
.tm-feat-num {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 500;
    color: var(--ink-4);
    letter-spacing: 0.12em;
}
.tm-feat-pro { background: linear-gradient(180deg, #fff 0%, var(--secondary-tint) 100%); border-color: var(--secondary); }
.tm-pro-pill {
    position: absolute;
    top: 20px; right: 20px;
    background: linear-gradient(135deg, var(--accent), var(--accent-deep));
    color: #fff;
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: var(--r-pill);
    letter-spacing: 0.08em;
    box-shadow: 0 4px 12px -2px rgba(232,185,74,0.4);
}
.tm-feat-visual {
    margin-top: auto;
    padding: 16px;
    background: var(--bg-warm);
    border-radius: var(--r-md);
    border: 1px solid var(--line);
}

/* WhatsApp mini */
.tm-feat-vis-wa { background: #ece5dd; display: flex; flex-direction: column; gap: 6px; padding: 12px; }
.tm-mini-msg { font-size: 12px; padding: 6px 10px; border-radius: 8px; max-width: 80%; box-shadow: 0 1px 1px rgba(0,0,0,0.06); }
.tm-mini-msg.in { background: #fff; align-self: flex-start; border-top-left-radius: 2px; }
.tm-mini-msg.out { background: #dcf8c6; align-self: flex-end; border-top-right-radius: 2px; }

/* AI tools */
.tm-feat-vis-ai { display: flex; flex-direction: column; gap: 6px; background: #14202c; }
.tm-ai-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: rgba(255,255,255,0.04);
    border-radius: 6px;
    color: rgba(255,255,255,0.85);
}
.tm-ai-tool { font-size: 14px; }
.tm-ai-row .tm-mono { font-size: 12px; color: var(--secondary); }
.tm-ai-result { font-family: var(--font-mono); font-size: 11px; color: rgba(255,255,255,0.7); }

/* URL mock */
.tm-feat-vis-url { padding: 0; }
.tm-url-mock {
    background: #fff;
    border-radius: var(--r-md);
    overflow: hidden;
    border: 1px solid var(--line);
}
.tm-url-mock .dots { display: flex; gap: 6px; padding: 10px 12px; background: var(--bg-warm); border-bottom: 1px solid var(--line); }
.tm-url-mock .dots i { width: 10px; height: 10px; border-radius: 50%; background: var(--line); }
.tm-url-mock .dots i:nth-child(1) { background: #ff5f57; }
.tm-url-mock .dots i:nth-child(2) { background: #ffbd2e; }
.tm-url-mock .dots i:nth-child(3) { background: #28ca42; }
.tm-url-mock .url { display: block; padding: 14px 16px; font-family: var(--font-mono); font-size: 13px; word-break: break-all; }
.tm-url-mock .url-pro { border-top: 1px solid var(--line); }
.tm-url-mock .url-tag { display: inline-block; margin-right: 8px; padding: 1px 7px; border-radius: 999px; font-family: var(--font-body); font-size: 10px; font-weight: 600; letter-spacing: .02em; background: var(--bg-warm); color: var(--ink-3); vertical-align: middle; }
.tm-url-mock .url-tag-pro { background: var(--primary); color: #fff; }
.tm-url-mock .proto { color: var(--ink-4); }
.tm-url-mock .slug { color: var(--primary-deep); font-weight: 600; }
.tm-url-mock .dom { color: var(--ink-2); }

/* Calendar mini */
.tm-feat-vis-cal { padding: 14px; }
.tm-mini-cal { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.tm-cal-cell {
    aspect-ratio: 1;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ink-3);
}
.tm-cal-cell.is-booked { background: var(--primary); color: #fff; border-color: var(--primary); }
.tm-cal-cell.is-pending { background: var(--accent-tint); color: var(--accent-deep); border-color: var(--accent); }

/* Price rows */
.tm-feat-vis-price { display: flex; flex-direction: column; gap: 6px; }
.tm-price-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #fff; border-radius: 6px; font-size: 13px; }
.tm-price-row.tm-up { background: var(--accent-tint); color: var(--accent-deep); }
.tm-price-row.tm-up-hi { background: linear-gradient(90deg, var(--accent-tint), #fff7e0); color: var(--accent-deep); font-weight: 600; }

/* PDF mini */
.tm-pdf-mini { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 14px; box-shadow: 0 6px 16px -8px rgba(0,0,0,0.1); }
.tm-pdf-mini .pdf-head { font-family: var(--font-mono); font-size: 10px; color: var(--ink-4); letter-spacing: 0.1em; padding-bottom: 8px; border-bottom: 1px dashed var(--line); margin-bottom: 8px; }
.tm-pdf-mini .pdf-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 12px; }
.tm-pdf-mini .pdf-row.sub { color: var(--ink-3); font-size: 11px; }
.tm-pdf-mini .pdf-row.total { font-weight: 600; padding-top: 8px; border-top: 1px solid var(--line); margin-top: 4px; color: var(--primary-deep); }

.tm-feat-more {
    margin-top: 32px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: center;
    color: var(--ink-3);
    font-size: 14px;
}
.tm-tag {
    padding: 6px 12px;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: var(--r-pill);
    font-size: 13px;
    color: var(--ink-2);
}
</style>

{{-- ============ HOW IT WORKS ============ --}}
<section class="tm-how" id="how">
    <div class="tm-container">
        <div class="tm-section-head">
            <span class="tm-kicker is-center">{{ $isMs ? 'BAGAIMANA IA BERFUNGSI' : 'HOW IT WORKS' }}</span>
            <h2 class="tm-h2 tm-center">{{ $isMs ? '5 minit untuk setup. Selamanya untuk grow.' : '5 minutes to set up. Forever to grow.' }}</h2>
        </div>

        <ol class="tm-steps">
            <li class="tm-step">
                <div class="tm-step-num">01</div>
                <div class="tm-step-body">
                    <h3 class="tm-h3">{{ $isMs ? 'Daftar dengan email' : 'Sign up with email' }}</h3>
                    <p>{{ $isMs ? 'Tiada kad kredit. Pilih nama untuk laman anda (cth: wafahomestay).' : 'No credit card. Pick a name for your page (e.g., wafahomestay).' }}</p>
                </div>
                <div class="tm-step-line"></div>
            </li>
            <li class="tm-step">
                <div class="tm-step-num">02</div>
                <div class="tm-step-body">
                    <h3 class="tm-h3">{{ $isMs ? 'Tambah homestay & gambar' : 'Add your property & photos' }}</h3>
                    <p>{{ $isMs ? 'Drag-and-drop gambar. Set harga setiap bilik. Pilih kemudahan (wifi, dapur, kolam).' : 'Drag-and-drop photos. Set rates per room. Pick amenities (wifi, kitchen, pool).' }}</p>
                </div>
                <div class="tm-step-line"></div>
            </li>
            <li class="tm-step">
                <div class="tm-step-num">03</div>
                <div class="tm-step-body">
                    <h3 class="tm-h3">{{ $isMs ? 'Sambung WhatsApp (QR scan)' : 'Connect WhatsApp (scan QR)' }}</h3>
                    <p>{{ $isMs ? 'Imbas QR sekali — pelanggan boleh terus tempah melalui WhatsApp anda yang sedia ada.' : 'Scan a QR once — guests now book through your existing WhatsApp number.' }}</p>
                </div>
                <div class="tm-step-line"></div>
            </li>
            <li class="tm-step">
                <div class="tm-step-num">04</div>
                <div class="tm-step-body">
                    <h3 class="tm-h3">{{ $isMs ? 'Kongsi link, mula terima tempahan' : 'Share your link, start taking bookings' }}</h3>
                    <p>{{ $isMs ? 'Post di Instagram, Facebook, atau profil WhatsApp. Pelanggan klik, pilih tarikh, bayar — terus.' : 'Post on Instagram, Facebook, or your WhatsApp profile. Guests click, pick dates, pay — direct.' }}</p>
                </div>
            </li>
        </ol>
    </div>
</section>

{{-- ============ MATH / COMPARISON ============ --}}
<section class="tm-math">
    <div class="tm-container">
        <div class="tm-section-head">
            <span class="tm-kicker is-center is-amber">{{ $isMs ? 'KIRA SENDIRI' : 'DO THE MATH' }}</span>
            <h2 class="tm-h2 tm-center">
                @if ($isMs)
                    Komisen Airbnb vs <em>Tempahlah Pro</em>.
                @else
                    Airbnb commission vs <em>Tempahlah Pro</em>.
                @endif
            </h2>
            <p class="tm-lead tm-center" style="margin-left:auto;margin-right:auto;">
                {{ $isMs ? '20 tempahan sebulan @ RM 300. Lihat sendiri.' : '20 bookings a month @ RM 300. See the difference.' }}
            </p>
        </div>

        <div class="tm-math-grid">
            <article class="tm-math-card tm-math-them">
                <header>
                    <span class="tm-math-label">{{ $isMs ? 'CARA LAMA' : 'THE OLD WAY' }}</span>
                    <h3 class="tm-h3">Airbnb / Booking.com</h3>
                </header>
                <dl class="tm-math-rows">
                    <div><dt>{{ $isMs ? 'Tempahan sebulan' : 'Monthly bookings' }}</dt><dd class="tm-mono">20</dd></div>
                    <div><dt>{{ $isMs ? 'Harga purata' : 'Average rate' }}</dt><dd class="tm-mono">RM 300</dd></div>
                    <div><dt>{{ $isMs ? 'Jumlah kasar' : 'Gross' }}</dt><dd class="tm-mono">RM 6,000</dd></div>
                    <div class="dash"><dt>{{ $isMs ? 'Komisen 15%' : 'Commission 15%' }}</dt><dd class="tm-mono tm-neg">− RM 900</dd></div>
                    <div class="dash"><dt>{{ $isMs ? 'Yuran payment 2.9%' : 'Payment fee 2.9%' }}</dt><dd class="tm-mono tm-neg">− RM 174</dd></div>
                </dl>
                <footer>
                    <span>{{ $isMs ? 'Anda dapat' : 'You keep' }}</span>
                    <span class="tm-math-total">RM 4,926</span>
                </footer>
            </article>

            <article class="tm-math-card tm-math-us">
                <div class="tm-math-badge">{{ $isMs ? 'PILIHAN BIJAK' : 'SMART CHOICE' }}</div>
                <header>
                    <span class="tm-math-label is-primary">{{ $isMs ? 'CARA TEMPAHLAH' : 'THE TEMPAHLAH WAY' }}</span>
                    <h3 class="tm-h3">Tempahlah Pro</h3>
                </header>
                <dl class="tm-math-rows">
                    <div><dt>{{ $isMs ? 'Tempahan sebulan' : 'Monthly bookings' }}</dt><dd class="tm-mono">20</dd></div>
                    <div><dt>{{ $isMs ? 'Harga purata' : 'Average rate' }}</dt><dd class="tm-mono">RM 300</dd></div>
                    <div><dt>{{ $isMs ? 'Jumlah kasar' : 'Gross' }}</dt><dd class="tm-mono">RM 6,000</dd></div>
                    <div class="dash"><dt>{{ $isMs ? 'Yuran Tempahlah' : 'Tempahlah fee' }}</dt><dd class="tm-mono tm-neg">− RM 49</dd></div>
                    <div class="dash"><dt>{{ $isMs ? 'Toyyibpay 1%' : 'Toyyibpay 1%' }}</dt><dd class="tm-mono tm-neg">− RM 60</dd></div>
                </dl>
                <footer>
                    <span>{{ $isMs ? 'Anda dapat' : 'You keep' }}</span>
                    <span class="tm-math-total is-up">RM 5,891</span>
                </footer>
                <div class="tm-math-savings">
                    <div>
                        <span class="tm-kicker is-amber" style="margin-bottom:4px;">{{ $isMs ? 'JIMAT' : 'SAVED' }}</span>
                        <strong class="tm-mono">+ RM 965 / {{ $isMs ? 'bulan' : 'mo' }}</strong>
                    </div>
                    <div>
                        <span class="tm-kicker is-amber" style="margin-bottom:4px;">{{ $isMs ? 'SETAHUN' : 'A YEAR' }}</span>
                        <strong class="tm-mono">+ RM 11,580</strong>
                    </div>
                </div>
            </article>
        </div>
    </div>
</section>

{{-- ============ TESTIMONIAL ============ --}}
<section class="tm-testimonial">
    <div class="tm-container">
        <div class="tm-testimonial-card">
            <svg class="tm-quote-mark" viewBox="0 0 60 48" aria-hidden="true">
                <path d="M0 48V28C0 14 8 4 22 0L24 8C16 11 12 17 12 24H22V48H0ZM36 48V28C36 14 44 4 58 0L60 8C52 11 48 17 48 24H58V48H36Z" fill="currentColor"/>
            </svg>
            <blockquote class="tm-quote">
                @if ($isMs)
                    Dulu saya bazir 2 jam sehari balas WhatsApp pelanggan tanya benda sama. <em>Sekarang AI buat semua</em> — saya boleh urus rumah, jaga anak, tidur cukup. Tempahan tak terlepas pun.
                @else
                    I used to waste 2 hours a day on WhatsApp answering the same questions. <em>Now the AI handles all of it</em> — I can run the house, look after the kids, sleep properly. And nothing slips through.
                @endif
            </blockquote>
            <footer class="tm-quote-attr">
                <div class="tm-quote-avatar">W</div>
                <div>
                    <strong>Wafa M.</strong>
                    <span>Wafa Homestay Kluang · {{ $isMs ? 'pengguna sejak' : 'a host since' }} 2026</span>
                </div>
                <div class="tm-quote-stars" aria-label="5 stars">★★★★★</div>
            </footer>
        </div>
    </div>
</section>

{{-- ============ PRICING ============ --}}
<section class="tm-pricing" id="pricing">
    <div class="tm-container">
        <div class="tm-section-head">
            <span class="tm-kicker is-center">{{ $isMs ? 'HARGA YANG ADIL' : 'FAIR PRICING' }}</span>
            <h2 class="tm-h2 tm-center">
                @if ($isMs)
                    Mula <em>percuma</em>.<br>Naik bila sedia.
                @else
                    Start <em>free</em>.<br>Upgrade when ready.
                @endif
            </h2>
            <p class="tm-lead tm-center" style="margin-left:auto;margin-right:auto;">
                {{ $isMs ? 'Bulanan sahaja. 0% komisen. Tiada kontrak — berhenti bila-bila.' : 'Monthly only. 0% commission. No contracts — cancel any time.' }}
            </p>
        </div>

        <div class="tm-price-grid">
            <article class="tm-pcard">
                <header>
                    <h3 class="tm-h3">{{ $isMs ? 'Percuma' : 'Free' }}</h3>
                    <p class="tm-pcard-sub">{{ $isMs ? 'Untuk satu homestay, baru mula.' : 'For a single property, just getting started.' }}</p>
                </header>
                <div class="tm-pcard-price">
                    <span class="rm">RM</span>
                    <span class="num">0</span>
                    <span class="per">/ {{ $isMs ? 'bulan' : 'mo' }}</span>
                </div>
                <a href="{{ route('register') }}" class="tm-btn tm-btn-ghost tm-btn-lg tm-btn-full">{{ $isMs ? 'Mula sekarang' : 'Start now' }}</a>
                <ul class="tm-pcard-list">
                    <li><span class="ck">✓</span> {{ $isMs ? '1 homestay, 4 bilik' : '1 property, 4 rooms' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? '20 tempahan / bulan' : '20 bookings / month' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Halaman tempahan sendiri (tempahlah.com/nama-anda)' : 'Your own booking page (tempahlah.com/your-name)' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Bayaran manual (bank transfer / tunai)' : 'Manual payment (bank transfer / cash)' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Penyenaraian marketplace' : 'Marketplace listing' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'WhatsApp click-to-chat' : 'WhatsApp click-to-chat' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Penilaian & blacklist' : 'Reviews & blacklist' }}</li>
                </ul>
            </article>

            <article class="tm-pcard tm-pcard-pro">
                <div class="tm-pcard-ribbon">
                    <span>★ {{ $isMs ? 'PALING POPULAR' : 'MOST POPULAR' }}</span>
                </div>
                <header>
                    <h3 class="tm-h3">Pro</h3>
                    <p class="tm-pcard-sub">{{ $isMs ? 'Untuk yang serius nak membesar.' : 'For hosts ready to scale.' }}</p>
                </header>
                <div class="tm-pcard-price">
                    <span class="rm">RM</span>
                    <span class="num">49</span>
                    <span class="per">/ {{ $isMs ? 'bulan' : 'mo' }}</span>
                </div>
                <a href="{{ route('register') }}" class="tm-btn tm-btn-primary tm-btn-lg tm-btn-full">
                    {{ $isMs ? 'Cuba 7 hari percuma' : 'Try 7 days free' }}
                    <span class="tm-arrow">→</span>
                </a>
                <p class="tm-pcard-fine">{{ $isMs ? '7 hari percuma · 0% komisen' : '7 days free · 0% commission' }}</p>
                <ul class="tm-pcard-list">
                    <li class="all"><strong>{{ $isMs ? 'Semua dalam Percuma, +' : 'Everything in Free, +' }}</strong></li>
                    <li><span class="ck">✓</span> {{ $isMs ? '3 homestay · bilik & tempahan tanpa had' : '3 properties · unlimited rooms & bookings' }}</li>
                    <li><span class="ck is-pro">★</span> {{ $isMs ? 'AI Agent WhatsApp 24/7' : 'AI WhatsApp Agent 24/7' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Gateway bayaran — SecurePay, Toyyibpay, Billplz' : 'Payment gateway — SecurePay, Toyyibpay, Billplz' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Subdomain sendiri (nama-anda.tempahlah.com)' : 'Your own subdomain (your-name.tempahlah.com)' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Google Calendar 2-arah' : 'Google Calendar 2-way' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Sync kalendar Airbnb & Booking.com' : 'Airbnb & Booking.com calendar sync' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Harga dinamik' : 'Dynamic pricing' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Invois & email berjenama' : 'Branded invoices & email' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Laporan & analitik' : 'Reports & analytics' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Keutamaan di marketplace' : 'Priority marketplace placement' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? '3 akaun pekerja' : '3 staff seats' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Sokongan WhatsApp keutamaan' : 'Priority WhatsApp support' }}</li>
                </ul>
            </article>

            <article class="tm-pcard">
                <header>
                    <h3 class="tm-h3">Ultra</h3>
                    <p class="tm-pcard-sub">{{ $isMs ? 'Untuk jenama berbilang homestay.' : 'For multi-property brands.' }}</p>
                </header>
                <div class="tm-pcard-price">
                    <span class="rm">RM</span>
                    <span class="num">89</span>
                    <span class="per">/ {{ $isMs ? 'bulan' : 'mo' }}</span>
                </div>
                <a href="{{ route('register') }}" class="tm-btn tm-btn-ghost tm-btn-lg tm-btn-full">
                    {{ $isMs ? 'Cuba 7 hari percuma' : 'Try 7 days free' }}
                </a>
                <p class="tm-pcard-fine">{{ $isMs ? '7 hari percuma · 0% komisen' : '7 days free · 0% commission' }}</p>
                <ul class="tm-pcard-list">
                    <li class="all"><strong>{{ $isMs ? 'Semua dalam Pro, +' : 'Everything in Pro, +' }}</strong></li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Homestay & pekerja tanpa had' : 'Unlimited properties & staff' }}</li>
                    <li><span class="ck is-pro">★</span> {{ $isMs ? 'White-label — tiada "Powered by Tempahlah"' : 'White-label — no "Powered by Tempahlah"' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Tempat teratas (featured) di marketplace' : 'Featured (top) marketplace placement' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Laporan lanjutan berbilang homestay' : 'Advanced multi-property reports' }}</li>
                    <li><span class="ck">✓</span> {{ $isMs ? 'Sokongan khas' : 'Dedicated support' }}</li>
                </ul>
            </article>
        </div>

        <div class="tm-pricing-foot">
            <p>
                {{ $isMs ? 'Sifar komisen — termasuk tempahan dari marketplace tempahlah.com. Setiap sen masuk terus ke akaun anda.' : 'Zero commission — including bookings from the tempahlah.com marketplace. Every ringgit lands in your own account.' }}
            </p>
        </div>
    </div>
</section>

{{-- ============ FAQ ============ --}}
<section class="tm-faq" id="faq">
    <div class="tm-container tm-container-narrow">
        <div class="tm-section-head">
            <span class="tm-kicker is-center">{{ $isMs ? 'SOALAN LAZIM' : 'COMMON QUESTIONS' }}</span>
            <h2 class="tm-h2 tm-center">{{ $isMs ? 'Soalan yang biasa ditanya.' : 'The things hosts usually ask.' }}</h2>
        </div>
        <div class="tm-faq-list">
            @php
                $faqs = $isMs ? [
                    ['Saya tak biasa pakai teknologi. Boleh ke?', 'Boleh. Setup ambil 5 minit. Jika anda boleh guna WhatsApp, anda boleh guna Tempahlah. Kami ada sokongan dalam Bahasa Melayu setiap hari.'],
                    ['Saya guna Airbnb sekarang. Boleh sambung sekali?', 'Boleh. Pelan Pro & Ultra sync 2-arah dengan Google Calendar serta iCal Airbnb & Booking.com — bila Airbnb tempah, slot di sini terus block. Tiada double-booking.'],
                    ['Bagaimana pelanggan bayar?', 'Pelan Percuma: bayaran manual (bank transfer / tunai) dengan arahan. Pelan Pro & Ultra: gateway anda sendiri — SecurePay, Toyyibpay atau Billplz — FPX, kad, e-wallet, semua auto. Duit terus masuk akaun bank anda, 0% komisen.'],
                    ['AI tu betul-betul faham Bahasa Melayu?', 'Ya. Ia jawab dalam bahasa pelanggan — BM, English, atau campur. Ia tahu konteks Malaysia (SST 8%, cuti sekolah, tourism tax untuk warga asing). Ia tidak pernah cipta harga atau tarikh palsu.'],
                    ['Berapa lama kontrak?', 'Tiada kontrak. Bayar bulanan, hentikan bila-bila masa. Data anda boleh export sebagai CSV pada bila-bila masa.'],
                    ['Adakah data saya selamat?', 'Ya. Hosting di Singapura (Digital Ocean), backup harian, semua maklumat sulit (bank account, MyKad) di-encrypt. Patuh PDPA Malaysia.'],
                    ['Ada percubaan?', 'Pro dan Ultra: 7 hari percuma. Batal bila-bila sebelum hari ke-7 — tiada caj. Jika tidak diteruskan, akaun turun ke pelan Percuma tanpa hilang data.'],
                ] : [
                    ['I\'m not techy. Can I still use this?', 'Yes. Setup takes 5 minutes. If you can use WhatsApp, you can use Tempahlah. We provide BM-language support every day.'],
                    ['I use Airbnb already. Can I connect both?', 'Yes. Pro & Ultra sync two-way with Google Calendar plus Airbnb & Booking.com iCal — when Airbnb books a date, it blocks here too. No double-bookings.'],
                    ['How do guests pay?', 'Free: manual payment (bank transfer / cash) with instructions. Pro & Ultra: your own gateway — SecurePay, Toyyibpay or Billplz — FPX, cards, e-wallets, all automatic. Funds settle directly into your bank account, 0% commission.'],
                    ['Does the AI really understand Bahasa Melayu?', 'Yes. It replies in the guest\'s language — BM, English, or a mix. It knows Malaysian context (SST 8%, school holidays, tourism tax for foreign guests). It never invents prices or dates.'],
                    ['How long is the contract?', 'No contracts. Pay monthly, cancel any time. Your data can be exported as CSV whenever you like.'],
                    ['Is my data safe?', 'Yes. Hosted in Singapore (Digital Ocean), daily backups, all sensitive data (bank account, MyKad) encrypted. Compliant with Malaysian PDPA.'],
                    ['Is there a free trial?', 'Pro and Ultra: 7 days free. Cancel any time before day 7 — no charge. If you don\'t continue, your account moves to the Free plan with no data loss.'],
                ];
            @endphp
            @foreach ($faqs as $i => $f)
                <details class="tm-faq-item" {{ $i === 0 ? 'open' : '' }}>
                    <summary>
                        <span>{{ $f[0] }}</span>
                        <span class="tm-faq-icon" aria-hidden="true">+</span>
                    </summary>
                    <p>{{ $f[1] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ FINAL CTA ============ --}}
<section class="tm-final">
    <div class="tm-container">
        <div class="tm-final-card">
            <div class="tm-final-bg"></div>
            <span class="tm-kicker is-center" style="color:rgba(255,255,255,0.7);">{{ $isMs ? 'SEDIA NAK MULA?' : 'READY TO START?' }}</span>
            <h2 class="tm-h2 tm-center" style="color:#fff;">
                @if ($isMs)
                    Daftar percuma.<br><em style="color:var(--accent)">5 minit saja.</em>
                @else
                    Sign up free.<br><em style="color:var(--accent)">Just 5 minutes.</em>
                @endif
            </h2>
            <p class="tm-lead tm-center" style="color:rgba(255,255,255,0.78);margin-left:auto;margin-right:auto;">
                {{ $isMs ? 'Tiada kad kredit. Tiada kontrak. Hentikan bila-bila masa.' : 'No credit card. No contract. Cancel any time.' }}
            </p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:8px;">
                <a href="{{ route('register') }}" class="tm-btn tm-btn-primary tm-btn-xl" style="background:linear-gradient(180deg, var(--accent), var(--accent-deep)); color:#14202c; box-shadow:0 12px 32px -8px rgba(232,185,74,0.5);">
                    {{ $isMs ? 'Mula sekarang' : 'Start now' }}
                    <span class="tm-arrow">→</span>
                </a>
                <a href="https://wa.me/60183426077" class="tm-btn tm-btn-ghost tm-btn-xl" style="border-color:rgba(255,255,255,0.25);color:#fff;">
                    {{ $isMs ? 'Tanya kami di WhatsApp' : 'Ask us on WhatsApp' }}
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ============ FOOTER ============ --}}
<footer class="tm-footer">
    <div class="tm-container">
        <div class="tm-footer-grid">
            <div class="tm-footer-brand">
                <a class="tm-brand" href="{{ route('hosts') }}">
                    <img src="/icons/logo.svg" alt="">
                    <span>tempahlah</span>
                </a>
                <p class="tm-footer-tagline">
                    {{ $isMs ? 'SaaS pengurusan homestay buatan Malaysia. Direka untuk tuan rumah kampung dan butik.' : 'A Malaysian homestay management SaaS. Built for kampung and boutique hosts alike.' }}
                </p>
                <div class="tm-footer-social">
                    <a href="https://wa.me/60183426077" aria-label="WhatsApp">WhatsApp</a>
                    <a href="https://instagram.com/tempahlah" aria-label="Instagram">Instagram</a>
                    <a href="https://facebook.com/tempahlah" aria-label="Facebook">Facebook</a>
                </div>
            </div>
            <div class="tm-footer-col">
                <h4>{{ $isMs ? 'Produk' : 'Product' }}</h4>
                <ul>
                    <li><a href="#features">{{ $isMs ? 'Ciri-ciri' : 'Features' }}</a></li>
                    <li><a href="#pricing">{{ $isMs ? 'Harga' : 'Pricing' }}</a></li>
                    <li><a href="#how">{{ $isMs ? 'Bagaimana ia berfungsi' : 'How it works' }}</a></li>
                    <li><a href="{{ url('/marketplace') }}">{{ $isMs ? 'Marketplace' : 'Marketplace' }}</a></li>
                </ul>
            </div>
            <div class="tm-footer-col">
                <h4>{{ $isMs ? 'Syarikat' : 'Company' }}</h4>
                <ul>
                    <li><a href="#">{{ $isMs ? 'Tentang kami' : 'About' }}</a></li>
                    <li><a href="#">{{ $isMs ? 'Blog' : 'Blog' }}</a></li>
                    <li><a href="mailto:drhidayatmat@gmail.com">drhidayatmat@gmail.com</a></li>
                </ul>
            </div>
            <div class="tm-footer-col">
                <h4>{{ $isMs ? 'Sah' : 'Legal' }}</h4>
                <ul>
                    <li><a href="#">{{ $isMs ? 'Terma Perkhidmatan' : 'Terms of Service' }}</a></li>
                    <li><a href="#">{{ $isMs ? 'Polisi Privasi' : 'Privacy Policy' }}</a></li>
                    <li><a href="#">PDPA</a></li>
                </ul>
            </div>
        </div>
        <div class="tm-footer-bottom">
            <p class="tm-mono">© 2026 Tempahlah Sdn Bhd · {{ $isMs ? 'Dibuat dengan ❤ di Malaysia' : 'Crafted with ❤ in Malaysia' }}</p>
            <div class="tm-locale" role="group">
                <a href="{{ route('locale.switch', 'ms') }}" class="{{ $isMs ? 'is-active' : '' }}">MS</a>
                <a href="{{ route('locale.switch', 'en') }}" class="{{ !$isMs ? 'is-active' : '' }}">EN</a>
            </div>
        </div>
    </div>
</footer>

<style>
/* ========== HOW IT WORKS ========== */
.tm-how { padding: 112px 0; background: var(--bg); }
.tm-steps {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: 1fr;
    gap: 32px;
    max-width: 760px;
    margin-left: auto;
    margin-right: auto;
    counter-reset: step;
}
.tm-step {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 24px;
    align-items: start;
    position: relative;
    padding-bottom: 8px;
}
.tm-step-num {
    font-family: var(--font-display);
    font-style: italic;
    font-weight: 500;
    font-size: 36px;
    line-height: 1;
    color: var(--primary);
    background: var(--primary-tint);
    width: 72px; height: 72px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}
.tm-step-body { padding-top: 18px; }
.tm-step-body p { color: var(--ink-2); margin: 6px 0 0; font-size: 15px; }
.tm-step-line {
    position: absolute;
    top: 72px;
    left: 36px;
    bottom: -16px;
    width: 2px;
    background: linear-gradient(180deg, var(--primary-tint), transparent);
    z-index: 1;
}

/* ========== MATH ========== */
.tm-math { padding: 112px 0; background: linear-gradient(180deg, var(--bg) 0%, var(--bg-warm) 100%); }
.tm-math-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    max-width: 960px;
    margin: 0 auto;
}
@media (min-width: 840px) { .tm-math-grid { grid-template-columns: 1fr 1fr; gap: 24px; } }
.tm-math-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: var(--r-2xl);
    padding: 32px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: relative;
}
.tm-math-them { background: #fff; }
.tm-math-them .tm-math-total { color: var(--ink-3); text-decoration: line-through; text-decoration-color: var(--danger); text-decoration-thickness: 2px; }
.tm-math-us {
    background: linear-gradient(180deg, #fff 0%, var(--primary-soft) 100%);
    border-color: var(--primary);
    box-shadow: var(--sh-glow);
    transform: translateY(-4px);
}
.tm-math-badge {
    position: absolute;
    top: -12px; left: 32px;
    background: linear-gradient(135deg, var(--accent), var(--accent-deep));
    color: #14202c;
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: var(--r-pill);
    letter-spacing: 0.12em;
    box-shadow: 0 6px 16px -4px rgba(232,185,74,0.4);
}
.tm-math-label { font-family: var(--font-mono); font-size: 11px; color: var(--ink-3); letter-spacing: 0.12em; }
.tm-math-label.is-primary { color: var(--primary-deep); }
.tm-math-rows { margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0; }
.tm-math-rows > div {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 14px 0;
    border-bottom: 1px solid var(--line);
}
.tm-math-rows dt { margin: 0; color: var(--ink-2); font-size: 14px; }
.tm-math-rows dd { margin: 0; font-size: 16px; font-weight: 500; color: var(--ink); font-variant-numeric: tabular-nums; }
.tm-math-rows .tm-neg { color: var(--danger); }
.tm-math-rows .dash dd { color: var(--ink-2); }
.tm-math-card footer {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding-top: 16px;
    border-top: 2px solid var(--ink);
    margin-top: 4px;
}
.tm-math-card footer span:first-child { font-size: 14px; color: var(--ink-2); font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.08em; }
.tm-math-total {
    font-family: var(--font-display);
    font-size: 36px;
    font-weight: 500;
    color: var(--ink);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}
.tm-math-total.is-up { color: var(--primary-deep); }
.tm-math-savings {
    background: var(--accent-tint);
    border-radius: var(--r-lg);
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-top: 4px;
}
.tm-math-savings > div { display: flex; flex-direction: column; gap: 4px; }
.tm-math-savings strong { font-size: 18px; font-weight: 600; color: var(--accent-deep); }

/* ========== TESTIMONIAL ========== */
.tm-testimonial { padding: 96px 0; background: var(--bg-warm); }
.tm-testimonial-card {
    max-width: 820px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: var(--r-2xl);
    padding: 56px 48px;
    box-shadow: var(--sh-md);
    position: relative;
    overflow: hidden;
}
.tm-testimonial-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(50% 50% at 100% 0%, var(--accent-tint) 0%, transparent 50%);
    opacity: 0.5;
    pointer-events: none;
}
.tm-quote-mark {
    width: 48px; height: 38px;
    color: var(--primary-tint);
    margin-bottom: 20px;
    position: relative;
}
.tm-quote {
    font-family: var(--font-display);
    font-size: clamp(22px, 2.6vw, 30px);
    line-height: 1.35;
    font-weight: 400;
    letter-spacing: -0.01em;
    color: var(--ink);
    margin: 0;
    position: relative;
}
.tm-quote em { font-style: italic; color: var(--primary-deep); }
.tm-quote-attr {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--line);
    position: relative;
}
.tm-quote-avatar {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 500;
}
.tm-quote-attr > div:nth-child(2) { display: flex; flex-direction: column; gap: 2px; flex: 1; }
.tm-quote-attr strong { font-size: 16px; color: var(--ink); }
.tm-quote-attr span { font-family: var(--font-mono); font-size: 12px; color: var(--ink-3); }
.tm-quote-stars { color: var(--accent); font-size: 14px; letter-spacing: 2px; }

/* ========== PRICING ========== */
.tm-pricing { padding: 112px 0; background: var(--bg); }
.tm-price-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
    max-width: 920px;
    margin: 0 auto;
}
@media (min-width: 840px) { .tm-price-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: start; max-width: 1100px; } }
.tm-pcard {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: var(--r-2xl);
    padding: 36px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: relative;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.tm-pcard:hover { transform: translateY(-2px); box-shadow: var(--sh-md); }
.tm-pcard-pro {
    background: linear-gradient(180deg, #14202c 0%, #1a2c3c 100%);
    color: #fff;
    border-color: transparent;
    box-shadow: var(--sh-xl);
    transform: translateY(-8px) scale(1.02);
}
@media (max-width: 839px) { .tm-pcard-pro { transform: none; } }
.tm-pcard-pro h3 { color: #fff; }
.tm-pcard-pro .tm-pcard-sub { color: rgba(255,255,255,0.7); }
.tm-pcard-pro .tm-pcard-price { color: #fff; }
.tm-pcard-pro .tm-pcard-price .rm, .tm-pcard-pro .tm-pcard-price .per { color: rgba(255,255,255,0.6); }
.tm-pcard-pro .tm-pcard-list { color: rgba(255,255,255,0.92); }
.tm-pcard-pro .tm-pcard-list li.all { color: var(--accent); }
.tm-pcard-pro .tm-pcard-list .ck { background: rgba(255,255,255,0.1); color: var(--secondary); }
.tm-pcard-pro .tm-pcard-list .ck.is-pro { background: linear-gradient(135deg, var(--accent), var(--accent-deep)); color: #14202c; }
.tm-pcard-pro .tm-pcard-fine { color: rgba(255,255,255,0.55); }
.tm-pcard-ribbon {
    position: absolute;
    top: -14px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, var(--accent), var(--accent-deep));
    color: #14202c;
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 700;
    padding: 6px 16px;
    border-radius: var(--r-pill);
    letter-spacing: 0.14em;
    white-space: nowrap;
    box-shadow: 0 8px 20px -4px rgba(232,185,74,0.45);
}
.tm-pcard-sub { color: var(--ink-3); margin: 4px 0 0; font-size: 14px; }
.tm-pcard-price {
    display: flex;
    align-items: baseline;
    gap: 4px;
    color: var(--ink);
}
.tm-pcard-price .rm { font-family: var(--font-mono); font-size: 18px; color: var(--ink-3); }
.tm-pcard-price .num { font-family: var(--font-display); font-size: 56px; font-weight: 500; letter-spacing: -0.03em; line-height: 1; }
.tm-pcard-price .per { font-family: var(--font-mono); font-size: 14px; color: var(--ink-3); margin-left: 4px; }
.tm-pcard-fine { font-family: var(--font-mono); font-size: 11px; color: var(--ink-3); text-align: center; margin: -8px 0 0; }
.tm-pcard-list { list-style: none; padding: 0; margin: 12px 0 0; display: flex; flex-direction: column; gap: 12px; font-size: 14.5px; }
.tm-pcard-list li { display: flex; align-items: flex-start; gap: 10px; line-height: 1.4; }
.tm-pcard-list li.all { font-size: 13px; font-family: var(--font-mono); color: var(--ink-3); text-transform: uppercase; letter-spacing: 0.06em; padding-bottom: 4px; border-bottom: 1px dashed var(--line); }
.tm-pcard-list .ck {
    flex-shrink: 0;
    width: 20px; height: 20px;
    background: var(--primary-tint);
    color: var(--primary-deep);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px;
    font-weight: 700;
    margin-top: 1px;
}
.tm-pcard-list .ck.is-pro { background: linear-gradient(135deg, var(--accent), var(--accent-deep)); color: #fff; }
.tm-pricing-foot { max-width: 720px; margin: 32px auto 0; text-align: center; color: var(--ink-3); font-size: 13px; font-family: var(--font-mono); padding: 0 24px; }

/* ========== FAQ ========== */
.tm-faq { padding: 112px 0; background: var(--bg-warm); }
.tm-faq-list { display: flex; flex-direction: column; gap: 0; margin-top: 16px; border-top: 1px solid var(--line); }
.tm-faq-item { border-bottom: 1px solid var(--line); }
.tm-faq-item summary {
    list-style: none;
    cursor: pointer;
    padding: 24px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-family: var(--font-display);
    font-size: 19px;
    font-weight: 500;
    color: var(--ink);
    letter-spacing: -0.01em;
    transition: color 0.15s ease;
}
.tm-faq-item summary::-webkit-details-marker { display: none; }
.tm-faq-item summary:hover { color: var(--primary-deep); }
.tm-faq-icon {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--bg-warm);
    color: var(--ink-2);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-mono);
    font-size: 18px;
    flex-shrink: 0;
    transition: transform 0.25s ease, background 0.15s ease;
}
.tm-faq-item[open] .tm-faq-icon { transform: rotate(45deg); background: var(--primary); color: #fff; }
.tm-faq-item p {
    margin: 0;
    padding: 0 56px 24px 0;
    color: var(--ink-2);
    font-size: 15.5px;
    line-height: 1.6;
}

/* ========== FINAL CTA ========== */
.tm-final { padding: 96px 0; background: var(--bg); }
.tm-final-card {
    background: #14202c;
    border-radius: var(--r-2xl);
    padding: 80px 32px;
    text-align: center;
    position: relative;
    overflow: hidden;
    isolation: isolate;
    display: flex; flex-direction: column; gap: 16px; align-items: center;
}
.tm-final-bg {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(60% 50% at 20% 30%, rgba(37,150,198,0.4) 0%, transparent 60%),
        radial-gradient(50% 60% at 80% 70%, rgba(232,185,74,0.2) 0%, transparent 60%);
    z-index: -1;
}
.tm-final-card .tm-h2 em { background: none; }

/* ========== FOOTER ========== */
.tm-footer { padding: 80px 0 40px; background: var(--bg-warm); border-top: 1px solid var(--line); }
.tm-footer-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 40px;
    margin-bottom: 48px;
}
@media (min-width: 640px) { .tm-footer-grid { grid-template-columns: 2fr 1fr 1fr 1fr; } }
.tm-footer-brand { display: flex; flex-direction: column; gap: 16px; max-width: 360px; }
.tm-footer-tagline { color: var(--ink-3); font-size: 14px; margin: 0; line-height: 1.6; }
.tm-footer-social { display: flex; gap: 16px; margin-top: 8px; }
.tm-footer-social a { font-family: var(--font-mono); font-size: 12px; color: var(--ink-2); transition: color 0.15s ease; }
.tm-footer-social a:hover { color: var(--primary-deep); }
.tm-footer-col h4 { font-family: var(--font-mono); font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-3); margin: 0 0 16px; }
.tm-footer-col ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.tm-footer-col a { font-size: 14px; color: var(--ink-2); transition: color 0.15s ease; }
.tm-footer-col a:hover { color: var(--primary-deep); }
.tm-footer-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding-top: 32px;
    border-top: 1px solid var(--line);
}
.tm-footer-bottom p { margin: 0; font-size: 12px; color: var(--ink-3); }
</style>

<script>
    // Sticky nav border-on-scroll
    (function() {
        var nav = document.getElementById('tmNav');
        if (!nav) return;
        var onScroll = function() {
            if (window.scrollY > 8) nav.classList.add('is-scrolled');
            else nav.classList.remove('is-scrolled');
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    })();

    // Smooth scroll for anchor links (extra nudge offset for sticky nav)
    document.querySelectorAll('a[href^="#"]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var id = a.getAttribute('href');
            if (id.length < 2) return;
            var el = document.querySelector(id);
            if (!el) return;
            e.preventDefault();
            var y = el.getBoundingClientRect().top + window.scrollY - 72;
            window.scrollTo({ top: y, behavior: 'smooth' });
        });
    });
</script>

</body>
</html>
