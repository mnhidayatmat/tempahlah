<x-app-layout :title="__('Bookings')">
    <div style="display:flex; flex-direction:column; gap: 20px;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between;">
            <div>
                <div class="kicker">{{ __('Reservations') }}</div>
                <h2 class="display-2" style="margin: 4px 0 0;">{{ __('Bookings') }}</h2>
            </div>
            <div style="display:flex; gap: 6px;">
                <button class="btn btn-sm" data-filter="all">{{ __('All') }}</button>
                <button class="btn btn-sm" data-filter="upcoming">{{ __('Upcoming') }}</button>
                <button class="btn btn-sm" data-filter="checked-in">{{ __('Checked-in') }}</button>
                <button class="btn btn-sm" data-filter="past">{{ __('Past') }}</button>
            </div>
        </div>

        @if (! $bookings || $bookings->isEmpty())
            <div class="hauz-card" style="padding: 48px; text-align:center;">
                <div style="font-family: var(--font-display); font-size: 24px; margin-bottom: 6px;">{{ __('No bookings yet') }}</div>
                <p style="margin: 0; color: var(--ink-3); font-size: 13px;">{{ __('Bookings will appear here as guests reserve.') }}</p>
            </div>
        @else
            <div class="hauz-card" style="padding: 0; overflow:hidden;">
                <table style="width:100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: var(--bg-sunk);">
                            <th style="text-align:left; padding: 10px 14px; font-weight:500; font-size:11px; color: var(--ink-3); text-transform: uppercase; letter-spacing:.08em;">{{ __('Guest') }}</th>
                            <th style="text-align:left; padding: 10px 14px; font-weight:500; font-size:11px; color: var(--ink-3); text-transform: uppercase; letter-spacing:.08em;">{{ __('Property') }}</th>
                            <th style="text-align:left; padding: 10px 14px; font-weight:500; font-size:11px; color: var(--ink-3); text-transform: uppercase; letter-spacing:.08em;">{{ __('Dates') }}</th>
                            <th style="text-align:left; padding: 10px 14px; font-weight:500; font-size:11px; color: var(--ink-3); text-transform: uppercase; letter-spacing:.08em;">{{ __('Source') }}</th>
                            <th style="text-align:left; padding: 10px 14px; font-weight:500; font-size:11px; color: var(--ink-3); text-transform: uppercase; letter-spacing:.08em;">{{ __('Payment') }}</th>
                            <th style="text-align:right; padding: 10px 14px; font-weight:500; font-size:11px; color: var(--ink-3); text-transform: uppercase; letter-spacing:.08em;">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $b)
                            @php
                                $ps = $b->payment_status ?? 'pending';
                                $variant = $ps === 'paid' ? 'ok' : ($ps === 'pending' ? 'warn' : 'err');
                            @endphp
                            <tr style="border-top: .5px solid var(--line);">
                                <td style="padding: 12px 14px;">
                                    <a href="{{ route('tenant.bookings.show', $b->id) }}" style="display:flex; align-items:center; gap:10px; text-decoration:none; color: inherit;">
                                        <x-avatar :name="$b->guest_name ?? 'Guest'" :size="28"/>
                                        <div>
                                            <div style="font-weight:500;">{{ $b->guest_name ?? __('Guest') }}</div>
                                            <div style="font-size: 11px; color: var(--ink-3);">{{ $b->guest_email ?? '' }}</div>
                                        </div>
                                    </a>
                                </td>
                                <td style="padding: 12px 14px; color: var(--ink-2);">{{ optional($b->property)->name ?? '—' }}</td>
                                <td style="padding: 12px 14px;" class="mono">
                                    {{ \Carbon\Carbon::parse($b->check_in)->format('d M') }} – {{ \Carbon\Carbon::parse($b->check_out)->format('d M') }}
                                </td>
                                <td style="padding: 12px 14px;">
                                    <x-pill>{{ ucfirst($b->source ?? 'direct') }}</x-pill>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <x-pill :variant="$variant" :dot="true">{{ ucfirst($ps) }}</x-pill>
                                </td>
                                <td style="padding: 12px 14px; text-align:right;" class="mono">RM{{ number_format($b->total_amount ?? 0) }}</td>
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
