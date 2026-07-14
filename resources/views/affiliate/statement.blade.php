<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('Your affiliate earnings') }} — Tempahlah</title>
    {{-- Private link — the token is the credential; keep it out of search. --}}
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    {{-- Alpine served from our own origin (public-alpine.js), never a third-party
         CDN — a CDN dependency previously caused intermittent blank pages. --}}
    @vite(['resources/css/app.css', 'resources/js/public-alpine.js'])
    @php
        $rateLabel = rtrim(rtrim(number_format((float) $affiliate->rate, 2), '0'), '.');
        $last4 = $affiliate->bank_account_no ? substr($affiliate->bank_account_no, -4) : null;
        $shareMsg = __('Saya guna Tempahlah untuk urus homestay saya — tempahan, kalendar, invois, semua dalam satu. Cuba percuma di sini: :url', ['url' => $referralUrl]);
        $statusTone = [
            \App\Models\AffiliateCommission::STATUS_PENDING => 'pill-warn',
            \App\Models\AffiliateCommission::STATUS_APPROVED => 'pill-primary',
            \App\Models\AffiliateCommission::STATUS_PAID => 'pill-ok',
            \App\Models\AffiliateCommission::STATUS_VOID => '',
        ];
    @endphp
    <style>
        body{ background:var(--bg-sunk); color:var(--ink); font-family:'Geist',system-ui,sans-serif; margin:0; }
        .as-wrap{ max-width:840px; margin:0 auto; padding:28px 16px 60px; display:flex; flex-direction:column; gap:16px; }
        .as-top{ display:flex; align-items:center; gap:10px; }
        .as-top img{ width:34px; height:34px; }
        .as-card{ background:var(--bg-elev); border:1px solid var(--line); border-radius:var(--r-lg, 16px); padding:18px; }
        .as-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; }
        .as-stat .k{ font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink-3); }
        .as-stat .v{ margin-top:6px; font-size:20px; font-weight:700; font-variant-numeric:tabular-nums; }
        .as-stat .s{ margin-top:2px; font-size:11px; color:var(--ink-3); }
        .as-link-row{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .as-link{ flex:1; min-width:220px; font-family:'Geist Mono',monospace; font-size:13px; padding:10px 12px; border:.5px solid var(--line); border-radius:var(--r-sm, 8px); background:var(--bg-sunk); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .as-table{ width:100%; border-collapse:collapse; font-size:12.5px; min-width:560px; }
        .as-table thead th{ text-align:left; padding:9px 14px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); background:var(--bg-sunk); border-bottom:.5px solid var(--line); white-space:nowrap; }
        .as-table tbody td{ padding:11px 14px; border-top:.5px solid var(--line); color:var(--ink-2); }
        .as-num{ text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .as-wrap-x{ overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .as-form-grid{ display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; }
        @media (max-width:680px){ .as-form-grid{ grid-template-columns:1fr; } }
        .as-field label{ display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px; text-transform:uppercase; letter-spacing:.04em; }
        .as-field .input{ width:100%; }
        .as-stat .v{ white-space:nowrap; }
        [x-cloak]{ display:none !important; }

        /* ── Mobile (phones) ─────────────────────────────────────────────── */
        @media (max-width:640px){
            .as-wrap{ padding:20px 12px 48px; gap:14px; }
            /* Referral link on its own row; Copy + Share big + side-by-side. */
            .as-link{ flex-basis:100%; min-width:0; }
            .as-link-row .btn{ flex:1; height:44px; justify-content:center; font-size:13px; }
            .as-actions .btn{ width:100%; height:46px; justify-content:center; font-size:13.5px; }
            /* Commission table → stacked label/value cards (no sideways scroll). */
            .as-wrap-x{ overflow-x:visible; }
            .as-table{ min-width:0; display:block; font-size:13px; }
            .as-table thead{ display:none; }
            .as-table tbody{ display:block; }
            .as-table tr{ display:block; padding:10px 14px; border-top:.5px solid var(--line); }
            .as-table tr:first-child{ border-top:0; }
            .as-table td{ display:flex; align-items:center; justify-content:space-between; gap:14px; padding:4px 0; border:0; text-align:right; white-space:normal; }
            .as-table td::before{ content:attr(data-label); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-3); text-align:left; flex-shrink:0; }
        }
    </style>
</head>
<body>
    <div class="as-wrap">
        <div class="as-top">
            <img src="{{ asset('icons/logo.svg') }}" alt="Tempahlah">
            <div>
                <div style="font-weight:700; font-size:15px;">Tempahlah — {{ __('Affiliate earnings') }}</div>
                <div style="font-size:12.5px; color:var(--ink-3);">{{ $affiliate->name }}</div>
            </div>
        </div>

        @if ($saved)
            <div class="as-card" style="border-color:var(--ok); background:var(--ok-tint); color:var(--ok); padding:12px 16px; font-size:13px;">{{ $saved }}</div>
        @endif
        @if ($errors->any())
            <div class="as-card" style="border-color:var(--err); background:var(--err-tint); color:var(--err); padding:12px 16px; font-size:13px;">
                <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
            </div>
        @endif

        @if (! $affiliate->isActive())
            <div class="as-card" style="border-color:var(--warn); background:var(--warn-tint, transparent); color:var(--warn); padding:12px 16px; font-size:13px;">
                {{ __('This affiliate account is currently suspended. Existing commissions are unaffected — contact us if you have questions.') }}
            </div>
        @endif

        {{-- Referral link --}}
        <div class="as-card">
            <div style="font-weight:700; font-size:14px; margin-bottom:4px;">{{ __('Your referral link') }}</div>
            <div style="font-size:12.5px; color:var(--ink-3); margin-bottom:12px;">
                {{ __('Share this link. When a homestay owner signs up through it and subscribes, you earn :rate% of their subscription for :months months.', ['rate' => $rateLabel, 'months' => $affiliate->duration_months]) }}
            </div>
            <div class="as-link-row" x-data="{ copied:false }">
                <div class="as-link" x-ref="link">{{ $referralUrl }}</div>
                <button type="button" class="btn btn-sm" @click="navigator.clipboard.writeText($refs.link.textContent.trim()).then(()=>{copied=true;setTimeout(()=>copied=false,2000)})">
                    <span x-show="!copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>✓ {{ __('Copied!') }}</span>
                </button>
                <a class="btn btn-primary btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($shareMsg) }}">{{ __('Share on WhatsApp') }}</a>
            </div>
        </div>

        {{-- Stats --}}
        <div class="as-grid">
            <div class="as-card as-stat"><div class="k">{{ __('Link clicks') }}</div><div class="v">{{ number_format($clicks) }}</div></div>
            <div class="as-card as-stat"><div class="k">{{ __('Sign-ups') }}</div><div class="v">{{ number_format($referrals->count()) }}</div><div class="s">{{ __(':n subscribed', ['n' => $convertedCount]) }}</div></div>
            <div class="as-card as-stat"><div class="k">{{ __('Pending') }}</div><div class="v">RM {{ number_format($pendingTotal, 2) }}</div><div class="s">{{ __(':days-day hold', ['days' => $holdDays]) }}</div></div>
            <div class="as-card as-stat"><div class="k">{{ __('Payable') }}</div><div class="v">RM {{ number_format($approvedTotal, 2) }}</div></div>
            <div class="as-card as-stat"><div class="k">{{ __('Paid out') }}</div><div class="v">RM {{ number_format($paidTotal, 2) }}</div></div>
        </div>

        {{-- Commission statement --}}
        <div class="as-card" style="padding:0; overflow:hidden;">
            <div style="padding:14px 16px 12px; border-bottom:.5px solid var(--line); font-weight:700; font-size:13.5px;">{{ __('Commission statement') }}</div>
            @if ($commissions->isEmpty())
                <div style="padding:26px; text-align:center; color:var(--ink-3); font-size:13px;">
                    {{ __('No commissions yet — they appear here when a homestay you referred makes a subscription payment.') }}
                </div>
            @else
                <div class="as-wrap-x">
                    <table class="as-table">
                        <thead><tr>
                            <th>{{ __('Date') }}</th><th>{{ __('Homestay') }}</th>
                            <th class="as-num">{{ __('Payment') }}</th><th class="as-num">{{ __('Your commission') }}</th><th>{{ __('Status') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($commissions as $c)
                                <tr>
                                    <td data-label="{{ __('Date') }}" style="white-space:nowrap;">{{ $c->created_at->format('j M Y') }}</td>
                                    <td data-label="{{ __('Homestay') }}">{{ $c->tenant?->business_name ?? '—' }}</td>
                                    <td data-label="{{ __('Payment') }}" class="as-num">RM {{ number_format((float) $c->base_amount, 2) }}</td>
                                    <td data-label="{{ __('Your commission') }}" class="as-num" style="font-weight:600; color:var(--ink);">RM {{ number_format((float) $c->amount, 2) }}</td>
                                    <td data-label="{{ __('Status') }}"><span class="pill {{ $statusTone[$c->status] ?? '' }}" style="height:20px; font-size:11px;">{{ $c->statusLabel() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Payout bank details --}}
        <div class="as-card">
            <div style="font-weight:700; font-size:14px; margin-bottom:4px;">{{ __('Payout details') }}</div>
            <div style="font-size:12.5px; color:var(--ink-3); margin-bottom:14px;">
                {{ __('Payable commissions are paid to this account by bank transfer / DuitNow. Your account number is stored encrypted.') }}
                @if ($last4)
                    <div style="margin-top:6px; color:var(--ink-2);">{{ __('On file: :bank •••• :last4', ['bank' => $affiliate->bank_name ?: '—', 'last4' => $last4]) }}</div>
                @endif
            </div>
            <form method="POST" action="{{ route('affiliate.statement.bank', ['token' => $affiliate->statement_token]) }}">
                @csrf
                <div class="as-form-grid">
                    <div class="as-field">
                        <label>{{ __('Bank') }}</label>
                        <input class="input" type="text" name="bank_name" maxlength="120" value="{{ old('bank_name', $affiliate->bank_name) }}" placeholder="Maybank">
                    </div>
                    <div class="as-field">
                        <label>{{ __('Account holder') }}</label>
                        <input class="input" type="text" name="bank_account_holder" maxlength="120" value="{{ old('bank_account_holder', $affiliate->bank_account_holder) }}">
                    </div>
                    <div class="as-field">
                        <label>{{ __('Account number') }}</label>
                        <input class="input" type="text" name="bank_account_no" maxlength="60" inputmode="numeric" value="{{ old('bank_account_no') }}" placeholder="{{ $last4 ? __('•••• :last4 (leave blank to keep)', ['last4' => $last4]) : '' }}">
                    </div>
                </div>
                <div class="as-actions" style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save payout details') }}</button>
                </div>
            </form>
        </div>

        {{-- Terms --}}
        <div class="as-card" style="font-size:12.5px; color:var(--ink-3); line-height:1.7;">
            <div style="font-weight:700; font-size:13px; color:var(--ink); margin-bottom:6px;">{{ __('How it works') }}</div>
            <ul style="margin:0; padding-left:18px;">
                <li>{{ __('You earn :rate% of every subscription payment made by a homestay you referred, for their first :months months as a paying customer.', ['rate' => $rateLabel, 'months' => $affiliate->duration_months]) }}</li>
                <li>{{ __('Commissions are held as Pending for :days days (refund protection), then become Payable.', ['days' => $holdDays]) }}</li>
                <li>{{ __('Payouts are made manually by bank transfer. Keep your payout details above up to date.') }}</li>
            </ul>
            <div style="margin-top:10px; padding-top:10px; border-top:.5px solid var(--line); color:var(--ink-4, var(--ink-3));">
                {{ __('This is a private page — anyone with this link can see these figures. Do not share it.') }}
            </div>
        </div>
    </div>
</body>
</html>
