<x-app-layout :title="__('New booking')">
    <div style="max-width: 880px; margin: 0 auto; display:flex; flex-direction:column; gap:20px;">

        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap;">
            <div>
                <a href="{{ route('tenant.bookings.index') }}" style="font-size:13px; color:var(--ink-3); text-decoration:none;">← {{ __('Back to bookings') }}</a>
                <div class="kicker" style="margin-top:8px;">{{ __('Reservations') }}</div>
                <h2 class="display-2" style="margin: 4px 0 0;">{{ __('New booking') }}</h2>
                <p style="color:var(--ink-3); font-size:13px; margin:6px 0 0;">
                    {{ __('Manually log a booking made via WhatsApp, walk-in or phone. The price is computed from the room rate, SST and tourism tax.') }}
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

        @if ($rooms->isEmpty())
            <div class="hauz-card" style="padding:48px; text-align:center;">
                <div style="font-family: var(--font-display); font-size: 22px; margin-bottom: 6px;">{{ __('No rooms yet') }}</div>
                <p style="margin:0 0 16px; color: var(--ink-3); font-size: 13px;">
                    {{ __('Create a property with at least one room first.') }}
                </p>
                <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary" style="text-decoration:none;">{{ __('Add a property') }}</a>
            </div>
        @else
            <form method="POST" action="{{ route('tenant.bookings.store') }}" style="display:flex; flex-direction:column; gap:14px;">
                @csrf

                {{-- Stay --}}
                <div class="hauz-card" style="padding:20px;">
                    <div class="kicker" style="margin-bottom:12px;">{{ __('Stay') }}</div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <label style="grid-column: 1 / 3;">
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Room') }}</div>
                            <select name="room_id" required class="input">
                                <option value="">{{ __('— select a room —') }}</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>
                                        {{ $room->property?->name ?? '—' }} · {{ $room->name }} · RM {{ number_format((float) $room->base_price, 0) }}/{{ __('night') }} · {{ __('sleeps') }} {{ ($room->max_adults ?? 0) + ($room->max_children ?? 0) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Check-in') }}</div>
                            <input type="date" name="check_in" required min="{{ $today }}" value="{{ old('check_in', $today) }}" class="input">
                        </label>
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Check-out') }}</div>
                            <input type="date" name="check_out" required min="{{ $tomorrow }}" value="{{ old('check_out', $tomorrow) }}" class="input">
                        </label>

                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Adults') }}</div>
                            <input type="number" name="adults" required min="1" max="30" value="{{ old('adults', 2) }}" class="input">
                        </label>
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Children') }}</div>
                            <input type="number" name="children" min="0" max="30" value="{{ old('children', 0) }}" class="input">
                        </label>
                    </div>
                </div>

                {{-- Guest --}}
                <div class="hauz-card" style="padding:20px;">
                    <div class="kicker" style="margin-bottom:12px;">{{ __('Guest') }}</div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <label style="grid-column: 1 / 3;">
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Full name') }}</div>
                            <input type="text" name="guest_name" required maxlength="120" value="{{ old('guest_name') }}" class="input" placeholder="{{ __('As on IC / passport') }}">
                        </label>
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Email') }}</div>
                            <input type="email" name="guest_email" maxlength="160" value="{{ old('guest_email') }}" class="input" placeholder="guest@example.com">
                        </label>
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('WhatsApp / phone') }}</div>
                            <input type="tel" name="guest_phone" maxlength="30" value="{{ old('guest_phone') }}" class="input" placeholder="+60 12-345 6789">
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
                                    <option value="{{ $code }}" @selected(old('guest_country', 'MY') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label style="grid-column: 1 / 3; display:flex; align-items:center; gap:8px; padding-top:4px; cursor:pointer;">
                            <input type="hidden" name="is_foreigner" value="0">
                            <input type="checkbox" name="is_foreigner" value="1" @checked(old('is_foreigner')) style="accent-color: var(--primary);">
                            <span style="font-size:13px;">{{ __('Foreign guest — apply RM 10/night tourism tax') }}</span>
                        </label>
                    </div>
                </div>

                {{-- Booking --}}
                <div class="hauz-card" style="padding:20px;">
                    <div class="kicker" style="margin-bottom:12px;">{{ __('Booking details') }}</div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px;">
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Channel') }}</div>
                            <select name="channel" required class="input">
                                @foreach ([
                                    \App\Models\Booking::CHANNEL_DIRECT => __('Direct (WhatsApp / phone)'),
                                    \App\Models\Booking::CHANNEL_MARKETPLACE => __('Marketplace'),
                                    \App\Models\Booking::CHANNEL_WALK_IN => __('Walk-in'),
                                ] as $key => $label)
                                    <option value="{{ $key }}" @selected(old('channel', 'direct') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Deposit %') }}</div>
                            <input type="number" name="deposit_pct" required min="0" max="100" step="1" value="{{ old('deposit_pct', 20) }}" class="input">
                        </label>
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Reminder days before') }}</div>
                            <input type="number" name="reminder_days" min="0" max="60" step="1" value="{{ old('reminder_days', 7) }}" class="input">
                        </label>
                        <label style="grid-column: 1 / 4;">
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Special requests (optional)') }}</div>
                            <textarea name="special_requests" rows="3" maxlength="1000" class="input" style="height:auto; padding:10px 12px; resize:vertical;" placeholder="{{ __('Late check-in, dietary needs, baby cot…') }}">{{ old('special_requests') }}</textarea>
                        </label>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <a href="{{ route('tenant.bookings.index') }}" class="btn" style="text-decoration:none;">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create booking') }}</button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
