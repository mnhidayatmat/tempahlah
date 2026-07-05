<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>{{ __('Deposit refund') }} — {{ $tenant->business_name }}</title>
    {{-- Magic-link page — links carry a signature that reveals PII. --}}
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style id="tenant-theme">:root { {!! $tenant->themeCssVariables() !!} }</style>
    <meta name="theme-color" content="{{ $tenant->themePrimary() }}">
    <style>
        body { background: var(--bg); color: var(--ink); font-family: 'Geist', system-ui, sans-serif; margin: 0; }
        .rb-wrap { max-width: 480px; margin: 0 auto; padding: 28px 18px 60px; }
        .rb-card { background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--r-xl, 20px); padding: 24px; }
        .rb-brand { display:flex; align-items:center; gap: 10px; margin-bottom: 22px; }
        .rb-brand img { width: 34px; height: 34px; border-radius: 8px; }
        .rb-brand span { font-weight: 700; font-size: 16px; }
        .rb-amount { font-family: 'Geist Mono', monospace; font-size: 30px; font-weight: 700; color: var(--primary); }
        .rb-field { display:flex; flex-direction:column; gap: 6px; margin-bottom: 16px; }
        .rb-field label { font-size: 12px; font-weight: 600; color: var(--ink-2); }
        .rb-field input { width: 100%; box-sizing: border-box; padding: 11px 13px; border: 1px solid var(--line);
            border-radius: 10px; background: var(--bg); color: var(--ink); font-size: 15px; font-family: inherit; }
        .rb-field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent); }
        .rb-btn { width: 100%; padding: 13px; border: 0; border-radius: 12px; background: var(--primary); color: #fff;
            font-size: 15px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .rb-note { font-size: 11.5px; color: var(--ink-3); margin-top: 14px; line-height: 1.5; }
        .rb-policy { margin-top: 18px; padding: 12px 14px; background: var(--bg-sunk); border-radius: 10px;
            font-size: 12px; color: var(--ink-2); line-height: 1.55; white-space: pre-line; }
        .rb-err { background: var(--err-tint); color: var(--err); border: 1px solid var(--err); border-radius: 10px;
            padding: 10px 12px; font-size: 12.5px; margin-bottom: 16px; }
        .rb-ok { text-align: center; padding: 8px 0; }
        .rb-ok-ic { width: 56px; height: 56px; border-radius: 50%; background: var(--ok-tint); color: var(--ok);
            display:flex; align-items:center; justify-content:center; margin: 0 auto 14px; font-size: 28px; }
    </style>
</head>
<body>
    @php $isBM = app()->getLocale() === 'ms'; @endphp
    <div class="rb-wrap">
        <div class="rb-brand">
            <img src="{{ asset('icons/logo.svg') }}" alt="">
            <span>{{ $tenant->business_name }}</span>
        </div>

        <div class="rb-card">
            @if ($submitted && ! $errors->any())
                {{-- Thank-you / already-submitted state --}}
                <div class="rb-ok">
                    <div class="rb-ok-ic">✓</div>
                    <div style="font-size: 18px; font-weight: 700; margin-bottom: 6px;">{{ __('Bank details received') }}</div>
                    <p style="color: var(--ink-3); font-size: 13.5px; margin: 0;">
                        {{ __('Thank you. :business will transfer your deposit of RM :amount to the account below.', ['business' => $tenant->business_name, 'amount' => number_format((float) $refund->amount, 2)]) }}
                    </p>
                    <div style="margin-top: 16px; padding: 14px; background: var(--bg-sunk); border-radius: 10px; text-align: left; font-size: 13px;">
                        <div><strong>{{ __('Bank') }}:</strong> {{ $refund->bank_name }}</div>
                        <div><strong>{{ __('Account holder') }}:</strong> {{ $refund->bank_account_holder }}</div>
                        <div><strong>{{ __('Account no.') }}:</strong> <span style="font-family:'Geist Mono',monospace;">{{ $refund->bank_account_number }}</span></div>
                    </div>
                    <p class="rb-note">{{ __('Need to change these? Contact the host directly.') }}</p>
                </div>
            @else
                <div style="font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-3);">{{ __('Deposit refund') }}</div>
                <div class="rb-amount" style="margin: 4px 0 4px;">RM {{ number_format((float) $refund->amount, 2) }}</div>
                <p style="color: var(--ink-3); font-size: 13.5px; margin: 0 0 20px;">
                    {{ __('Enter the bank account where you\'d like :business to send your deposit back.', ['business' => $tenant->business_name]) }}
                </p>

                @if ($errors->any())
                    <div class="rb-err">
                        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ url()->current() }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
                    @csrf
                    <div class="rb-field">
                        <label>{{ __('Bank name') }}</label>
                        <input type="text" name="bank_name" required maxlength="120"
                               value="{{ old('bank_name', $refund->bank_name) }}"
                               placeholder="{{ __('e.g. Maybank, CIMB, Bank Islam') }}">
                    </div>
                    <div class="rb-field">
                        <label>{{ __('Account number') }}</label>
                        <input type="text" name="bank_account_number" required maxlength="60" inputmode="numeric"
                               value="{{ old('bank_account_number') }}"
                               placeholder="{{ __('Your account number') }}">
                    </div>
                    <div class="rb-field">
                        <label>{{ __('Account holder name') }}</label>
                        <input type="text" name="bank_account_holder" required maxlength="160"
                               value="{{ old('bank_account_holder', $refund->bank_account_holder ?? $booking?->guestName()) }}"
                               placeholder="{{ __('Name as per bank account') }}">
                    </div>

                    <button type="submit" class="rb-btn">{{ __('Submit bank details') }}</button>
                </form>

                <p class="rb-note">🔒 {{ __('Your details are encrypted and shared only with the host to process this refund.') }}</p>

                @if (trim($tenant->refundPolicyText()) !== '')
                    <div class="rb-policy"><strong>{{ __('Refund policy') }}</strong><br>{{ $tenant->refundPolicyText() }}</div>
                @endif
            @endif
        </div>
    </div>
</body>
</html>
