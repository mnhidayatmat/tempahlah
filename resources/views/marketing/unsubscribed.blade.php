<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php $isBM = app()->getLocale() === 'ms'; @endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $isBM ? 'Berhenti melanggan' : 'Unsubscribed' }} · {{ config('app.name', 'Tempahlah') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; background: #fbfdfe; color: #17272f; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 20px; }
        .card { max-width: 440px; background: #fff; border: 1px solid #e6edf1; border-radius: 18px; padding: 36px 32px; text-align: center; }
        h1 { font-size: 20px; margin: 12px 0 8px; }
        p { font-size: 14px; line-height: 1.6; color: #45565f; margin: 0 0 6px; }
        .tick { font-size: 40px; }
        a { color: #1a6a96; }
    </style>
</head>
<body>
    <div class="card">
        <div class="tick">✅</div>
        <h1>{{ $isBM ? 'Anda telah berhenti melanggan' : "You've been unsubscribed" }}</h1>
        <p>
            {{ $isBM
                ? $tenant->business_name.' tidak akan menerima email pemasaran daripada Tempahlah lagi.'
                : $tenant->business_name.' will no longer receive marketing emails from Tempahlah.' }}
        </p>
        <p style="font-size: 12.5px; color: #78878f;">
            {{ $isBM
                ? 'Email transaksi (tempahan, invois, resit) tidak terjejas.'
                : 'Transactional email (bookings, invoices, receipts) is unaffected.' }}
        </p>
    </div>
</body>
</html>
