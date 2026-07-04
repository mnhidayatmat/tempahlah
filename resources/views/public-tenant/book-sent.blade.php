<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>{{ __('Pay deposit') }} — {{ $tenant->business_name }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style id="tenant-theme">:root { {!! $tenant->themeCssVariables() !!} }</style>
    <meta name="theme-color" content="{{ $tenant->themePrimary() }}">
    @php $isBM = app()->getLocale() === 'ms'; @endphp
</head>
<body class="bs-body">
    <main class="bs-card">
        <div class="bs-eyebrow">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ $isBM ? 'Tempahan diterima' : 'Booking received' }}
        </div>
        <h1 class="bs-title">{{ $isBM ? 'Hampir siap!' : 'Almost there!' }}</h1>
        <p class="bs-sub">
            @if($payUrl)
                {{ $isBM
                    ? 'Kami telah menghantar invois ke emel dan WhatsApp anda. Tekan butang di bawah untuk membayar deposit dan mengesahkan tempahan.'
                    : 'We\'ve sent the invoice to your email and WhatsApp. Tap the button below to pay the deposit and confirm your booking.' }}
            @else
                {{ $isBM
                    ? 'Kami telah menghantar invois ke emel dan WhatsApp anda. Sila buat bayaran mengikut arahan di bawah untuk mengesahkan tempahan.'
                    : 'We\'ve sent the invoice to your email and WhatsApp. Please complete payment using the instructions below to confirm your booking.' }}
            @endif
        </p>

        <div class="bs-summary">
            <div class="bs-summary-row">
                <span class="lbl">{{ $isBM ? 'Rujukan' : 'Reference' }}</span>
                <span class="val">{{ $booking->reference }}</span>
            </div>
            <div class="bs-summary-row">
                <span class="lbl">{{ $isBM ? 'Penginapan' : 'Property' }}</span>
                <span class="val">{{ $booking->property->name }}</span>
            </div>
            <div class="bs-summary-row">
                <span class="lbl">{{ $isBM ? 'Tarikh' : 'Dates' }}</span>
                <span class="val">{{ $booking->check_in->format('D, d M Y') }} → {{ $booking->check_out->format('D, d M Y') }}</span>
            </div>
            <div class="bs-summary-row">
                <span class="lbl">{{ $isBM ? 'Malam' : 'Nights' }}</span>
                <span class="val">{{ $booking->nights }}</span>
            </div>
            <div class="bs-summary-row">
                <span class="lbl">{{ $isBM ? 'Tetamu' : 'Guests' }}</span>
                <span class="val">{{ $booking->adults }}</span>
            </div>
            <div class="bs-summary-row bs-summary-total">
                <span class="lbl">{{ $isBM ? 'Jumlah' : 'Total' }}</span>
                <span class="val">RM {{ number_format($booking->total_amount, 2) }}</span>
            </div>
            <div class="bs-summary-row bs-summary-deposit">
                <span class="lbl">{{ $isBM ? 'Bayar sekarang' : 'Pay now' }}</span>
                <span class="val">RM {{ number_format($booking->deposit_amount, 2) }}</span>
            </div>
        </div>

        @if($payUrl)
            <a href="{{ $payUrl }}" class="bs-cta">
                {{ $isBM ? 'Bayar sekarang' : 'Pay now' }} — RM {{ number_format($booking->deposit_amount, 2) }} →
            </a>
            <p class="bs-fine">
                {{ $isBM
                    ? 'Anda akan dialihkan ke Toyyibpay. FPX, kad kredit/debit & DuitNow QR diterima.'
                    : 'You\'ll be redirected to Toyyibpay. FPX, credit/debit cards & DuitNow QR accepted.' }}
            </p>
        @else
            {{-- Manual payment: show the host's bank-transfer instructions
                 (or a generic "contact the host" note when none are set). --}}
            <div class="bs-manual">
                <div class="bs-manual-head">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    {{ $isBM ? 'Cara bayaran' : 'How to pay' }}
                </div>
                <div class="bs-manual-body">@if(!empty($manualInstructions)){{ $manualInstructions }}@else{{ $isBM
                    ? 'Sila hubungi tuan rumah untuk mengaturkan bayaran. Sebut rujukan tempahan anda semasa membayar.'
                    : 'Please contact the host to arrange your payment. Quote your booking reference when you pay.' }}@endif</div>
                <div class="bs-manual-ref">{{ $isBM ? 'Rujukan' : 'Reference' }}: <strong>{{ $booking->reference }}</strong></div>
            </div>
            @if($contactPhone ?? preg_replace('/\D/', '', $tenant->business_phone ?? ''))
                @php $waPhone = preg_replace('/\D/', '', $tenant->business_phone ?? ''); @endphp
                <a href="https://wa.me/{{ $waPhone }}?text={{ rawurlencode(($isBM ? 'Salam! Saya sudah tempah. Rujukan: ' : 'Hi! I\'ve booked. Reference: ').$booking->reference) }}" target="_blank" rel="noopener" class="bs-cta">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.8-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4 0 1.4 1 2.8 1.2 3 .1.2 2.1 3.2 5.1 4.4.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.1-.3-.2-.6-.3z"/><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2z"/></svg>
                    {{ $isBM ? 'Hantar resit bayaran' : 'Send payment proof' }}
                </a>
            @else
                <a href="{{ route('tenant-public.home', ['tenant_slug' => $tenant->slug]) }}" class="bs-cta">
                    {{ $isBM ? 'Kembali ke laman utama' : 'Back to home' }}
                </a>
            @endif
        @endif

        <div class="bs-channels">
            <div class="bs-channel">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>
                <span>{{ $isBM ? 'Semak emel anda' : 'Check your email' }}</span>
            </div>
            <div class="bs-channel">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.8-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4 0 1.4 1 2.8 1.2 3 .1.2 2.1 3.2 5.1 4.4.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.1-.3-.2-.6-.3z"/><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2z"/></svg>
                <span>{{ $isBM ? 'Semak WhatsApp anda' : 'Check your WhatsApp' }}</span>
            </div>
        </div>

        <a href="{{ route('tenant-public.home', ['tenant_slug' => $tenant->slug]) }}" class="bs-back">← {{ $isBM ? 'Kembali ke ' . $tenant->business_name : 'Back to ' . $tenant->business_name }}</a>
    </main>

    <style>
        .bs-body {
            margin: 0;
            font-family: var(--font-sans);
            background:
                radial-gradient(900px 600px at 50% -20%, oklch(96% 0.04 45 / 0.5), transparent 65%),
                var(--bg);
            color: var(--ink);
            min-height: 100dvh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px 16px;
            -webkit-font-smoothing: antialiased;
        }
        .bs-card {
            width: 100%;
            max-width: 480px;
            background: var(--bg-elev);
            border-radius: 22px;
            border: 1px solid var(--line);
            box-shadow: 0 30px 80px -20px rgba(40,30,10,0.18);
            padding: 28px 22px;
        }
        .bs-eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            font-family: var(--font-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--ok);
            padding: 5px 10px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--ok) 12%, transparent);
            margin-bottom: 14px;
        }
        .bs-title {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px;
            color: var(--ink);
            line-height: 1.15;
        }
        .bs-sub {
            font-size: 14.5px;
            line-height: 1.55;
            color: var(--ink-2);
            margin: 0 0 20px;
        }
        .bs-summary {
            background: var(--bg-sunk);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 18px;
            display: flex; flex-direction: column; gap: 7px;
        }
        .bs-summary-row {
            display: flex; justify-content: space-between; align-items: baseline;
            font-size: 13px;
        }
        .bs-summary-row .lbl { color: var(--ink-3); }
        .bs-summary-row .val { color: var(--ink); font-weight: 500; font-family: var(--font-mono); }
        .bs-summary-total {
            margin-top: 6px; padding-top: 8px;
            border-top: 1px dashed var(--line);
            font-weight: 600;
        }
        .bs-summary-deposit {
            color: var(--primary-deep);
        }
        .bs-summary-deposit .lbl { color: var(--primary-deep); font-weight: 600; }
        .bs-summary-deposit .val { color: var(--primary-deep); font-weight: 700; font-size: 15px; }
        .bs-cta {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 14px 18px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-deep) 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 6px 18px -6px color-mix(in srgb, var(--primary-deep) 50%, transparent);
        }
        .bs-cta:hover { transform: translateY(-1px); }
        .bs-fine {
            text-align: center;
            font-size: 12px;
            color: var(--ink-3);
            margin: 10px 0 18px;
        }
        .bs-manual {
            background: var(--bg-sunk);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .bs-manual-head {
            display: flex; align-items: center; gap: 7px;
            font-size: 12px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--primary-deep);
            margin-bottom: 8px;
        }
        .bs-manual-body {
            font-size: 13.5px;
            line-height: 1.5;
            color: var(--ink);
            white-space: pre-line;
        }
        .bs-manual-ref {
            margin-top: 10px; padding-top: 10px;
            border-top: 1px dashed var(--line);
            font-size: 12.5px; color: var(--ink-2);
            font-family: var(--font-mono);
        }
        .bs-channels {
            display: flex; gap: 8px;
            margin-top: 18px; padding-top: 18px;
            border-top: 1px solid var(--line);
        }
        .bs-channel {
            flex: 1;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 12.5px;
            color: var(--ink-2);
            padding: 10px 8px;
            background: var(--bg-sunk);
            border-radius: 10px;
        }
        .bs-back {
            display: block;
            margin-top: 16px;
            text-align: center;
            font-size: 13px;
            color: var(--ink-3);
            text-decoration: none;
        }
        .bs-back:hover { color: var(--primary); }
    </style>
</body>
</html>
