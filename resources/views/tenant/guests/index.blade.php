<x-app-layout :title="__('Guests')">
    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Guest book') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Guests') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ trans_choice('{1} :count unique guest|[2,*] :count unique guests', $totalGuests, ['count' => $totalGuests]) }}
                    · {{ $returning }} {{ __('returning') }}
                    · RM {{ number_format($totalSpend, 0) }} {{ __('lifetime') }}
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="{{ route('tenant.guests.export', ['q' => $q]) }}" class="btn btn-sm">{{ __('Export CSV') }}</a>
            </div>
        </div>

        {{-- Quick stats --}}
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 14px;">
            <div class="hauz-card" style="padding: 16px;">
                <div class="kicker" style="margin-bottom: 8px;">{{ __('Total guests') }}</div>
                <div class="display-3" style="line-height: 1;">{{ $totalGuests }}</div>
                <div style="margin-top: 6px; font-size: 12px; color: var(--ink-3);">{{ __('across all properties') }}</div>
            </div>
            <div class="hauz-card" style="padding: 16px;">
                <div class="kicker" style="margin-bottom: 8px;">{{ __('Returning') }}</div>
                <div class="display-3" style="line-height: 1;">{{ $returning }}</div>
                <div style="margin-top: 6px; font-size: 12px; color: var(--ink-3);">
                    {{ $totalGuests > 0 ? round(($returning / $totalGuests) * 100) : 0 }}% {{ __('repeat rate') }}
                </div>
            </div>
            <div class="hauz-card" style="padding: 16px;">
                <div class="kicker" style="margin-bottom: 8px;">{{ __('Outstanding') }}</div>
                <div class="display-3" style="line-height: 1;">
                    <span class="mono" style="font-size: 14px; color: var(--ink-3); margin-right: 4px;">RM</span>{{ number_format($outstanding, 0) }}
                </div>
                <div style="margin-top: 6px; font-size: 12px; color: {{ $outstanding > 0 ? 'var(--warn)' : 'var(--ink-3)' }};">
                    {{ $outstanding > 0 ? __('across deposit/unpaid bookings') : __('all settled') }}
                </div>
            </div>
        </div>

        {{-- Search --}}
        <form method="GET" action="{{ route('tenant.guests.index') }}" style="position: relative; max-width: 360px;">
            <input class="input" name="q" value="{{ $q }}" placeholder="{{ __('Search by name or phone…') }}" style="height: 36px; padding-left: 32px; font-size: 13px;">
            <span style="position: absolute; left: 10px; top: 10px; color: var(--ink-3);">
                <x-icon name="search" :size="14"/>
            </span>
        </form>

        {{-- Table. On desktop it's an 8-column table in a horizontal scroll rail
             (`.hauz-card` sets `overflow:hidden`, which would otherwise clip the
             right-hand columns). Below 768px the same markup collapses into one
             card per guest — the <thead> hides and each cell shows its column
             name from `data-label`. One source of truth, so the table and the
             cards can never drift apart.
             The cells carry inline padding/text-align, which beats a class
             selector, so the mobile rules need `!important`. --}}
        <style>
            @media (max-width: 768px) {
                .g-scroll{ overflow-x: visible !important; }
                .g-table{ min-width: 0 !important; display: block; }
                .g-table thead{ display: none; }
                .g-table tbody{ display: block; }
                .g-table tbody tr{ display: block; padding: 6px 0; }
                .g-table tbody td{
                    display: flex; align-items: baseline; justify-content: space-between; gap: 14px;
                    padding: 5px 14px !important; text-align: left !important;
                }
                .g-table td[data-label]::before{
                    content: attr(data-label); flex: none;
                    font-size: 10.5px; font-weight: 600; letter-spacing: .05em;
                    text-transform: uppercase; color: var(--ink-3);
                }
                .g-table td.g-title{ display: block; padding-top: 12px !important; }
                .g-table td.g-actions{ justify-content: flex-end; padding-bottom: 12px !important; }
                .g-table td[colspan]{ display: block; text-align: center !important; }
            }
        </style>
        <div class="hauz-card" style="padding: 0;">
            <div class="g-scroll" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="g-table" style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 720px;">
                <thead>
                    <tr style="background: var(--bg-sunk);">
                        @foreach ([__('Guest'), __('Phone'), __('Stays'), __('Nights'), __('Lifetime spend'), __('Last stay'), __('Channels'), ''] as $i => $h)
                            <th style="text-align: {{ in_array($i, [2,3,4]) ? 'right' : 'left' }};
                                       padding: 10px 14px; font-weight: 500; font-size: 11px;
                                       color: var(--ink-3); text-transform: uppercase; letter-spacing: .08em;">
                                {{ $h }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($guests as $g)
                        <tr style="border-top: .5px solid var(--line);">
                            <td class="g-title" style="padding: 12px 14px;">
                                <div style="display:flex; align-items:center; gap: 10px;">
                                    <x-avatar :name="$g->name" :size="30"/>
                                    <div>
                                        <div style="font-weight: 500;">{{ $g->name }}</div>
                                        @if ($g->stays > 1)
                                            <span style="font-size: 10.5px; color: var(--accent); font-weight: 500;">
                                                ★ {{ __('Returning') }} · {{ $g->stays }}×
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="{{ __('Phone') }}" style="padding: 12px 14px; color: var(--ink-2);" class="mono">{{ $g->phone }}</td>
                            <td data-label="{{ __('Stays') }}" style="padding: 12px 14px; text-align: right;" class="mono">{{ $g->stays }}</td>
                            <td data-label="{{ __('Nights') }}" style="padding: 12px 14px; text-align: right;" class="mono">{{ $g->nights }}</td>
                            <td data-label="{{ __('Lifetime spend') }}" style="padding: 12px 14px; text-align: right; font-weight: 500;" class="mono">
                                RM {{ number_format($g->spend, 0) }}
                            </td>
                            <td data-label="{{ __('Last stay') }}" style="padding: 12px 14px; color: var(--ink-2);">
                                <div style="font-size: 12.5px;">{{ \Carbon\Carbon::parse($g->last_checkin)->format('M j') }}</div>
                                <div style="font-size: 11px; color: var(--ink-3);">{{ $g->last_property ?? '—' }}</div>
                            </td>
                            <td data-label="{{ __('Channels') }}" style="padding: 12px 14px;">
                                <div style="display:flex; gap: 4px; flex-wrap: wrap;">
                                    @foreach ($g->channels as $c)
                                        <span class="pill" style="height: 18px; font-size: 10.5px;">{{ ucfirst($c) }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="g-actions" style="padding: 12px 14px; text-align: right;">
                                @php $waNumber = preg_replace('/\D/', '', $g->phone ?? ''); @endphp
                                @if ($waNumber)
                                    <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener"
                                       class="btn btn-sm btn-ghost" style="color: var(--ink-3);"
                                       aria-label="{{ __('WhatsApp :name', ['name' => $g->name]) }}">
                                        <x-icon name="message" :size="13"/>
                                    </a>
                                @else
                                    <span style="font-size: 11px; color: var(--ink-4);">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding: 32px; text-align: center; color: var(--ink-3); font-size: 13px;">
                                @if ($q !== '')
                                    {{ __('No guests match ":q"', ['q' => $q]) }}
                                @else
                                    {{ __('No guests yet — they\'ll appear here once you have bookings.') }}
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
</x-app-layout>
