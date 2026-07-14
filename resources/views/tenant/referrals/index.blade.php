<x-app-layout :title="__('Refer & Earn')">
    @php
        $rateLabel = rtrim(rtrim(number_format((float) $affiliate->rate, 2), '0'), '.');
        $shareMsg = __('Saya guna Tempahlah untuk urus homestay saya — tempahan, kalendar, invois, semua dalam satu. Cuba percuma di sini: :url', ['url' => $referralUrl]);
        $statusTone = [
            \App\Models\AffiliateCommission::STATUS_PENDING => 'pill-warn',
            \App\Models\AffiliateCommission::STATUS_APPROVED => 'pill-primary',
            \App\Models\AffiliateCommission::STATUS_PAID => 'pill-ok',
            \App\Models\AffiliateCommission::STATUS_VOID => '',
        ];
    @endphp

    @once
    <style>
        .rf-grid{ display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; }
        .rf-stat{ padding:14px 16px; }
        .rf-stat .k{ font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink-3); }
        .rf-stat .v{ margin-top:6px; font-size:20px; font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; white-space:nowrap; }
        .rf-stat .s{ margin-top:2px; font-size:11px; color:var(--ink-3); }
        .rf-link-row{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .rf-link{ flex:1; min-width:220px; font-family:var(--font-mono, monospace); font-size:13px; padding:10px 12px; border:.5px solid var(--line); border-radius:var(--r-sm); background:var(--bg-sunk); color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rf-table{ width:100%; border-collapse:collapse; font-size:12.5px; min-width:640px; }
        .rf-table thead th{ text-align:left; padding:9px 14px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); background:var(--bg-sunk); border-bottom:.5px solid var(--line); white-space:nowrap; }
        .rf-table tbody td{ padding:11px 14px; border-top:.5px solid var(--line); vertical-align:middle; color:var(--ink-2); }
        .rf-num{ text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .rf-wrap{ overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .rf-field label{ display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px; text-transform:uppercase; letter-spacing:.04em; }
        .rf-form-grid{ display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; }
        @media (max-width:720px){ .rf-form-grid{ grid-template-columns: 1fr; } }

        /* ── Mobile (phones) ─────────────────────────────────────────────── */
        @media (max-width:640px){
            /* Referral link: link on its own row, Copy + Share as two big
               side-by-side buttons with proper tap targets. */
            .rf-link{ flex-basis:100%; min-width:0; }
            .rf-link-row .btn{ flex:1; height:44px; justify-content:center; font-size:13px; }
            /* Save payout button spans the row (easy thumb target). */
            .rf-actions .btn{ width:100%; height:46px; justify-content:center; font-size:13.5px; }
            /* Commission table → stacked label/value cards (no sideways scroll). */
            .rf-wrap{ overflow-x:visible; }
            .rf-table{ min-width:0; display:block; font-size:13px; }
            .rf-table thead{ display:none; }
            .rf-table tbody{ display:block; }
            .rf-table tr{ display:block; padding:10px 14px; border-top:.5px solid var(--line); }
            .rf-table tr:first-child{ border-top:0; }
            .rf-table td{ display:flex; align-items:center; justify-content:space-between; gap:14px; padding:4px 0; border:0; text-align:right; white-space:normal; }
            .rf-table td::before{ content:attr(data-label); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-3); text-align:left; flex-shrink:0; }
        }
    </style>
    @endonce

    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div>
            <div class="kicker">{{ __('Configure') }}</div>
            <div class="display-2" style="margin-top: 4px;">{{ __('Refer & Earn') }}</div>
            <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                {{ __('Share your link with other homestay owners. When someone signs up through it and subscribes, you earn :rate% of every payment they make for their first :months months — paid to your bank account.', ['rate' => $rateLabel, 'months' => $affiliate->duration_months]) }}
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--ok); background: var(--ok-tint); color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <ul style="margin:0; padding-left: 18px;">@foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Referral link --}}
        <div class="hauz-card" style="padding: 18px;">
            <div style="font-weight:700; font-size:14px; color:var(--ink); margin-bottom:4px;">{{ __('Your referral link') }}</div>
            <div style="font-size:12.5px; color:var(--ink-3); margin-bottom:12px;">
                {{ __('Anyone who opens this link and registers within :days days is counted as your referral.', ['days' => \App\Support\Affiliate\ReferralAttribution::cookieDays()]) }}
            </div>
            <div class="rf-link-row" x-data="{ copied: false }">
                <div class="rf-link" x-ref="link">{{ $referralUrl }}</div>
                <button type="button" class="btn btn-sm"
                        @click="navigator.clipboard.writeText($refs.link.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 2000); })">
                    <span x-show="!copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>✓ {{ __('Copied!') }}</span>
                </button>
                <a class="btn btn-primary btn-sm" target="_blank" rel="noopener"
                   href="https://wa.me/?text={{ rawurlencode($shareMsg) }}">
                    {{ __('Share on WhatsApp') }}
                </a>
            </div>
        </div>

        {{-- Stats --}}
        <div class="rf-grid">
            <div class="hauz-card rf-stat">
                <div class="k">{{ __('Link clicks') }}</div>
                <div class="v">{{ number_format($clicks) }}</div>
            </div>
            <div class="hauz-card rf-stat">
                <div class="k">{{ __('Sign-ups') }}</div>
                <div class="v">{{ number_format($referrals->count()) }}</div>
                <div class="s">{{ __(':n subscribed', ['n' => $convertedCount]) }}</div>
            </div>
            <div class="hauz-card rf-stat">
                <div class="k">{{ __('Pending') }}</div>
                <div class="v">RM {{ number_format($pendingTotal, 2) }}</div>
                <div class="s">{{ __(':days-day hold', ['days' => $holdDays]) }}</div>
            </div>
            <div class="hauz-card rf-stat">
                <div class="k">{{ __('Payable') }}</div>
                <div class="v">RM {{ number_format($approvedTotal, 2) }}</div>
            </div>
            <div class="hauz-card rf-stat">
                <div class="k">{{ __('Paid out') }}</div>
                <div class="v">RM {{ number_format($paidTotal, 2) }}</div>
            </div>
        </div>

        {{-- Commission statement --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 14px 16px 12px; border-bottom: .5px solid var(--line); font-weight:700; font-size:13.5px; color:var(--ink);">
                {{ __('Commission statement') }}
            </div>
            @if ($commissions->isEmpty())
                <div style="padding: 28px; text-align:center; color: var(--ink-3); font-size: 13px;">
                    {{ __('No commissions yet — they appear here when a homestay you referred makes a subscription payment.') }}
                </div>
            @else
                <div class="rf-wrap">
                    <table class="rf-table">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Homestay') }}</th>
                                <th class="rf-num">{{ __('Payment') }}</th>
                                <th class="rf-num">{{ __('Your commission') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($commissions as $c)
                                <tr>
                                    <td data-label="{{ __('Date') }}" style="white-space:nowrap;">{{ $c->created_at->format('j M Y') }}</td>
                                    <td data-label="{{ __('Homestay') }}">{{ $c->tenant?->business_name ?? '—' }}</td>
                                    <td data-label="{{ __('Payment') }}" class="rf-num">RM {{ number_format((float) $c->base_amount, 2) }}</td>
                                    <td data-label="{{ __('Your commission') }}" class="rf-num" style="font-weight:600; color:var(--ink);">RM {{ number_format((float) $c->amount, 2) }}</td>
                                    <td data-label="{{ __('Status') }}"><span class="pill {{ $statusTone[$c->status] ?? '' }}" style="height:20px; font-size:11px;">{{ $c->statusLabel() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Payout details --}}
        <div class="hauz-card" style="padding: 18px;">
            <div style="font-weight:700; font-size:14px; color:var(--ink); margin-bottom:4px;">{{ __('Payout details') }}</div>
            <div style="font-size:12.5px; color:var(--ink-3); margin-bottom:14px;">
                {{ __('Payable commissions are transferred to this account by bank transfer / DuitNow. Your account number is stored encrypted.') }}
            </div>
            <form method="POST" action="{{ route('tenant.referrals.bank') }}">
                @csrf
                <div class="rf-form-grid">
                    <div class="rf-field">
                        <label for="rf-bank-name">{{ __('Bank') }}</label>
                        <input id="rf-bank-name" class="input" type="text" name="bank_name" maxlength="120" value="{{ old('bank_name', $affiliate->bank_name) }}" placeholder="Maybank">
                    </div>
                    <div class="rf-field">
                        <label for="rf-bank-holder">{{ __('Account holder') }}</label>
                        <input id="rf-bank-holder" class="input" type="text" name="bank_account_holder" maxlength="120" value="{{ old('bank_account_holder', $affiliate->bank_account_holder) }}">
                    </div>
                    <div class="rf-field">
                        <label for="rf-bank-no">{{ __('Account number') }}</label>
                        <input id="rf-bank-no" class="input" type="text" name="bank_account_no" maxlength="60" inputmode="numeric" value="{{ old('bank_account_no', $affiliate->bank_account_no) }}">
                    </div>
                </div>
                <div class="rf-actions" style="margin-top: 12px;">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save payout details') }}</button>
                </div>
            </form>
        </div>

        {{-- Program terms --}}
        <div class="hauz-card" style="padding: 16px 18px; font-size: 12.5px; color: var(--ink-3); line-height: 1.7;">
            <div style="font-weight:700; font-size:13px; color:var(--ink); margin-bottom:6px;">{{ __('How it works') }}</div>
            <ul style="margin:0; padding-left: 18px;">
                <li>{{ __('You earn :rate% of every subscription payment made by a homestay you referred, for their first :months months as a paying customer.', ['rate' => $rateLabel, 'months' => $affiliate->duration_months]) }}</li>
                <li>{{ __('Commissions are held as Pending for :days days (refund protection), then become Payable.', ['days' => $holdDays]) }}</li>
                <li>{{ __('Payouts are made manually by bank transfer. Make sure your payout details above are correct.') }}</li>
                <li>{{ __('Referring your own account earns nothing, and abuse may lead to the program being suspended for you.') }}</li>
            </ul>
        </div>
    </div>
</x-app-layout>
