<x-app-layout :title="__('Affiliates')" :breadcrumbs="[__('Platform')]">
    <div style="max-width: 1080px; margin: 0 auto; display:flex; flex-direction:column; gap: 18px;">

        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Platform admin') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Affiliates') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 13px;">
                    {{ __('The referral program: hosts get their link automatically from Refer & Earn; external partners (influencers, agencies) are created here. Commissions accrue only on real subscription money.') }}
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--ok); background: var(--ok-tint);">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err);">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <ul style="margin:0; padding-left: 18px;">@foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- New external affiliate --}}
        <details class="hauz-card" style="padding: 0; overflow:hidden;">
            <summary style="padding: 14px 16px; cursor:pointer; font-weight:700; font-size:13.5px; color:var(--ink); list-style:none;">
                + {{ __('New external affiliate') }}
                <span style="font-weight:400; color:var(--ink-3); font-size:12px; margin-left:8px;">{{ __('(influencer / agency — no homestay account needed)') }}</span>
            </summary>
            <form method="POST" action="{{ route('platform.affiliates.store') }}" style="padding: 4px 16px 16px; border-top:.5px solid var(--line);">
                @csrf
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-top:12px;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px; text-transform:uppercase;">{{ __('Name') }} *</label>
                        <input class="input" type="text" name="name" required maxlength="160" value="{{ old('name') }}">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px; text-transform:uppercase;">{{ __('Email') }}</label>
                        <input class="input" type="email" name="email" maxlength="190" value="{{ old('email') }}">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px; text-transform:uppercase;">{{ __('Phone') }}</label>
                        <input class="input" type="text" name="phone" maxlength="40" value="{{ old('phone') }}">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px; text-transform:uppercase;">{{ __('Code (optional)') }}</label>
                        <input class="input" type="text" name="code" maxlength="24" placeholder="{{ __('auto') }}" value="{{ old('code') }}">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px; text-transform:uppercase;">{{ __('Rate %') }} *</label>
                        <input class="input" type="number" name="rate" required min="0" max="50" step="0.5" value="{{ old('rate', $defaultRate) }}">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px; text-transform:uppercase;">{{ __('Months') }} *</label>
                        <input class="input" type="number" name="duration_months" required min="1" max="60" value="{{ old('duration_months', $defaultMonths) }}">
                    </div>
                </div>
                <div style="margin-top: 12px;">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Create affiliate') }}</button>
                </div>
            </form>
        </details>

        {{-- Affiliate list --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            @if ($affiliates->isEmpty())
                <div style="padding: 40px; text-align: center; color: var(--ink-3);">
                    {{ __('No affiliates yet. Hosts appear here automatically the first time they open Refer & Earn.') }}
                </div>
            @else
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 860px;">
                        <thead>
                            <tr style="background: var(--bg-sunk); font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: var(--ink-3);">
                                <th style="text-align:left; padding: 12px 18px;">{{ __('Affiliate') }}</th>
                                <th style="text-align:left; padding: 12px 10px;">{{ __('Code') }}</th>
                                <th style="text-align:right; padding: 12px 10px;">{{ __('Rate') }}</th>
                                <th style="text-align:right; padding: 12px 10px;">{{ __('Clicks') }}</th>
                                <th style="text-align:right; padding: 12px 10px;">{{ __('Referrals') }}</th>
                                <th style="text-align:right; padding: 12px 10px;">{{ __('Pending') }}</th>
                                <th style="text-align:right; padding: 12px 10px;">{{ __('Payable') }}</th>
                                <th style="text-align:right; padding: 12px 10px;">{{ __('Paid') }}</th>
                                <th style="text-align:left; padding: 12px 18px;">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($affiliates as $a)
                                @php
                                    $t = $totals->get($a->id, collect())->pluck('total', 'status');
                                @endphp
                                <tr style="border-top: .5px solid var(--line);">
                                    <td style="padding: 12px 18px;">
                                        <a href="{{ route('platform.affiliates.show', $a) }}" style="color: var(--ink); font-weight: 600; text-decoration: none;">{{ $a->name }}</a>
                                        <div style="font-size:11.5px; color:var(--ink-3);">
                                            {{ $a->user_id ? __('Host') : __('External') }}@if($a->email) · {{ $a->email }}@endif
                                        </div>
                                    </td>
                                    <td style="padding: 12px 10px; font-family: var(--font-mono, monospace); font-size:12px;">{{ $a->code }}</td>
                                    <td style="padding: 12px 10px; text-align:right;">{{ rtrim(rtrim(number_format((float) $a->rate, 2), '0'), '.') }}%</td>
                                    <td style="padding: 12px 10px; text-align:right;">{{ number_format((int) ($clicks[$a->id] ?? 0)) }}</td>
                                    <td style="padding: 12px 10px; text-align:right;">{{ number_format($a->referrals_count) }}</td>
                                    <td style="padding: 12px 10px; text-align:right;">RM {{ number_format((float) ($t['pending'] ?? 0), 2) }}</td>
                                    <td style="padding: 12px 10px; text-align:right; font-weight:600;">RM {{ number_format((float) ($t['approved'] ?? 0), 2) }}</td>
                                    <td style="padding: 12px 10px; text-align:right;">RM {{ number_format((float) ($t['paid'] ?? 0), 2) }}</td>
                                    <td style="padding: 12px 18px;">
                                        <span class="pill {{ $a->status === 'active' ? 'pill-ok' : 'pill-warn' }}" style="height: 20px; font-size: 11px;">{{ __(ucfirst($a->status)) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
