{{--
    Onboarding — "Name your homestay". Shown to an authenticated but
    tenant-less host (chiefly Google sign-ups). One required field: the
    business name guests will see. Nothing is auto-generated.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Name your homestay') }} · {{ config('app.name', 'Tempahlah') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600&family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <meta name="theme-color" content="#0e2a3a">
    @include('partials.pwa')

    @php $loc = app()->getLocale(); $isBM = $loc === 'ms'; @endphp

    <style>
        :root {
            --ink-deep: #0e2a3a; --teal: #2596c6; --brass: #e8b94a; --brass-soft: #f4d68a;
            --cream: #fafaf7; --paper: #fdfcf8; --ink: #1a1614; --ink-2: #4a4540; --ink-3: #8a857f;
            --line: rgba(26,22,20,0.08); --line-2: rgba(26,22,20,0.14); --err: #c8554a; --ok: #2cb88a;
        }
        * { box-sizing: border-box; -webkit-font-smoothing: antialiased; }
        html, body { margin: 0; padding: 0; min-height: 100%; }
        body {
            font-family: 'Geist', system-ui, sans-serif; color: var(--ink);
            min-height: 100dvh; display: grid; place-items: center; padding: 24px;
            background:
                radial-gradient(900px 700px at 85% 10%, rgba(232,185,74,0.10) 0%, transparent 55%),
                radial-gradient(900px 700px at 10% 100%, rgba(44,184,196,0.14) 0%, transparent 55%),
                var(--cream);
        }
        .card {
            width: 100%; max-width: 460px; background: var(--paper);
            border: 1px solid var(--line); border-radius: 18px;
            padding: 40px 38px; box-shadow: 0 24px 60px -24px rgba(14,42,58,0.28);
        }
        @media (max-width: 520px) { .card { padding: 30px 22px; } }
        .brand { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 26px; }
        .brand-mark { width: 34px; height: 34px; background: linear-gradient(155deg, #1c5b7d, #0e2a3a); border-radius: 9px; padding: 5px; display: grid; place-items: center; }
        .brand-mark img { width: 100%; height: 100%; display: block; }
        .brand-name { font-family: 'Fraunces', serif; font-style: italic; font-weight: 500; font-size: 19px; letter-spacing: -0.01em; }
        .kicker { font-family: 'Geist Mono', monospace; font-size: 10.5px; letter-spacing: 0.22em; text-transform: uppercase; color: var(--teal); margin-bottom: 10px; }
        .title { font-family: 'Fraunces', serif; font-style: italic; font-weight: 500; font-size: 30px; line-height: 1.1; letter-spacing: -0.02em; margin: 0; }
        .sub { font-size: 14px; color: var(--ink-3); margin: 12px 0 26px; line-height: 1.55; }
        .field { margin-bottom: 18px; }
        .field-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink-2); margin-bottom: 6px; }
        .field-input {
            display: block; width: 100%; padding: 12px 14px; border: 1.5px solid var(--line-2);
            border-radius: 9px; background: #fff; font-family: 'Geist', sans-serif; font-size: 16px; /* >=16px stops mobile browsers auto-zooming on focus */
            font-weight: 500; color: var(--ink); outline: none; touch-action: manipulation; transition: border-color .15s, box-shadow .15s;
        }
        .field-input::placeholder { color: var(--ink-3); font-weight: 400; }
        .field-input:focus { border-color: var(--teal); box-shadow: 0 0 0 3px color-mix(in srgb, var(--teal) 14%, transparent); }
        .field-err { display: block; font-size: 12px; color: var(--err); margin-top: 6px; }
        .field-hint { display: block; font-size: 11.5px; color: var(--ink-3); margin-top: 6px; }
        .cta {
            width: 100%; padding: 15px 22px; border: 0; border-radius: 10px; margin-top: 6px;
            background: linear-gradient(160deg, #2596c6 0%, #1a6a96 100%); color: #fff;
            font-family: 'Geist', sans-serif; font-size: 14.5px; font-weight: 600; letter-spacing: 0.02em;
            cursor: pointer; box-shadow: inset 0 1px 0 rgba(255,255,255,0.18), 0 8px 18px -6px rgba(37,150,198,0.45);
            transition: transform .2s, box-shadow .2s; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
        }
        .cta:hover { transform: translateY(-1.5px); }
        .cta:active { transform: translateY(0); }
        .foot { margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .foot-user { font-size: 12px; color: var(--ink-3); }
        .foot a { font-size: 12px; color: var(--ink-3); text-decoration: none; border-bottom: 1px solid transparent; }
        .foot a:hover { border-bottom-color: var(--ink-3); }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <span class="brand-mark"><img src="{{ asset('icons/logo.svg') }}" alt=""></span>
            <span class="brand-name">Tempahlah</span>
        </div>

        <div class="kicker">{{ $isBM ? 'Langkah terakhir' : 'One last step' }}</div>
        <h1 class="title">{{ $isBM ? 'Namakan homestay anda.' : 'Name your homestay.' }}</h1>
        <p class="sub">
            {{ $isBM
                ? 'Inilah nama yang tetamu akan nampak pada halaman tempahan anda. Anda boleh ubah bila-bila masa dalam Tetapan.'
                : 'This is the name guests will see on your booking page. You can change it any time in Settings.' }}
        </p>

        <form method="POST" action="{{ route('onboarding.homestay.store') }}" novalidate>
            @csrf

            <div class="field">
                <label for="business_name" class="field-label">{{ $isBM ? 'Nama homestay' : 'Homestay name' }}</label>
                <input id="business_name" name="business_name" type="text" required autofocus
                       value="{{ old('business_name') }}" class="field-input"
                       placeholder="{{ $isBM ? 'Wafa Homestay Kluang' : 'Aisha\'s Beach Retreat' }}">
                @error('business_name') <span class="field-err">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="phone" class="field-label">{{ $isBM ? 'Telefon (WhatsApp) — pilihan' : 'Phone (WhatsApp) — optional' }}</label>
                <input id="phone" name="phone" type="tel" autocomplete="tel"
                       value="{{ old('phone') }}" class="field-input" placeholder="+60123456789" data-phone-input>
                @error('phone') <span class="field-err">{{ $message }}</span> @enderror
                <span class="field-hint">{{ $isBM ? 'Digunakan untuk pautan WhatsApp tetamu.' : 'Used for guest WhatsApp links.' }}</span>
            </div>

            <button type="submit" class="cta">
                {{ $isBM ? 'Buka homestay saya' : 'Open my homestay' }}
                <span aria-hidden="true">→</span>
            </button>
        </form>

        <div class="foot">
            <span class="foot-user">{{ auth()->user()?->email }}</span>
            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('onboard-logout').submit();">
                {{ $isBM ? 'Log keluar' : 'Sign out' }}
            </a>
        </div>
        <form id="onboard-logout" method="POST" action="{{ route('logout') }}" style="display:none;">@csrf</form>
    </main>

    @include('partials.phone-input')
</body>
</html>
