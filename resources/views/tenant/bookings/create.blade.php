<x-app-layout :title="__('New booking')">
    @php($isBM = app()->getLocale() === 'ms')

    <style>
        [x-cloak] { display: none !important; }
        .avail-cal-nav {
            width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--line);
            background: var(--bg-elev); color: var(--ink-2); cursor: pointer; font-size: 16px;
            line-height: 1; display: inline-flex; align-items: center; justify-content: center;
        }
        .avail-cal-nav:hover { background: var(--bg-sunk); }
        .avail-cal-nav:disabled { opacity: .4; cursor: not-allowed; }
        .avail-cal-week, .avail-cal-grid {
            display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px;
        }
        .avail-cal-wd {
            text-align: center; font-size: 10px; font-weight: 600; letter-spacing: .04em;
            color: var(--ink-3); text-transform: uppercase; padding-bottom: 4px;
        }
        .avail-cal-cell {
            aspect-ratio: 1 / 1; border: 1px solid var(--line); border-radius: 8px;
            background: var(--bg-elev); color: var(--ink); font-size: 13px; font-weight: 500;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            padding: 0; transition: background .1s, border-color .1s;
        }
        .avail-cal-cell:hover:not(:disabled) { background: var(--primary-tint); border-color: var(--primary); }
        .avail-cal-cell.is-empty { border: none; background: transparent; cursor: default; }
        .avail-cal-cell.is-past { color: var(--ink-4); background: transparent; border-color: transparent; cursor: not-allowed; }
        .avail-cal-cell.is-booked {
            background: var(--err-tint); color: var(--err); border-color: var(--err-tint);
            text-decoration: line-through; cursor: not-allowed;
        }
        .avail-cal-cell.is-range { background: var(--primary-tint); border-color: var(--primary-tint); }
        .avail-cal-cell.is-in, .avail-cal-cell.is-out {
            background: var(--primary); color: #fff; border-color: var(--primary); font-weight: 700;
        }
        .avail-cal-cell.is-today { box-shadow: inset 0 0 0 2px var(--primary); }
        .avail-cal-legend {
            display: flex; gap: 16px; flex-wrap: wrap; margin-top: 10px;
            font-size: 11px; color: var(--ink-3);
        }
        .avail-cal-legend span { display: inline-flex; align-items: center; gap: 6px; }
        .avail-cal-legend .dot { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }
        .avail-cal-legend .dot-free { background: var(--bg-elev); border: 1px solid var(--line); }
        .avail-cal-legend .dot-booked { background: var(--err-tint); border: 1px solid var(--err); }
        .avail-cal-legend .dot-sel { background: var(--primary); }
        .avail-cal-warn {
            margin-top: 10px; padding: 9px 12px; border-radius: var(--r-md);
            background: var(--err-tint); color: var(--err); border: 1px solid var(--err);
            font-size: 12px; font-weight: 500;
        }
    </style>

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
            <form method="POST" action="{{ route('tenant.bookings.store') }}" style="display:flex; flex-direction:column; gap:14px;"
                  x-data="{
                      roomFees: {{ Js::from($roomFees) }},
                      roomGuests: {{ Js::from($roomGuests) }},
                      bookedByRoom: {{ Js::from($roomBookedDates) }},
                      selectedRoom: '{{ old('room_id', $prefillRoomId ?? ($rooms->count() === 1 ? $rooms->first()->id : null)) }}',
                      checkIn: '{{ old('check_in', $prefillCheckIn) }}',
                      checkOut: '{{ old('check_out', $prefillCheckOut) }}',
                      today: '{{ $today }}',
                      tomorrow: '{{ $tomorrow }}',
                      picking: 'in',
                      calY: 2000,
                      calM: 0,
                      bookingFee: '{{ old('deposit_amount') }}',
                      adults: {{ old('adults') !== null ? (int) old('adults') : 'null' }},
                      children: {{ (int) old('children', 0) }},
                      maxGuests: 30,
                      paymentReceived: '{{ old('payment_received', 'none') }}',
                      weekdays: {{ Js::from($isBM ? ['Ahd','Isn','Sel','Rab','Kha','Jum','Sab'] : ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']) }},
                      applyGuestDefaults(id) {
                          // Follow the tenant's per-property guest setup: default
                          // the adults count to 'Default guests' and cap inputs at
                          // the room's 'Max guests' (sleeps capacity).
                          const g = this.roomGuests[id];
                          if (!g) return;
                          this.maxGuests = g.max;
                          this.adults = g.default;
                          if (this.children > g.max) this.children = g.max;
                      },
                      onRoomChange(id) {
                          this.selectedRoom = id;
                          // Pre-fill the booking fee from the property's fee unless
                          // the host has already typed a custom amount.
                          if (this.bookingFee === '' && this.roomFees[id] != null) {
                              this.bookingFee = this.roomFees[id];
                          }
                          this.applyGuestDefaults(id);
                          this.revalidateRange();
                      },
                      /* ---- Availability helpers (occupied nights = [check_in, check_out)) ---- */
                      pad(n) { return String(n).padStart(2, '0'); },
                      ymd(y, m, d) { return y + '-' + this.pad(m + 1) + '-' + this.pad(d); },
                      bookedSet() { return this.bookedByRoom[this.selectedRoom] || []; },
                      isBooked(d) { return this.bookedSet().includes(d); },
                      isPast(d) { return d < this.today; },
                      isCheckIn(d) { return d === this.checkIn; },
                      isCheckOut(d) { return d === this.checkOut; },
                      inRange(d) { return this.checkIn && this.checkOut && d > this.checkIn && d < this.checkOut; },
                      rangeWouldConflict(a, b) {
                          for (const x of this.bookedSet()) { if (x >= a && x < b) return true; }
                          return false;
                      },
                      rangeConflict() {
                          if (!this.checkIn || !this.checkOut) return false;
                          if (this.checkOut <= this.checkIn) return false;
                          return this.rangeWouldConflict(this.checkIn, this.checkOut);
                      },
                      revalidateRange() {
                          // On room change / typing, drop a range that no longer fits
                          // the selected room's availability so the host re-picks.
                          if (this.checkIn && this.isBooked(this.checkIn)) { this.checkIn = ''; this.checkOut = ''; this.picking = 'in'; return; }
                          if (this.rangeConflict()) { this.checkOut = ''; this.picking = 'out'; }
                      },
                      pickDay(d) {
                          if (!d || this.isPast(d) || this.isBooked(d)) return;
                          if (this.picking === 'out' && d > this.checkIn && !this.rangeWouldConflict(this.checkIn, d)) {
                              this.checkOut = d; this.picking = 'in'; return;
                          }
                          // Otherwise start a fresh range from this day.
                          this.checkIn = d; this.checkOut = ''; this.picking = 'out';
                      },
                      minCheckOut() {
                          if (!this.checkIn) return this.tomorrow;
                          const dt = new Date(this.checkIn + 'T00:00:00');
                          dt.setDate(dt.getDate() + 1);
                          return this.ymd(dt.getFullYear(), dt.getMonth(), dt.getDate());
                      },
                      afterCheckInChange() {
                          if (this.checkOut && this.checkOut <= this.checkIn) this.checkOut = '';
                          this.picking = this.checkOut ? 'in' : 'out';
                          this.focusMonth(this.checkIn);
                      },
                      focusMonth(d) {
                          const base = d || this.today;
                          const dt = new Date(base + 'T00:00:00');
                          this.calY = dt.getFullYear(); this.calM = dt.getMonth();
                      },
                      prevMonth() { if (this.calM === 0) { this.calM = 11; this.calY--; } else { this.calM--; } },
                      nextMonth() { if (this.calM === 11) { this.calM = 0; this.calY++; } else { this.calM++; } },
                      atMinMonth() { return this.ymd(this.calY, this.calM, 1) <= this.today.slice(0, 8) + '01'; },
                      monthLabel() {
                          return new Date(this.calY, this.calM, 1)
                              .toLocaleDateString('{{ $isBM ? 'ms-MY' : 'en-MY' }}', { month: 'long', year: 'numeric' });
                      },
                      monthDays() {
                          const first = new Date(this.calY, this.calM, 1);
                          const startDow = first.getDay();
                          const count = new Date(this.calY, this.calM + 1, 0).getDate();
                          const cells = [];
                          for (let i = 0; i < startDow; i++) cells.push(null);
                          for (let d = 1; d <= count; d++) cells.push(this.ymd(this.calY, this.calM, d));
                          return cells;
                      },
                  }"
                  x-init="
                      if (bookingFee === '' && selectedRoom && roomFees[selectedRoom] != null) bookingFee = roomFees[selectedRoom];
                      if (selectedRoom && roomGuests[selectedRoom]) {
                          maxGuests = roomGuests[selectedRoom].max;
                          if (adults === null) adults = roomGuests[selectedRoom].default;
                      }
                      if (adults === null) adults = {{ (int) $defaultGuests }};
                      focusMonth(checkIn);
                  ">
                @csrf

                {{-- Stay --}}
                <div class="hauz-card" style="padding:20px;">
                    <div class="kicker" style="margin-bottom:12px;">{{ __('Stay') }}</div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <label style="grid-column: 1 / 3;">
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Room') }}</div>
                            <select name="room_id" required class="input" x-model="selectedRoom" @change="onRoomChange($event.target.value)">
                                @if ($rooms->count() !== 1)
                                    <option value="">{{ __('— select a room —') }}</option>
                                @endif
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}" @selected(old('room_id', $prefillRoomId) == $room->id || $rooms->count() === 1)>
                                        {{ $room->property?->name ?? '—' }} · {{ $room->name }} · RM {{ number_format((float) $room->base_price, 0) }}/{{ __('night') }} · {{ __('sleeps') }} {{ ($room->max_adults ?? 0) + ($room->max_children ?? 0) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Check-in') }}</div>
                            <input type="date" name="check_in" required min="{{ $today }}" class="input" x-model="checkIn" @change="afterCheckInChange()">
                        </label>
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Check-out') }}</div>
                            <input type="date" name="check_out" required :min="minCheckOut()" class="input" x-model="checkOut">
                        </label>
                    </div>

                    {{-- Availability calendar — greys out nights already booked for
                         the selected room so the host can't double-book. --}}
                    <div class="avail-cal" style="margin-top:14px;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                            <div style="font-size:12px; color:var(--ink-2); font-weight:600;" x-text="monthLabel()"></div>
                            <div style="display:flex; gap:6px;">
                                <button type="button" class="avail-cal-nav" @click="prevMonth()" :disabled="atMinMonth()" aria-label="{{ __('Previous month') }}">‹</button>
                                <button type="button" class="avail-cal-nav" @click="nextMonth()" aria-label="{{ __('Next month') }}">›</button>
                            </div>
                        </div>

                        <div style="font-size:12px; color:var(--ink-3); margin-bottom:8px;">
                            <template x-if="!selectedRoom">
                                <span>{{ __('Select a room to see its availability.') }}</span>
                            </template>
                            <template x-if="selectedRoom">
                                <span x-text="picking === 'in' ? '{{ __('Tap a date to set check-in.') }}' : '{{ __('Now tap the check-out date.') }}'"></span>
                            </template>
                        </div>

                        <div class="avail-cal-week">
                            <template x-for="wd in weekdays" :key="wd">
                                <div class="avail-cal-wd" x-text="wd"></div>
                            </template>
                        </div>
                        <div class="avail-cal-grid">
                            <template x-for="(d, i) in monthDays()" :key="i">
                                <button type="button"
                                        class="avail-cal-cell"
                                        :class="{
                                            'is-empty': !d,
                                            'is-past': d && isPast(d),
                                            'is-booked': d && isBooked(d) && !isCheckOut(d),
                                            'is-range': d && inRange(d),
                                            'is-in': d && isCheckIn(d),
                                            'is-out': d && isCheckOut(d),
                                            'is-today': d && d === today,
                                        }"
                                        :disabled="!d || isPast(d) || isBooked(d)"
                                        @click="pickDay(d)"
                                        x-text="d ? Number(d.slice(8)) : ''"></button>
                            </template>
                        </div>

                        <div class="avail-cal-legend">
                            <span><i class="dot dot-free"></i>{{ __('Available') }}</span>
                            <span><i class="dot dot-booked"></i>{{ __('Booked') }}</span>
                            <span><i class="dot dot-sel"></i>{{ __('Your dates') }}</span>
                        </div>

                        <div x-show="rangeConflict()" x-cloak class="avail-cal-warn">
                            {{ __('These dates overlap an existing booking for this room. Pick different dates.') }}
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:14px;">
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Adults') }}</div>
                            <input type="number" name="adults" required min="1" :max="maxGuests" x-model.number="adults" class="input">
                        </label>
                        <label>
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Children') }}</div>
                            <input type="number" name="children" min="0" :max="maxGuests" x-model.number="children" class="input">
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
                            <input type="tel" name="guest_phone" maxlength="30" value="{{ old('guest_phone') }}" class="input" inputmode="tel" placeholder="0127964501">
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
                            <div style="font-size:12px; color:var(--ink-2); margin-bottom:6px; font-weight:500;">{{ __('Booking fee (RM)') }}</div>
                            <input type="number" name="deposit_amount" required min="0" max="1000000" step="0.01" x-model="bookingFee" class="input" placeholder="0.00">
                            <div style="font-size:11px; color:var(--ink-3); margin-top:5px;">{{ __('Upfront amount the guest pays to secure the booking.') }}</div>
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

                {{-- Payment — record a manual (cash / bank-transfer) payment now,
                     or leave as "Not paid yet" to collect later via reminder. --}}
                <div class="hauz-card" style="padding:20px;">
                    <div class="kicker" style="margin-bottom:4px;">{{ __('Payment') }}</div>
                    <p style="color:var(--ink-3); font-size:12px; margin:0 0 12px;">
                        {{ __('Did the guest already pay you directly (cash / bank transfer)? Record it now, or leave as “Not paid yet” to collect later.') }}
                    </p>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:10px;">
                        @foreach ([
                            'none'        => [__('Not paid yet'), __('Collect later — you can send a reminder')],
                            'booking_fee' => [__('Booking fee paid'), __('Confirms the booking')],
                            'full'        => [__('Fully paid'), __('Settles the whole amount')],
                        ] as $val => $opt)
                            <label style="cursor:pointer; position:relative; display:block;">
                                <input type="radio" name="payment_received" value="{{ $val }}" x-model="paymentReceived"
                                       style="position:absolute; opacity:0; width:0; height:0;">
                                <div style="border:1.5px solid var(--line); border-radius:var(--r-md); padding:16px 18px; height:100%; transition:border-color .12s, background .12s;"
                                     :style="paymentReceived === '{{ $val }}' ? 'border-color:var(--primary); background:var(--primary-tint);' : ''">
                                    <div style="font-size:13px; font-weight:600;">{{ $opt[0] }}</div>
                                    <div style="font-size:11px; color:var(--ink-3); margin-top:4px; line-height:1.4;">{{ $opt[1] }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; align-items:center; gap:12px;">
                    <span x-show="rangeConflict()" x-cloak style="font-size:12px; color:var(--err); font-weight:500;">
                        {{ __('Dates overlap an existing booking.') }}
                    </span>
                    <a href="{{ route('tenant.bookings.index') }}" class="btn" style="text-decoration:none;">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary" :disabled="rangeConflict()"
                            :style="rangeConflict() ? 'opacity:.5; cursor:not-allowed;' : ''">{{ __('Create booking') }}</button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
