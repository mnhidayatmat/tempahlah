<x-app-layout :title="__('Bookings')">
    {{-- Page-scoped responsive styles. Desktop keeps the data table; phones
         get a stacked-card list so nothing overflows horizontally — the page
         scrolls only vertically, as expected on mobile. --}}
    <style>
        .bk-desktop { display: block; }
        .bk-mobile  { display: none; }
        @media (max-width: 768px) {
            .bk-desktop { display: none; }
            .bk-mobile  { display: flex; flex-direction: column; gap: 10px; }
            /* Header: stack the title above the controls; let pills wrap. */
            .bk-head { flex-direction: column; align-items: stretch; gap: 12px; }
            .bk-filters { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .bk-filters::-webkit-scrollbar { display: none; }
            .bk-new { justify-content: center; }
        }
        /* Mobile booking card */
        .bk-card { display: block; padding: 14px; text-decoration: none; color: inherit; }
        .bk-card-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .bk-card-guest { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .bk-card-guest-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bk-card-total { font-weight: 700; white-space: nowrap; }
        .bk-card-meta { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
        .bk-card-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 12.5px; }
        .bk-card-row .lbl { color: var(--ink-3); text-transform: uppercase; letter-spacing: .06em; font-size: 10px; font-weight: 500; }
        .bk-card-pills { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
    </style>

    <div style="display:flex; flex-direction:column; gap: 20px;">
        <div class="bk-head" style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Reservations') }}</div>
                <h2 class="display-2" style="margin: 4px 0 0;">{{ __('Bookings') }}</h2>
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <div class="bk-filters" style="display:flex; gap:2px; padding:3px; background: var(--bg-elev); border-radius:999px; border:.5px solid var(--line);">
                    @foreach ([
                        'all' => __('All'),
                        'upcoming' => __('Upcoming'),
                        'checked-in' => __('Checked-in'),
                        'past' => __('Past'),
                    ] as $key => $label)
                        @php $active = $filter === $key; @endphp
                        <a href="{{ route('tenant.bookings.index', $key === 'all' ? [] : ['status' => $key]) }}"
                           class="btn btn-sm"
                           style="border:0; text-decoration:none; border-radius:999px; white-space:nowrap;
                                  background: {{ $active ? 'var(--primary)' : 'transparent' }};
                                  color: {{ $active ? 'var(--primary-ink)' : 'var(--ink-2)' }};
                                  font-weight: {{ $active ? '600' : '500' }};">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('tenant.bookings.create') }}" class="btn btn-primary btn-sm bk-new" style="text-decoration:none;">
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
            <div class="hauz-card" style="padding: 48px 24px; text-align:center;">
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
            {{-- ===== Desktop / tablet: data table ===== --}}
            <div class="hauz-card bk-desktop" style="padding: 0; overflow:hidden;">
                <div style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
                    <table style="width:100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: var(--bg-sunk);">
                                @foreach ([__('Guest'), __('Property'), __('Dates'), __('Payment Status'), __('Channel'), __('Total')] as $i => $h)
                                    <th style="text-align: {{ $i === 5 ? 'right' : 'left' }}; padding: 10px 14px; font-weight:500; font-size:11px; color: var(--ink-3); text-transform: uppercase; letter-spacing:.08em; white-space:nowrap;">{{ $h }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookings as $b)
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
                                    <td style="padding: 12px 14px; white-space:nowrap;" class="mono">
                                        {{ $b->check_in->format('d M') }} – {{ $b->check_out->format('d M') }}
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <x-pill :variant="$b->paymentStatusVariant()" :dot="true">{{ $b->paymentStatusLabel() }}</x-pill>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <x-pill>{{ ucfirst((string) ($b->channel ?? 'direct')) }}</x-pill>
                                    </td>
                                    <td style="padding: 12px 14px; text-align:right; white-space:nowrap;" class="mono">RM{{ number_format((float) $b->total_amount, 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ===== Mobile: stacked cards ===== --}}
            <div class="bk-mobile">
                @foreach ($bookings as $b)
                    <a href="{{ route('tenant.bookings.show', $b->id) }}" class="hauz-card bk-card">
                        <div class="bk-card-top">
                            <div class="bk-card-guest">
                                <x-avatar :name="$b->guest?->name ?? 'Guest'" :size="34"/>
                                <div style="min-width:0;">
                                    <div class="bk-card-guest-name">{{ $b->guest?->name ?? __('Guest') }}</div>
                                    <div style="font-size: 11px; color: var(--ink-3);" class="mono">{{ $b->reference }}</div>
                                </div>
                            </div>
                            <div class="bk-card-total mono">RM{{ number_format((float) $b->total_amount, 0) }}</div>
                        </div>

                        <div class="bk-card-meta">
                            <div class="bk-card-row">
                                <span class="lbl">{{ __('Property') }}</span>
                                <span style="text-align:right; color:var(--ink-2);">{{ $b->property?->name ?? '—' }}</span>
                            </div>
                            <div class="bk-card-row">
                                <span class="lbl">{{ __('Dates') }}</span>
                                <span class="mono">{{ $b->check_in->format('d M') }} – {{ $b->check_out->format('d M') }}</span>
                            </div>
                        </div>

                        <div class="bk-card-pills">
                            <x-pill :variant="$b->paymentStatusVariant()" :dot="true">{{ $b->paymentStatusLabel() }}</x-pill>
                            <x-pill>{{ ucfirst((string) ($b->channel ?? 'direct')) }}</x-pill>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if (method_exists($bookings, 'links'))
            <div>{{ $bookings->links() }}</div>
        @endif
    </div>
</x-app-layout>
