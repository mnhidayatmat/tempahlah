<x-app-layout :title="$affiliate->name" :breadcrumbs="[__('Platform'), __('Affiliates')]">
    @php
        $statusTone = [
            \App\Models\AffiliateCommission::STATUS_PENDING => 'pill-warn',
            \App\Models\AffiliateCommission::STATUS_APPROVED => 'pill-primary',
            \App\Models\AffiliateCommission::STATUS_PAID => 'pill-ok',
            \App\Models\AffiliateCommission::STATUS_VOID => '',
        ];
        $pendingTotal = (float) ($sums['pending'] ?? 0);
        $approvedTotal = (float) ($sums['approved'] ?? 0);
        $paidTotal = (float) ($sums['paid'] ?? 0);
    @endphp

    <div style="max-width: 1080px; margin: 0 auto; display:flex; flex-direction:column; gap: 18px;">

        <div>
            <a href="{{ route('platform.affiliates.index') }}" style="font-size: 12.5px; color: var(--ink-3); text-decoration: none;">← {{ __('All affiliates') }}</a>
            <div style="display:flex; align-items:center; gap: 10px; margin-top: 6px; flex-wrap: wrap;">
                <div class="display-2">{{ $affiliate->name }}</div>
                <span class="pill {{ $affiliate->status === 'active' ? 'pill-ok' : 'pill-warn' }}" style="height: 20px; font-size: 11px;">{{ __(ucfirst($affiliate->status)) }}</span>
                <span class="pill" style="height: 20px; font-size: 11px;">{{ $affiliate->user_id ? __('Host affiliate') : __('External affiliate') }}</span>
            </div>
            <div style="margin-top: 6px; color: var(--ink-3); font-size: 13px; font-family: var(--font-mono, monospace);">
                {{ $affiliate->referralUrl() }}
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

        {{-- Stats --}}
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px;">
            @foreach ([
                [__('Clicks'), number_format($clicks)],
                [__('Referrals'), number_format($affiliate->referrals_count)],
                [__('Pending'), 'RM '.number_format($pendingTotal, 2)],
                [__('Payable'), 'RM '.number_format($approvedTotal, 2)],
                [__('Paid out'), 'RM '.number_format($paidTotal, 2)],
            ] as [$k, $v])
                <div class="hauz-card" style="padding: 14px 16px;">
                    <div style="font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink-3);">{{ $k }}</div>
                    <div style="margin-top:6px; font-size:19px; font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums;">{{ $v }}</div>
                </div>
            @endforeach
        </div>

        {{-- Payout + settings row --}}
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px;">
            {{-- Record payout --}}
            <div class="hauz-card" style="padding: 16px 18px;">
                <div style="font-weight:700; font-size:13.5px; color:var(--ink); margin-bottom:4px;">{{ __('Record payout') }}</div>
                <div style="font-size:12px; color:var(--ink-3); margin-bottom:12px;">
                    {{ __('Marks every PAYABLE commission paid under one reference (do the bank transfer first).') }}
                    @if ($affiliate->bank_name || $affiliate->bank_account_no)
                        <div style="margin-top:6px; color:var(--ink-2);">
                            🏦 {{ $affiliate->bank_name }} · {{ $affiliate->bank_account_no }} · {{ $affiliate->bank_account_holder }}
                        </div>
                    @else
                        <div style="margin-top:6px;">{{ __('No payout bank details on file yet.') }}</div>
                    @endif
                </div>
                <form method="POST" action="{{ route('platform.affiliates.mark-paid', $affiliate) }}" style="display:flex; gap:8px; flex-wrap:wrap;">
                    @csrf
                    <input class="input" type="text" name="payout_ref" required maxlength="120" placeholder="{{ __('Transfer reference') }}" style="flex:1; min-width:160px;">
                    <button type="submit" class="btn btn-primary btn-sm" @disabled($approvedTotal <= 0)>
                        {{ __('Mark RM :amt paid', ['amt' => number_format($approvedTotal, 2)]) }}
                    </button>
                </form>
            </div>

            {{-- Edit affiliate --}}
            <div class="hauz-card" style="padding: 16px 18px;">
                <div style="font-weight:700; font-size:13.5px; color:var(--ink); margin-bottom:12px;">{{ __('Settings') }}</div>
                <form method="POST" action="{{ route('platform.affiliates.update', $affiliate) }}">
                    @csrf
                    @method('PATCH')
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:10px;">
                        <div>
                            <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px;">{{ __('Name') }}</label>
                            <input class="input" type="text" name="name" required maxlength="160" value="{{ old('name', $affiliate->name) }}">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px;">{{ __('Email') }}</label>
                            <input class="input" type="email" name="email" maxlength="190" value="{{ old('email', $affiliate->email) }}">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px;">{{ __('Phone') }}</label>
                            <input class="input" type="text" name="phone" maxlength="40" value="{{ old('phone', $affiliate->phone) }}">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px;">{{ __('Status') }}</label>
                            <select class="input" name="status">
                                <option value="active" @selected($affiliate->status === 'active')>{{ __('Active') }}</option>
                                <option value="suspended" @selected($affiliate->status === 'suspended')>{{ __('Suspended') }}</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px;">{{ __('Rate %') }}</label>
                            <input class="input" type="number" name="rate" required min="0" max="50" step="0.5" value="{{ old('rate', (float) $affiliate->rate) }}">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px;">{{ __('Months') }}</label>
                            <input class="input" type="number" name="duration_months" required min="1" max="60" value="{{ old('duration_months', $affiliate->duration_months) }}">
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <label style="display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px;">{{ __('Notes') }}</label>
                        <input class="input" type="text" name="notes" maxlength="500" value="{{ old('notes', $affiliate->notes) }}">
                    </div>
                    <div style="margin-top: 12px;">
                        <button type="submit" class="btn btn-sm">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Referrals --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 14px 16px 12px; border-bottom: .5px solid var(--line); font-weight:700; font-size:13.5px; color:var(--ink);">
                {{ __('Referred homestays') }}
            </div>
            @if ($referrals->isEmpty())
                <div style="padding: 24px; text-align:center; color: var(--ink-3); font-size: 13px;">{{ __('No sign-ups through this link yet.') }}</div>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:560px;">
                        <thead>
                            <tr style="background: var(--bg-sunk); font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: var(--ink-3);">
                                <th style="text-align:left; padding: 10px 18px;">{{ __('Homestay') }}</th>
                                <th style="text-align:left; padding: 10px 10px;">{{ __('Signed up') }}</th>
                                <th style="text-align:left; padding: 10px 18px;">{{ __('First paid') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($referrals as $r)
                                <tr style="border-top:.5px solid var(--line);">
                                    <td style="padding: 11px 18px; font-weight:600; color:var(--ink);">{{ $r->tenant?->business_name ?? '—' }}</td>
                                    <td style="padding: 11px 10px; color:var(--ink-2);">{{ $r->created_at->format('j M Y') }}</td>
                                    <td style="padding: 11px 18px; color:var(--ink-2);">
                                        {{ $r->converted_at ? $r->converted_at->format('j M Y') : __('Not yet') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Commissions --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 14px 16px 12px; border-bottom: .5px solid var(--line); font-weight:700; font-size:13.5px; color:var(--ink);">
                {{ __('Commissions') }}
            </div>
            @if ($commissions->isEmpty())
                <div style="padding: 24px; text-align:center; color: var(--ink-3); font-size: 13px;">{{ __('No commissions yet.') }}</div>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:760px;">
                        <thead>
                            <tr style="background: var(--bg-sunk); font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: var(--ink-3);">
                                <th style="text-align:left; padding: 10px 18px;">{{ __('Date') }}</th>
                                <th style="text-align:left; padding: 10px 10px;">{{ __('Homestay') }}</th>
                                <th style="text-align:left; padding: 10px 10px;">{{ __('Source') }}</th>
                                <th style="text-align:right; padding: 10px 10px;">{{ __('Payment') }}</th>
                                <th style="text-align:right; padding: 10px 10px;">{{ __('Commission') }}</th>
                                <th style="text-align:left; padding: 10px 10px;">{{ __('Status') }}</th>
                                <th style="text-align:right; padding: 10px 18px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($commissions as $c)
                                <tr style="border-top:.5px solid var(--line);">
                                    <td style="padding: 11px 18px; white-space:nowrap; color:var(--ink-2);">{{ $c->created_at->format('j M Y') }}</td>
                                    <td style="padding: 11px 10px; color:var(--ink-2);">{{ $c->tenant?->business_name ?? '—' }}</td>
                                    <td style="padding: 11px 10px; font-family: var(--font-mono, monospace); font-size:11.5px; color:var(--ink-3);">{{ $c->source }}@if($c->payout_ref) · {{ $c->payout_ref }}@endif</td>
                                    <td style="padding: 11px 10px; text-align:right;">RM {{ number_format((float) $c->base_amount, 2) }}</td>
                                    <td style="padding: 11px 10px; text-align:right; font-weight:600; color:var(--ink);">RM {{ number_format((float) $c->amount, 2) }}</td>
                                    <td style="padding: 11px 10px;">
                                        <span class="pill {{ $statusTone[$c->status] ?? '' }}" style="height:20px; font-size:11px;">{{ $c->statusLabel() }}</span>
                                    </td>
                                    <td style="padding: 11px 18px; text-align:right;">
                                        @if (in_array($c->status, [\App\Models\AffiliateCommission::STATUS_PENDING, \App\Models\AffiliateCommission::STATUS_APPROVED], true))
                                            <form method="POST" action="{{ route('platform.affiliates.void', [$affiliate, $c->id]) }}" onsubmit="return confirm('{{ __('Void this commission?') }}');" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-sm" style="color: var(--err);">{{ __('Void') }}</button>
                                            </form>
                                        @endif
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
