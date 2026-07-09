<x-app-layout :title="__('Edit booking :ref', ['ref' => $booking->reference])">
    @php
        $lead = $booking->bookingGuests->firstWhere('is_lead', true) ?? $booking->bookingGuests->first();
        $gName    = old('guest_name', $lead?->full_name ?? $booking->guest?->name);
        $gEmail   = old('guest_email', $lead?->email ?? $booking->guest?->email);
        $gPhone   = old('guest_phone', $lead?->phone ?? $booking->guest?->phone);
        $gCountry = old('guest_country', $lead?->country ?? 'MY');
        $isForeigner = (bool) old('is_foreigner', $booking->is_foreigner);
    @endphp

    <style>
        /* A grid item's automatic minimum is its content width, so a plain `1fr`
           column can't shrink below a long <select> option — the tracks locked at
           170px and pushed the form off a 360px screen. minmax(0,1fr) lets them
           shrink; on phones the fields stack for legibility. */
        .bke-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .bke-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        @media (max-width: 640px) {
            .bke-grid-2 { grid-template-columns: 1fr; }
        }
    </style>

    <div style="max-width: 880px; margin: 0 auto; display:flex; flex-direction:column; gap:20px;">

        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap;">
            <div>
                <a href="{{ route('tenant.bookings.show', $booking->id) }}" style="font-size:13px; color:var(--ink-3); text-decoration:none;">← {{ __('Back to booking') }}</a>
                <div class="kicker" style="margin-top:8px;">{{ $booking->reference }}</div>
                <h2 class="display-2" style="margin: 4px 0 0;">{{ __('Edit booking') }}</h2>
                <p style="color:var(--ink-3); font-size:13px; margin:6px 0 0;">
                    {{ __('Amounts are edited directly — they are not recomputed from the room rate, so agreed/historical prices stay intact.') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding:12px 16px; background:var(--err-tint); color:var(--err); border-color:var(--err); font-size:13px;">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="hauz-card" style="padding:12px 16px; background:var(--err-tint); color:var(--err); border-color:var(--err); font-size:13px;">
                <strong>{{ __('Please correct the following:') }}</strong>
                <ul style="margin:6px 0 0 18px;">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tenant.bookings.update', $booking->id) }}" style="display:flex; flex-direction:column; gap:14px;">
            @csrf
            @method('PATCH')

            {{-- Stay --}}
            <div class="hauz-card" style="padding:20px;">
                <div class="kicker" style="margin-bottom:12px;">{{ __('Stay') }}</div>
                <div class="bke-grid-2">
                    <label style="grid-column: 1 / -1;">
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Room') }}</div>
                        <select name="room_id" required class="input">
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected(old('room_id', $booking->room_id) == $room->id)>
                                    {{ $room->property?->name ?? '—' }} · {{ $room->name }} · RM {{ number_format((float) $room->base_price, 0) }}/{{ __('night') }} · {{ __('sleeps') }} {{ ($room->max_adults ?? 0) + ($room->max_children ?? 0) }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Check-in') }}</div>
                        <input type="date" name="check_in" required value="{{ old('check_in', $booking->check_in->toDateString()) }}" class="input">
                    </label>
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Check-out') }}</div>
                        <input type="date" name="check_out" required value="{{ old('check_out', $booking->check_out->toDateString()) }}" class="input">
                    </label>

                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Adults') }}</div>
                        <input type="number" name="adults" required min="1" max="60" value="{{ old('adults', $booking->adults) }}" class="input">
                    </label>
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Children') }}</div>
                        <input type="number" name="children" min="0" max="60" value="{{ old('children', $booking->children) }}" class="input">
                    </label>
                </div>
            </div>

            {{-- Guest --}}
            <div class="hauz-card" style="padding:20px;">
                <div class="kicker" style="margin-bottom:12px;">{{ __('Guest') }}</div>
                <div class="bke-grid-2">
                    <label style="grid-column: 1 / -1;">
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Full name') }}</div>
                        <input type="text" name="guest_name" required maxlength="120" value="{{ $gName }}" class="input" placeholder="{{ __('As on IC / passport') }}">
                    </label>
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Email') }}</div>
                        <input type="email" name="guest_email" maxlength="160" value="{{ $gEmail }}" class="input" placeholder="guest@example.com">
                    </label>
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('WhatsApp / phone') }}</div>
                        <input type="tel" name="guest_phone" maxlength="30" value="{{ $gPhone }}" class="input" inputmode="tel" placeholder="0127964501">
                        <div style="font-size:11px; color:var(--ink-3); margin-top:4px;">{{ __('Just type the number, e.g. 0127964501 — no need for +6.') }}</div>
                    </label>
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Country') }}</div>
                        <select name="guest_country" class="input">
                            @foreach ([
                                'MY' => '🇲🇾 Malaysia',
                                'SG' => '🇸🇬 Singapore',
                                'ID' => '🇮🇩 Indonesia',
                                'TH' => '🇹🇭 Thailand',
                                'CN' => '🇨🇳 China',
                                'JP' => '🇯🇵 Japan',
                                'AU' => '🇦🇺 Australia',
                                'GB' => '🇬🇧 United Kingdom',
                                'US' => '🇺🇸 United States',
                                'OT' => '🌐 ' . __('Other'),
                            ] as $code => $label)
                                <option value="{{ $code }}" @selected($gCountry === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="grid-column: 1 / -1; display:flex; align-items:center; gap:8px; padding-top:4px; cursor:pointer;">
                        <input type="hidden" name="is_foreigner" value="0">
                        <input type="checkbox" name="is_foreigner" value="1" @checked($isForeigner) style="accent-color: var(--primary);">
                        <span style="font-size:13px;">{{ __('Foreign guest') }}</span>
                    </label>
                </div>
            </div>

            {{-- Booking details --}}
            <div class="hauz-card" style="padding:20px;">
                <div class="kicker" style="margin-bottom:12px;">{{ __('Booking details') }}</div>
                <div class="bke-grid-2">
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Payment Status') }}</div>
                        @php
                            $paymentChoices = \App\Models\Booking::paymentStatusOptions();
                            $currentPayment = old('payment_status', $booking->paymentStatusKey());
                        @endphp
                        <select name="payment_status" required class="input">
                            @foreach ($paymentChoices as $key => $label)
                                <option value="{{ $key }}" @selected($currentPayment === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Channel') }}</div>
                        <select name="channel" required class="input">
                            @foreach ([
                                \App\Models\Booking::CHANNEL_DIRECT      => __('Direct (WhatsApp / phone)'),
                                \App\Models\Booking::CHANNEL_MARKETPLACE => __('Marketplace'),
                                \App\Models\Booking::CHANNEL_WALK_IN     => __('Walk-in'),
                                \App\Models\Booking::CHANNEL_BOOKING     => 'Booking.com',
                                \App\Models\Booking::CHANNEL_AIRBNB      => 'Airbnb',
                            ] as $key => $label)
                                <option value="{{ $key }}" @selected(old('channel', $booking->channel ?? 'direct') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="grid-column: 1 / -1;">
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Special requests / notes (optional)') }}</div>
                        <textarea name="special_requests" rows="3" maxlength="1000" class="input" style="height:auto; padding:10px 12px; resize:vertical;" placeholder="{{ __('Late check-in, dietary needs, baby cot…') }}">{{ old('special_requests', $booking->special_requests) }}</textarea>
                    </label>
                </div>
            </div>

            {{-- Money --}}
            <div class="hauz-card" style="padding:20px;">
                <div class="kicker" style="margin-bottom:4px;">{{ __('Amounts (RM)') }}</div>
                <p style="font-size:11.5px; color:var(--ink-3); margin:0 0 12px;">
                    {{ __('Edited directly. Total is what the guest pays; deposit is the pay-now / booking-fee portion.') }}
                </p>
                <div class="bke-grid-3">
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Base (room)') }}</div>
                        <input type="number" name="base_amount" required min="0" step="0.01" value="{{ old('base_amount', number_format((float) $booking->base_amount, 2, '.', '')) }}" class="input mono">
                    </label>
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Total') }}</div>
                        <input type="number" name="total_amount" required min="0" step="0.01" value="{{ old('total_amount', number_format((float) $booking->total_amount, 2, '.', '')) }}" class="input mono">
                    </label>
                    <label>
                        <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Deposit') }}</div>
                        <input type="number" name="deposit_amount" min="0" step="0.01" value="{{ old('deposit_amount', number_format((float) $booking->deposit_amount, 2, '.', '')) }}" class="input mono">
                    </label>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('tenant.bookings.show', $booking->id) }}" class="btn" style="text-decoration:none;">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
