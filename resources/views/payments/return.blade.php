@extends('layouts.booking-public')

@section('content')
<div style="max-width: 520px; margin: 64px auto; padding: 0 16px;">
    @php
        $ok = $payment->status === \App\Models\Payment::STATUS_SUCCEEDED;
        $failed = $payment->status === \App\Models\Payment::STATUS_FAILED;
        $pending = ! $ok && ! $failed;
    @endphp

    <div class="hauz-card" style="padding: 36px 32px; text-align: center;">
        @if ($ok)
            <div style="font-size: 48px;">✅</div>
            <h1 class="display-2" style="margin: 12px 0 6px;">{{ __('Payment received') }}</h1>
            <p style="color: var(--ink-3); margin: 0;">
                {{ __('Thanks! Your booking at :name is confirmed.', ['name' => $booking?->property?->name ?? '']) }}
            </p>
        @elseif ($failed)
            <div style="font-size: 48px;">⚠️</div>
            <h1 class="display-2" style="margin: 12px 0 6px;">{{ __('Payment did not complete') }}</h1>
            <p style="color: var(--ink-3); margin: 0;">
                {{ __('No worries — your booking is still on hold. Try again or contact your host.') }}
            </p>
        @else
            <div style="font-size: 48px;">⏳</div>
            <h1 class="display-2" style="margin: 12px 0 6px;">{{ __('Confirming your payment…') }}</h1>
            <p style="color: var(--ink-3); margin: 0;">
                {{ __('This usually takes under a minute. This page will refresh automatically.') }}
            </p>
            <meta http-equiv="refresh" content="6">
        @endif

        <div style="border-top: .5px solid var(--line); margin-top: 22px; padding-top: 18px;
                    display: flex; justify-content: space-between; font-size: 13px; font-family: var(--mono, monospace); color: var(--ink-2);">
            <span>{{ $booking?->reference ?? '—' }}</span>
            <span>RM {{ number_format((float) $payment->amount, 2) }}</span>
        </div>
    </div>
</div>
@endsection
