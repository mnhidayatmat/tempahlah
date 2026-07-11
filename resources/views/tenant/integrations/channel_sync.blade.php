<x-app-layout :title="__('Airbnb & Booking.com sync')">
    <div style="max-width: 780px; margin: 0 auto; display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div>
            <a href="{{ route('tenant.integrations.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Integrations') }}</a>
            <div style="display:flex; align-items:flex-end; justify-content:space-between; margin-top: 6px; gap: 12px; flex-wrap: wrap;">
                <div>
                    <div class="kicker">{{ __('Configure') }}</div>
                    <h2 class="display-2" style="margin: 4px 0 0;">{{ __('Airbnb & Booking.com sync') }}</h2>
                    <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px; max-width: 560px;">
                        {{ __('Keep your calendars in step both ways. A booking on any channel blocks those dates everywhere, so you never get double-booked.') }}
                    </p>
                </div>
                <x-pill variant="pro"><x-icon name="sparkle" :size="10"/> Pro</x-pill>
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--ok); background: var(--ok-tint); color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <ul style="margin:0; padding-left: 18px;">@foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- How it works --}}
        <div class="hauz-card" style="padding: 18px 20px;">
            <div class="kicker" style="margin-bottom: 8px;">{{ __('How it works') }}</div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px; font-size: 12.5px; color: var(--ink-2); line-height: 1.6;">
                <div>
                    <div style="font-weight:600; color: var(--ink); margin-bottom: 2px;">① {{ __('Export (out)') }}</div>
                    {{ __('Copy each room\'s Tempahlah calendar link and paste it into Airbnb and Booking.com. They\'ll block the dates you\'re already booked.') }}
                </div>
                <div>
                    <div style="font-weight:600; color: var(--ink); margin-bottom: 2px;">② {{ __('Import (in)') }}</div>
                    {{ __('Paste each channel\'s calendar link below. We check them every hour and block those dates here automatically.') }}
                </div>
            </div>
        </div>

        @if ($properties->isEmpty() || $properties->every(fn ($p) => $p->rooms->isEmpty()))
            <div class="hauz-card" style="padding: 24px; text-align:center; color: var(--ink-3); font-size: 13px;">
                {{ __('Add a homestay with at least one room first, then come back to connect it.') }}
                <div style="margin-top: 12px;"><a href="{{ route('tenant.properties.create') }}" class="btn btn-sm btn-primary">{{ __('Add homestay') }}</a></div>
            </div>
        @endif

        {{-- Per room --}}
        @foreach ($properties as $property)
            @foreach ($property->rooms as $room)
                @php
                    $exportUrl = $room->icalExportUrl();
                    $airbnb  = $links[$room->id.':airbnb'] ?? null;
                    $booking = $links[$room->id.':booking'] ?? null;
                @endphp
                <div class="hauz-card" style="padding: 22px; display:flex; flex-direction:column; gap: 18px;">
                    <div style="display:flex; align-items:baseline; justify-content:space-between; gap: 10px; flex-wrap: wrap;">
                        <div>
                            <span style="font-weight: 700; font-size: 15px;">{{ $room->name }}</span>
                            <span style="font-size: 12px; color: var(--ink-3);">· {{ $property->name }}</span>
                        </div>
                    </div>

                    {{-- ① Export feed --}}
                    <div>
                        <div class="kicker" style="margin-bottom: 6px;">① {{ __('Your Tempahlah calendar link') }}</div>
                        <p style="font-size: 12px; color: var(--ink-3); margin: 0 0 8px;">
                            {{ __('Paste this into both Airbnb and Booking.com so they block your booked dates.') }}
                        </p>
                        <div style="display:flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                            <input class="input mono" type="text" readonly value="{{ $exportUrl }}"
                                   onclick="this.select()"
                                   style="flex:1; min-width: 240px; font-size: 12px;">
                            <x-housekeeping.copy-button :text="$exportUrl" :label="__('Copy link')" />
                            <form method="POST" action="{{ route('tenant.integrations.channel-sync.rotate', $room->public_id) }}"
                                  onsubmit="return confirm('{{ __('Generate a new link? The old one stops working — you\'ll need to update it in Airbnb and Booking.com.') }}');">
                                @csrf
                                <button type="submit" class="btn btn-sm" title="{{ __('Generate a new link if this one leaks') }}">{{ __('Reset') }}</button>
                            </form>
                        </div>
                    </div>

                    {{-- ② Import URLs --}}
                    <form method="POST" action="{{ route('tenant.integrations.channel-sync.update', $room->public_id) }}"
                          style="display:flex; flex-direction:column; gap: 14px; border-top: 1px solid var(--line); padding-top: 16px;">
                        @csrf
                        <div class="kicker">② {{ __('Import from your channels') }}</div>

                        {{-- Airbnb --}}
                        <div>
                            <label style="display:block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">{{ __('Airbnb calendar URL') }}</label>
                            <input class="input mono" type="url" name="airbnb_url" placeholder="https://www.airbnb.com/calendar/ical/...ics"
                                   value="{{ old('airbnb_url', $airbnb?->ical_import_url) }}" style="font-size: 12px;">
                            <x-channel.sync-status :link="$airbnb" />
                        </div>

                        {{-- Booking.com --}}
                        <div>
                            <label style="display:block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">{{ __('Booking.com calendar URL') }}</label>
                            <input class="input mono" type="url" name="booking_url" placeholder="https://admin.booking.com/hotel/hoteladmin/ical.html?..."
                                   value="{{ old('booking_url', $booking?->ical_import_url) }}" style="font-size: 12px;">
                            <x-channel.sync-status :link="$booking" />
                        </div>

                        <div style="display:flex; gap: 8px; align-items:center; flex-wrap: wrap;">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                            @if (($airbnb && $airbnb->active) || ($booking && $booking->active))
                                <button type="submit" class="btn btn-sm"
                                        formaction="{{ route('tenant.integrations.channel-sync.sync', $room->public_id) }}">
                                    {{ __('Sync now') }}
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            @endforeach
        @endforeach

        {{-- Step-by-step guidance --}}
        <div class="hauz-card" style="padding: 22px;">
            <div class="kicker" style="margin-bottom: 12px;">{{ __('Step-by-step') }}</div>

            <div style="display:flex; flex-direction:column; gap: 18px; font-size: 12.5px; color: var(--ink-2); line-height: 1.65;">
                <div>
                    <div style="font-weight:700; color: var(--ink); margin-bottom: 6px;">🅰️ {{ __('Airbnb') }}</div>
                    <div style="font-weight:600; color: var(--ink-2);">{{ __('Send Airbnb your Tempahlah dates:') }}</div>
                    <ol style="margin: 4px 0 10px; padding-left: 20px;">
                        <li>{{ __('Airbnb → Menu → Listings → pick your listing → Availability → Connect calendars.') }}</li>
                        <li>{{ __('Choose "Import calendar", paste this room\'s Tempahlah link above, name it "Tempahlah", and Import.') }}</li>
                    </ol>
                    <div style="font-weight:600; color: var(--ink-2);">{{ __('Bring Airbnb bookings into Tempahlah:') }}</div>
                    <ol style="margin: 4px 0 0; padding-left: 20px;">
                        <li>{{ __('On that same "Connect calendars" screen choose "Export calendar" and copy the link Airbnb shows.') }}</li>
                        <li>{{ __('Paste it into the "Airbnb calendar URL" box above and Save.') }}</li>
                    </ol>
                </div>

                <div style="border-top: 1px solid var(--line); padding-top: 16px;">
                    <div style="font-weight:700; color: var(--ink); margin-bottom: 6px;">🅱️ {{ __('Booking.com') }}</div>
                    <div style="font-weight:600; color: var(--ink-2);">{{ __('Send Booking.com your Tempahlah dates:') }}</div>
                    <ol style="margin: 4px 0 10px; padding-left: 20px;">
                        <li>{{ __('Booking.com Extranet → Rates & Availability → Sync calendars (iCal).') }}</li>
                        <li>{{ __('Click "Import calendar", paste this room\'s Tempahlah link, name it "Tempahlah", and Import.') }}</li>
                    </ol>
                    <div style="font-weight:600; color: var(--ink-2);">{{ __('Bring Booking.com reservations into Tempahlah:') }}</div>
                    <ol style="margin: 4px 0 0; padding-left: 20px;">
                        <li>{{ __('On the same Sync calendars page, copy the "Export calendar" link Booking.com gives you.') }}</li>
                        <li>{{ __('Paste it into the "Booking.com calendar URL" box above and Save.') }}</li>
                    </ol>
                </div>

                <div style="border-top: 1px solid var(--line); padding-top: 14px; font-size: 12px; color: var(--ink-3);">
                    <strong style="color: var(--ink-2);">{{ __('Good to know:') }}</strong>
                    {{ __('Channels refresh iCal calendars on their own schedule — usually every few hours, not instantly. For same-day changes, block the dates manually on the other channel too. We re-check your imported calendars every hour.') }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
