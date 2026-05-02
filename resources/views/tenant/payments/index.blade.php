<x-app-layout :title="__('Payments')">
    @php
        $statusPill = function ($s) {
            return match ($s) {
                'succeeded' => ['cls' => 'pill-ok', 'label' => __('Succeeded')],
                'pending'   => ['cls' => 'pill-err', 'label' => __('Pending')],
                'processing'=> ['cls' => 'pill-warn', 'label' => __('Processing')],
                'failed'    => ['cls' => 'pill-err', 'label' => __('Failed')],
                'refunded'  => ['cls' => 'pill', 'label' => __('Refunded')],
                default     => ['cls' => 'pill-warn', 'label' => __('Scheduled')],
            };
        };
        $methodLabel = fn ($m) => match ($m) {
            'toyyibpay' => 'Toyyibpay',
            'manual'    => __('Bank transfer'),
            default     => ucfirst((string) $m),
        };
        $typeLabel = fn ($t) => match ($t) {
            'deposit' => __('Deposit'),
            'balance' => __('Balance'),
            'full'    => __('Full payment'),
            'refund'  => __('Refund'),
            default   => ucfirst((string) $t),
        };
    @endphp

    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Cash flow') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Payments') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ __('Last 30 days') }} · {{ trans_choice('{1} :count transaction|[2,*] :count transactions', $totalCount, ['count' => $totalCount]) }}
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="{{ route('tenant.payments.export') }}" class="btn btn-sm">{{ __('Export CSV') }}</a>
            </div>
        </div>

        {{-- Stats --}}
        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 14px;">
            @foreach ([
                [__('Collected'), $collected, '+18% vs last 30d', 'var(--ok)'],
                [__('Pending'), $pending, $pendingCount.' '.__('transactions'), 'var(--warn)'],
                [__('Gateway fees'), $fees, __('Toyyibpay 1% avg'), null],
                [__('Net payout'), $netPayout, __('All settled'), null],
            ] as [$label, $value, $sub, $tone])
                <div class="hauz-card" style="padding: 18px;">
                    <div class="kicker" style="margin-bottom: 8px;">{{ $label }}</div>
                    <div class="display-3" style="line-height: 1;">
                        <span class="mono" style="font-size: 14px; color: var(--ink-3); margin-right: 4px;">RM</span>{{ number_format($value, 2) }}
                    </div>
                    <div style="margin-top: 6px; font-size: 12px; color: {{ $tone ?? 'var(--ink-3)' }};">{{ $sub }}</div>
                </div>
            @endforeach
        </div>

        {{-- Transactions --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 14px 18px; border-bottom: .5px solid var(--line); display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 8px;">
                <div style="font-weight: 600; font-size: 14px;">{{ __('Transactions') }}</div>
                <div style="display:flex; gap: 6px;">
                    @foreach ([null => __('All'), 'succeeded' => __('Succeeded'), 'pending' => __('Pending')] as $key => $label)
                        @php $active = $filter === $key; @endphp
                        <a href="{{ route('tenant.payments.index', $key ? ['status' => $key] : []) }}"
                           class="btn btn-sm {{ $active ? '' : 'btn-ghost' }}"
                           style="text-decoration:none; {{ $active ? '' : 'color: var(--ink-3);' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 920px;">
                    <thead>
                        <tr style="background: var(--bg-sunk);">
                            @foreach ([__('Date'), __('Booking'), __('Guest'), __('Property'), __('Type'), __('Method'), __('Status'), __('Amount')] as $i => $h)
                                <th style="text-align: {{ $i === 7 ? 'right' : 'left' }};
                                           padding: 10px 14px; font-weight: 500; font-size: 11px;
                                           color: var(--ink-3); text-transform: uppercase; letter-spacing: .08em;">
                                    {{ $h }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $p)
                            @php $sp = $statusPill($p->status); @endphp
                            <tr style="border-top: .5px solid var(--line);">
                                <td style="padding: 12px 14px;" class="mono">{{ $p->created_at->format('M j') }}</td>
                                <td style="padding: 12px 14px;" class="mono">{{ $p->booking?->reference ?? '—' }}</td>
                                <td style="padding: 12px 14px; font-weight: 500;">{{ $p->booking?->guest?->name ?? '—' }}</td>
                                <td style="padding: 12px 14px; color: var(--ink-2);">{{ $p->booking?->property?->name ?? '—' }}</td>
                                <td style="padding: 12px 14px; color: var(--ink-2);">{{ $typeLabel($p->type) }}</td>
                                <td style="padding: 12px 14px; color: var(--ink-2);">{{ $methodLabel($p->method) }}</td>
                                <td style="padding: 12px 14px;">
                                    <span class="pill {{ $sp['cls'] }}"><span class="pill-dot"></span>{{ $sp['label'] }}</span>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 600;" class="mono">
                                    RM {{ number_format($p->amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="padding: 32px; text-align: center; color: var(--ink-3); font-size: 13px;">
                                    {{ __('No payments yet — they\'ll show here once guests pay deposits or balances.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
