@extends('layouts.booking-public')

@section('content')
<div style="max-width: 520px; margin: 64px auto; padding: 0 16px;">
    @php
        $waPhone = $hostPhone ? preg_replace('/\D/', '', $hostPhone) : null;
    @endphp

    <div class="hauz-card" style="padding: 36px 32px; text-align: center;">
        @if ($state === 'paid')
            <div style="font-size: 48px;">✅</div>
            <h1 class="display-2" style="margin: 12px 0 6px;">{{ __('Payment received') }}</h1>
            <p style="color: var(--ink-3); margin: 0;">
                {{ __('Thanks! Your booking at :name is confirmed.', ['name' => $booking?->property?->name ?? '']) }}
            </p>

        @elseif ($state === 'failed')
            <div style="font-size: 48px;">⚠️</div>
            <h1 class="display-2" style="margin: 12px 0 6px;">{{ __('Payment did not go through') }}</h1>
            <p style="color: var(--ink-3); margin: 0;">
                {{ __('Your bank did not complete the payment, so nothing was charged. Your dates are still held — you can try again.') }}
            </p>

        @elseif ($state === 'stalled')
            <div style="font-size: 48px;">🕐</div>
            <h1 class="display-2" style="margin: 12px 0 6px;">{{ __('Still waiting on your bank') }}</h1>
            <p style="color: var(--ink-3); margin: 0;">
                {{ __('We have not had confirmation yet. If money left your account it will appear here shortly — please do not pay twice. Otherwise you can try again or message your host.') }}
            </p>

        @else
            <div style="font-size: 48px;">⏳</div>
            <h1 class="display-2" style="margin: 12px 0 6px;">{{ __('Confirming your payment…') }}</h1>
            <p style="color: var(--ink-3); margin: 0;">
                {{ __('This usually takes under a minute. This page will refresh automatically.') }}
            </p>
            <meta http-equiv="refresh" content="{{ $refreshSeconds }};url={{ $nextUrl }}">
        @endif

        @if (in_array($state, ['failed', 'stalled'], true))
            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 22px;">
                @if ($retryUrl)
                    <a href="{{ $retryUrl }}" class="btn btn-primary">{{ __('Try payment again') }}</a>
                @endif

                @if ($state === 'stalled')
                    <a href="{{ route('payments.return', ['payment' => $payment->public_id]) }}" class="btn">
                        {{ __('Check again') }}
                    </a>
                @endif

                @if ($waPhone)
                    <a href="https://wa.me/{{ $waPhone }}" class="btn" target="_blank" rel="noopener">
                        {{ __('Message the host on WhatsApp') }}
                    </a>
                @endif
            </div>
        @endif

        <div style="border-top: .5px solid var(--line); margin-top: 22px; padding-top: 18px;
                    display: flex; justify-content: space-between; font-size: 13px; font-family: var(--mono, monospace); color: var(--ink-2);">
            <span>{{ $booking?->reference ?? '—' }}</span>
            <span>RM {{ number_format((float) $payment->amount, 2) }}</span>
        </div>
    </div>
</div>
@endsection
