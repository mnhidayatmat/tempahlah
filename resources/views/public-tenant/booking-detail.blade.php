<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>{{ __('Booking') }} {{ $booking->reference }} — {{ $tenant->business_name }}</title>
    {{-- Magic-link page — don't index in search engines (links contain a
         signature that lets anyone with the URL view PII). --}}
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style id="tenant-theme">:root { {!! $tenant->themeCssVariables() !!} }</style>
    <meta name="theme-color" content="{{ $tenant->themePrimary() }}">
    @php
        $isBM   = app()->getLocale() === 'ms';
        $status = $booking->status;
        // Status pill copy + tone — maps onto our own status constants.
        $statusMap = [
            'pending'      => ['bm' => 'Menunggu deposit', 'en' => 'Awaiting deposit', 'tone' => 'warn'],
            'confirmed'    => ['bm' => 'Disahkan',         'en' => 'Confirmed',        'tone' => 'ok'],
            'checked_in'   => ['bm' => 'Daftar masuk',     'en' => 'Checked in',       'tone' => 'ok'],
            'checked_out'  => ['bm' => 'Selesai',          'en' => 'Completed',        'tone' => 'muted'],
            'cancelled'    => ['bm' => 'Dibatalkan',       'en' => 'Cancelled',        'tone' => 'err'],
            'no_show'      => ['bm' => 'Tidak hadir',      'en' => 'No-show',          'tone' => 'err'],
        ];
        $st = $statusMap[$status] ?? $statusMap['pending'];
        $statusLabel = $isBM ? $st['bm'] : $st['en'];
        $address = collect([
            $booking->property->address_line1,
            $booking->property->address_line2,
            $booking->property->postcode ? trim(($booking->property->postcode ?? '').' '.($booking->property->city ?? '')) : $booking->property->city,
            $booking->property->state,
        ])->filter()->implode(', ');
        $contactPhoneClean = $contactPhone ?: '';
    @endphp
</head>
<body class="bd-body">
    <main class="bd-card">
        <a href="{{ route('tenant-public.home', ['tenant_slug' => $tenant->slug]) }}" class="bd-brand">
            <span class="bd-brand-mark">{{ mb_strtoupper(mb_substr($tenant->business_name, 0, 1)) }}</span>
            <span class="bd-brand-name">{{ $tenant->business_name }}</span>
        </a>

        <div class="bd-status bd-status-{{ $st['tone'] }}">
            <span class="bd-status-dot"></span>
            {{ $statusLabel }}
        </div>

        <h1 class="bd-title">
            {{ $isBM ? 'Tempahan anda' : 'Your booking' }}
            <span class="bd-ref">{{ $booking->reference }}</span>
        </h1>

        @if($leadGuest)
            <p class="bd-sub">
                {{ $isBM ? 'Salam' : 'Hi' }} <strong>{{ $leadGuest->full_name }}</strong> —
                {{ $isBM
                    ? 'di bawah adalah butiran tempahan anda. Simpan halaman ini untuk rujukan.'
                    : 'here are your booking details. Save this page for your records.' }}
            </p>
        @endif

        {{-- ── Summary card ───────────────────────────────────────── --}}
        <div class="bd-summary">
            <div class="bd-row">
                <span class="lbl">{{ $isBM ? 'Penginapan' : 'Property' }}</span>
                <span class="val">{{ $booking->property->name }}</span>
            </div>
            @if($booking->room && $booking->room->name)
                <div class="bd-row">
                    <span class="lbl">{{ $isBM ? 'Bilik' : 'Room' }}</span>
                    <span class="val">{{ $booking->room->name }}</span>
                </div>
            @endif
            <div class="bd-row">
                <span class="lbl">{{ $isBM ? 'Daftar masuk' : 'Check-in' }}</span>
                <span class="val">{{ $booking->check_in->format('D, d M Y') }} · {{ \Illuminate\Support\Str::limit($booking->property->check_in_time, 5, '') }}</span>
            </div>
            <div class="bd-row">
                <span class="lbl">{{ $isBM ? 'Daftar keluar' : 'Check-out' }}</span>
                <span class="val">{{ $booking->check_out->format('D, d M Y') }} · {{ \Illuminate\Support\Str::limit($booking->property->check_out_time, 5, '') }}</span>
            </div>
            <div class="bd-row">
                <span class="lbl">{{ $isBM ? 'Malam' : 'Nights' }}</span>
                <span class="val">{{ $booking->nights }}</span>
            </div>
            <div class="bd-row">
                <span class="lbl">{{ $isBM ? 'Tetamu' : 'Guests' }}</span>
                <span class="val">{{ $booking->adults }}{{ $booking->children ? ' + '.$booking->children.' '.($isBM ? 'kanak-kanak' : 'kids') : '' }}</span>
            </div>
        </div>

        {{-- ── Payment card ───────────────────────────────────────── --}}
        <div class="bd-summary bd-payment">
            <div class="bd-row">
                <span class="lbl">{{ $isBM ? 'Jumlah' : 'Total' }}</span>
                <span class="val">RM {{ number_format($booking->total_amount, 2) }}</span>
            </div>
            @if($booking->deposit_paid_at)
                <div class="bd-row bd-row-ok">
                    <span class="lbl">{{ $isBM ? 'Deposit dibayar' : 'Deposit paid' }}</span>
                    <span class="val">RM {{ number_format($booking->deposit_amount, 2) }} ✓</span>
                </div>
            @elseif($booking->deposit_amount > 0)
                <div class="bd-row">
                    <span class="lbl">{{ $isBM ? 'Deposit' : 'Deposit' }}</span>
                    <span class="val">RM {{ number_format($booking->deposit_amount, 2) }}</span>
                </div>
            @endif
            @if($balanceDue > 0.01)
                <div class="bd-row bd-row-total">
                    <span class="lbl">{{ $isBM ? 'Baki perlu dibayar' : 'Balance due' }}</span>
                    <span class="val">RM {{ number_format($balanceDue, 2) }}</span>
                </div>
            @else
                <div class="bd-row bd-row-ok bd-row-total">
                    <span class="lbl">{{ $isBM ? 'Telah dibayar penuh' : 'Fully paid' }}</span>
                    <span class="val">✓</span>
                </div>
            @endif
        </div>

        @if($openPayUrl && $balanceDue > 0.01)
            <a href="{{ $openPayUrl }}" class="bd-cta">
                {{ $isBM ? 'Bayar baki' : 'Pay balance' }} — RM {{ number_format($balanceDue, 2) }} →
            </a>
            <p class="bd-fine">
                {{ $isBM
                    ? 'Anda akan dialihkan ke Toyyibpay. FPX, kad kredit/debit & DuitNow QR diterima.'
                    : 'You\'ll be redirected to Toyyibpay. FPX, credit/debit cards & DuitNow QR accepted.' }}
            </p>
        @endif

        @if($address)
            <div class="bd-block">
                <div class="bd-block-eyebrow">{{ $isBM ? 'Alamat' : 'Address' }}</div>
                <p class="bd-block-text">{{ $address }}</p>
                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($booking->property->name.', '.$address) }}"
                   target="_blank" rel="noopener" class="bd-block-link">
                    {{ $isBM ? 'Buka di Google Maps' : 'Open in Google Maps' }} →
                </a>
            </div>
        @endif

        @if($booking->special_requests)
            <div class="bd-block">
                <div class="bd-block-eyebrow">{{ $isBM ? 'Permintaan khas' : 'Special requests' }}</div>
                <p class="bd-block-text">{{ $booking->special_requests }}</p>
            </div>
        @endif

        @if($contactPhoneClean)
            <a href="https://wa.me/{{ $contactPhoneClean }}?text={{ rawurlencode(($isBM ? 'Salam ' : 'Hi ').$tenant->business_name.' — '.($isBM ? 'rujukan tempahan ' : 'booking ref ').$booking->reference) }}"
               target="_blank" rel="noopener" class="bd-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.8-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4 0 1.4 1 2.8 1.2 3 .1.2 2.1 3.2 5.1 4.4.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.1-.3-.2-.6-.3z"/><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2z"/></svg>
                {{ $isBM ? 'Hubungi tuan rumah di WhatsApp' : 'Message host on WhatsApp' }}
            </a>
        @endif

        <a href="{{ route('tenant-public.home', ['tenant_slug' => $tenant->slug]) }}" class="bd-back">
            ← {{ $isBM ? 'Kembali ke '.$tenant->business_name : 'Back to '.$tenant->business_name }}
        </a>

        <p class="bd-foot">
            {{ $isBM ? 'Pautan ini hanya untuk anda — jangan kongsi.' : 'This link is for you only — please don\'t share it.' }}
        </p>
    </main>

    <style>
        .bd-body {
            margin: 0;
            font-family: var(--font-sans);
            background:
                radial-gradient(900px 600px at 50% -20%, color-mix(in srgb, var(--primary) 8%, transparent), transparent 65%),
                var(--bg);
            color: var(--ink);
            min-height: 100dvh;
            display: flex; align-items: flex-start; justify-content: center;
            padding: 24px 16px 40px;
            -webkit-font-smoothing: antialiased;
        }
        .bd-card {
            width: 100%;
            max-width: 520px;
            background: var(--bg-elev);
            border-radius: 22px;
            border: 1px solid var(--line);
            box-shadow: 0 30px 80px -20px rgba(40,30,10,0.18);
            padding: 26px 22px;
        }
        .bd-brand {
            display: inline-flex; align-items: center; gap: 10px;
            text-decoration: none;
            color: var(--ink);
            margin-bottom: 18px;
        }
        .bd-brand-mark {
            width: 32px; height: 32px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-deep));
            color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700;
        }
        .bd-brand-name {
            font-weight: 600;
            font-size: 14.5px;
        }
        .bd-status {
            display: inline-flex; align-items: center; gap: 7px;
            font-family: var(--font-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 5px 11px;
            border-radius: 999px;
            margin-bottom: 14px;
        }
        .bd-status-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: currentColor;
            display: inline-block;
        }
        .bd-status-ok    { background: color-mix(in srgb, var(--ok) 12%, transparent);      color: var(--ok); }
        .bd-status-warn  { background: color-mix(in srgb, var(--warn) 14%, transparent);    color: var(--warn); }
        .bd-status-err   { background: color-mix(in srgb, var(--err) 12%, transparent);     color: var(--err); }
        .bd-status-muted { background: color-mix(in srgb, var(--ink-3) 12%, transparent);   color: var(--ink-3); }
        .bd-title {
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 6px;
            line-height: 1.2;
            display: flex; flex-wrap: wrap; align-items: baseline; gap: 10px;
        }
        .bd-ref {
            font-family: var(--font-mono);
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-3);
            background: var(--bg-sunk);
            padding: 3px 8px;
            border-radius: 6px;
        }
        .bd-sub {
            font-size: 14px;
            line-height: 1.55;
            color: var(--ink-2);
            margin: 0 0 20px;
        }
        .bd-sub strong { color: var(--ink); font-weight: 600; }
        .bd-summary {
            background: var(--bg-sunk);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 14px;
            display: flex; flex-direction: column; gap: 7px;
        }
        .bd-payment { margin-bottom: 18px; }
        .bd-row {
            display: flex; justify-content: space-between; align-items: baseline;
            font-size: 13.5px;
            gap: 14px;
        }
        .bd-row .lbl { color: var(--ink-3); }
        .bd-row .val { color: var(--ink); font-weight: 500; font-family: var(--font-mono); text-align: right; }
        .bd-row-ok .lbl, .bd-row-ok .val { color: var(--ok); font-weight: 600; }
        .bd-row-total {
            margin-top: 6px; padding-top: 8px;
            border-top: 1px dashed var(--line);
            font-weight: 600;
        }
        .bd-row-total .lbl, .bd-row-total .val {
            color: var(--primary-deep);
            font-weight: 700;
            font-size: 15px;
        }
        .bd-cta {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 14px 18px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-deep) 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 6px 18px -6px color-mix(in srgb, var(--primary-deep) 50%, transparent);
            transition: transform 120ms ease;
        }
        .bd-cta:hover { transform: translateY(-1px); }
        .bd-fine {
            text-align: center;
            font-size: 12px;
            color: var(--ink-3);
            margin: 10px 0 16px;
        }
        .bd-block {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }
        .bd-block-eyebrow {
            font-family: var(--font-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--ink-3);
            margin-bottom: 6px;
        }
        .bd-block-text {
            font-size: 14px;
            line-height: 1.55;
            color: var(--ink);
            margin: 0;
        }
        .bd-block-link {
            display: inline-block;
            margin-top: 8px;
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .bd-block-link:hover { text-decoration: underline; }
        .bd-secondary {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 18px;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: var(--bg);
            color: var(--ink);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .bd-secondary:hover { border-color: var(--primary); color: var(--primary-deep); }
        .bd-back {
            display: block;
            margin-top: 18px;
            text-align: center;
            font-size: 13px;
            color: var(--ink-3);
            text-decoration: none;
        }
        .bd-back:hover { color: var(--primary); }
        .bd-foot {
            margin: 16px 0 0;
            text-align: center;
            font-family: var(--font-mono);
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--ink-3);
        }

        /* Make all interactive elements at least 48px tall on touch devices,
           and skip the 300ms tap delay. */
        .bd-cta, .bd-secondary, .bd-block-link, .bd-back {
            touch-action: manipulation;
            min-height: 44px;
        }
    </style>
</body>
</html>
