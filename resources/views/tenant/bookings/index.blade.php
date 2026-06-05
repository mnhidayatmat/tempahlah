<x-app-layout :title="__('Bookings')">
    @php
        $paymentState = function ($b) {
            if ($b->balance_paid_at) return ['key' => 'paid', 'variant' => 'ok', 'label' => __('Paid')];
            if ($b->deposit_paid_at) return ['key' => 'deposit', 'variant' => 'warn', 'label' => __('Deposit')];
            return ['key' => 'unpaid', 'variant' => 'err', 'label' => __('Unpaid')];
        };
        $statusOptions = \App\Models\Booking::statusLabels();
        // Border accent per status so the dropdown reads at a glance.
        $statusAccent = [
            'pending'     => 'var(--warn)',
            'confirmed'   => 'var(--primary)',
            'checked_in'  => 'var(--ok)',
            'checked_out' => 'var(--ink-3)',
            'cancelled'   => 'var(--err)',
            'no_show'     => 'var(--err)',
        ];
    @endphp

    <div style="display:flex; flex-direction:column; gap: 20px;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Reservations') }}</div>
                <h2 class="display-2" style="margin: 4px 0 0;">{{ __('Bookings') }}</h2>
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <div style="display:flex; gap:2px; padding:3px; background: var(--bg-elev); border-radius:999px; border:.5px solid var(--line);">
                    @foreach ([
                        'all' => __('All'),
                        'upcoming' => __('Upcoming'),
                        'checked-in' => __('Checked-in'),
                        'past' => __('Past'),
                    ] as $key => $label)
                        @php $active = $filter === $key; @endphp
                        <a href="{{ route('tenant.bookings.index', $key === 'all' ? [] : ['status' => $key]) }}"
                           class="btn btn-sm"
                           style="border:0; text-decoration:none; border-radius:999px;
                                  background: {{ $active ? 'var(--primary)' : 'transparent' }};
                                  color: {{ $active ? 'var(--primary-ink)' : 'var(--ink-2)' }};
                                  font-weight: {{ $active ? '600' : '500' }};">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('tenant.bookings.create') }}" class="btn btn-primary btn-sm" style="text-decoration:none;">
                    <x-icon name="plus" :size="12"/> {{ __('New booking') }}
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--ok); background: var(--ok-tint); color: var(--ok); font-size: 13px;">
                {{ session('status') }}
            </div>
        @endif

        @if (! $bookings || $bookings->isEmpty())
            <div class="hauz-card" style="padding: 48px; text-align:center;">
                <div style="font-family: var(--font-display); font-size: 24px; margin-bottom: 6px;">{{ __('No bookings yet') }}</div>
                <p style="margin: 0; color: var(--ink-3); font-size: 13px;">
                    @if ($filter === 'all')
                        {{ __('Bookings will appear here as guests reserve.') }}
                    @else
                        {{ __('No bookings match this filter — try a different one.') }}
                    @endif
                </p>
            </div>
        @else
            <div class="hauz-card" style="padding: 0; overflow:hidden;">
                <table style="width:100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: var(--bg-sunk);">
                            @foreach ([__('Guest'), __('Property'), __('Dates'), __('Status'), __('Channel'), __('Payment'), __('Total')] as $i => $h)
                                <th style="text-align: {{ $i === 6 ? 'right' : 'left' }}; padding: 10px 14px; font-weight:500; font-size:11px; color: var(--ink-3); text-transform: uppercase; letter-spacing:.08em;">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $b)
                            @php $ps = $paymentState($b); @endphp
                            <tr style="border-top: .5px solid var(--line);">
                                <td style="padding: 12px 14px;">
                                    <a href="{{ route('tenant.bookings.show', $b->id) }}" style="display:flex; align-items:center; gap:10px; text-decoration:none; color: inherit;">
                                        <x-avatar :name="$b->guest?->name ?? 'Guest'" :size="28"/>
                                        <div>
                                            <div style="font-weight:500;">{{ $b->guest?->name ?? __('Guest') }}</div>
                                            <div style="font-size: 11px; color: var(--ink-3);">{{ $b->reference }}</div>
                                        </div>
                                    </a>
                                </td>
                                <td style="padding: 12px 14px; color: var(--ink-2);">{{ $b->property?->name ?? '—' }}</td>
                                <td style="padding: 12px 14px;" class="mono">
                                    {{ $b->check_in->format('d M') }} – {{ $b->check_out->format('d M') }}
                                </td>
                                <td style="padding: 12px 14px;">
                                    <form method="POST" action="{{ route('tenant.bookings.update-status', $b->id) }}" style="margin:0;">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" onchange="this.form.submit()" title="{{ __('Change status') }}"
                                                style="font-size:12px; padding:5px 8px; border-radius:8px; cursor:pointer;
                                                       border:1px solid var(--line);
                                                       border-left:3px solid {{ $statusAccent[$b->status] ?? 'var(--line)' }};
                                                       background: var(--bg-elev); color: var(--ink-1);">
                                            @foreach ($statusOptions as $val => $label)
                                                <option value="{{ $val }}" @selected($b->status === $val)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <x-pill>{{ ucfirst((string) ($b->channel ?? 'direct')) }}</x-pill>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <x-pill :variant="$ps['variant']" :dot="true">{{ $ps['label'] }}</x-pill>
                                </td>
                                <td style="padding: 12px 14px; text-align:right;" class="mono">RM{{ number_format((float) $b->total_amount, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (method_exists($bookings, 'links'))
                <div>{{ $bookings->links() }}</div>
            @endif
        @endif
    </div>
</x-app-layout>
