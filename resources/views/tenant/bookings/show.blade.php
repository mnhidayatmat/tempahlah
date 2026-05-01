<x-app-layout :title="__('Booking #:id', ['id' => $booking->id])">
    @php
        $ps = $booking->payment_status ?? 'pending';
        $variant = $ps === 'paid' ? 'ok' : ($ps === 'pending' ? 'warn' : 'err');
        $nights = \Carbon\Carbon::parse($booking->check_in)->diffInDays(\Carbon\Carbon::parse($booking->check_out));
    @endphp
    <div style="display:flex; flex-direction:column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.bookings.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Bookings') }}</a>
            <div style="display:flex; align-items:flex-end; justify-content:space-between; margin-top: 6px;">
                <div>
                    <div class="kicker">{{ __('Booking') }} #{{ $booking->id }}</div>
                    <h2 class="display-2" style="margin: 4px 0 0;">{{ $booking->guest_name ?? __('Guest') }}</h2>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <x-pill :variant="$variant" :dot="true">{{ ucfirst($ps) }}</x-pill>
                    <button class="btn btn-sm">{{ __('Send reminder') }}</button>
                    <button class="btn btn-primary btn-sm">{{ __('Mark paid') }}</button>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 18px;">
            <div style="display:flex; flex-direction:column; gap: 14px;">
                <div class="hauz-card" style="padding: 18px;">
                    <div class="kicker" style="margin-bottom: 10px;">{{ __('Stay') }}</div>
                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 14px;">
                        <div>
                            <div style="font-size: 11px; color: var(--ink-3); margin-bottom: 4px;">{{ __('Check-in') }}</div>
                            <div style="font-family: var(--font-display); font-size: 20px;">{{ \Carbon\Carbon::parse($booking->check_in)->format('d M') }}</div>
                            <div class="mono" style="font-size: 11.5px; color: var(--ink-3);">{{ \Carbon\Carbon::parse($booking->check_in)->format('Y') }} · 3pm</div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--ink-3); margin-bottom: 4px;">{{ __('Check-out') }}</div>
                            <div style="font-family: var(--font-display); font-size: 20px;">{{ \Carbon\Carbon::parse($booking->check_out)->format('d M') }}</div>
                            <div class="mono" style="font-size: 11.5px; color: var(--ink-3);">{{ \Carbon\Carbon::parse($booking->check_out)->format('Y') }} · 12pm</div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--ink-3); margin-bottom: 4px;">{{ __('Nights') }}</div>
                            <div style="font-family: var(--font-display); font-size: 20px;">{{ $nights }}</div>
                            <div class="mono" style="font-size: 11.5px; color: var(--ink-3);">{{ $booking->guests ?? 1 }} {{ __('guests') }}</div>
                        </div>
                    </div>
                </div>

                <div class="hauz-card" style="padding: 18px;">
                    <div class="kicker" style="margin-bottom: 10px;">{{ __('Property') }}</div>
                    <div style="display:flex; gap: 12px; align-items:center;">
                        @if ($booking->property)
                            <x-property-visual :property="$booking->property" :size="48"/>
                            <div>
                                <div style="font-weight: 500;">{{ $booking->property->name }}</div>
                                <div style="font-size: 12px; color: var(--ink-3);">{{ $booking->property->city ?? '—' }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap: 14px;">
                <div class="hauz-card" style="padding: 16px;">
                    <div class="kicker" style="margin-bottom: 8px;">{{ __('Total') }}</div>
                    <div style="display:flex; align-items:baseline; gap: 4px; margin-bottom: 12px;">
                        <span class="mono" style="font-size: 13px; color: var(--ink-3);">RM</span>
                        <span style="font-family: var(--font-display); font-size: 32px; line-height: 1;">{{ number_format($booking->total_amount ?? 0) }}</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap: 5px; font-size: 12.5px;">
                        <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Subtotal') }}</span><span class="mono">RM{{ number_format($booking->subtotal ?? $booking->total_amount ?? 0) }}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Deposit') }}</span><span class="mono">RM{{ number_format($booking->deposit_amount ?? 0) }}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Source') }}</span><span>{{ ucfirst($booking->source ?? 'direct') }}</span></div>
                    </div>
                </div>

                <div class="hauz-card" style="padding: 16px;">
                    <div class="kicker" style="margin-bottom: 8px;">{{ __('Contact') }}</div>
                    <div style="font-size: 13px;">{{ $booking->guest_name ?? '—' }}</div>
                    <div style="font-size: 12px; color: var(--ink-3); margin-bottom: 4px;">{{ $booking->guest_email ?? '' }}</div>
                    @if (! empty($booking->guest_phone))
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $booking->guest_phone) }}" class="btn btn-sm" style="margin-top: 6px;">
                            WhatsApp →
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
