<x-app-layout :title="__('Calendar')" :subtitle="__('Month view')" :breadcrumbs="[$property?->name ?? __('Calendar')]">
    @php
        // Mirror the bookings-list payment vocabulary so the calendar and the
        // list never disagree about the same booking: full payment in →
        // 'paid'; booking fee in OR the booking is confirmed (in this system a
        // confirmed booking means its booking fee has cleared) → 'deposit';
        // otherwise nothing paid yet → 'unpaid' (pending).
        $paymentStatus = function ($b) {
            if ($b->balance_paid_at) return 'paid';
            if ($b->deposit_paid_at || in_array($b->status, ['confirmed', 'checked_in', 'checked_out'], true)) return 'deposit';
            return 'unpaid';
        };
        $totalRoomsCount = $rooms->count() ?: 1;
    @endphp

    {{-- Mobile compaction: fit the whole month with minimal/no vertical scroll.
         Desktop (>640px) is untouched. Day cells shrink and swap guest-name
         chips for colour-coded occupancy dots; the summary slims to a stat
         strip; the day-detail panel stacks below the grid. --}}
    <style>
        .cal-weekday-abbr { display: none; }   /* desktop shows full weekday names */

        /* Day-detail room rows are clickable — booked rows open the booking's
           edit form, free rows start a pre-filled new booking. Signal it. */
        .cal-room-row { transition: border-color 120ms, background 120ms, box-shadow 120ms; cursor: pointer; }
        .cal-room-row:hover { border-color: var(--primary) !important; background: var(--bg-elev) !important; box-shadow: var(--sh-1); }
        .cal-room-row:hover .cal-room-edit { color: var(--primary); }

        @media (max-width: 640px) {
            .cal-root { gap: 10px !important; }
            .cal-toolbar { gap: 6px !important; }

            /* Summary → slim stat strip (identity + legend dropped; the
               property name is already in the page breadcrumb/title). */
            .cal-summary { padding: 10px 12px !important; gap: 10px !important; }
            .cal-summary-id { display: none !important; }
            .cal-summary-meta { width: 100%; }
            /* 4 stats in one nowrap row overflow a phone — lay them out as a
               2×2 grid so each cell has room and the labels don't spill. */
            .cal-summary-meta > div:first-child {
                width: 100%; display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 6px !important;
            }
            .cal-summary-meta > div:first-child > div {
                min-width: 0 !important; padding: 7px 9px !important;
            }
            .cal-summary-meta > div:first-child > div .cm-eyebrow {
                font-size: 8.5px !important; white-space: normal !important;
            }
            .cal-summary-meta > div:first-child > div .mono { font-size: 14px !important; }
            .cal-summary-meta > div:last-child { display: none !important; }  /* legend (shown as standalone strip below) */

            /* Standalone colour legend — the summary's inline legend is hidden
               on phones, so surface a compact centred strip instead. */
            .cal-legend-mobile { display: flex !important; }

            /* Detail panel stacks under the grid */
            .cal-main { grid-template-columns: 1fr !important; gap: 10px !important; }

            /* Weekday header: single letters, centered, slim */
            .cal-weekday-full { display: none; }
            .cal-weekday-abbr { display: inline; }
            .cal-weekday { padding: 7px 0 !important; text-align: center !important; font-size: 10px !important; }

            /* Day cells: perfect squares, dot-based occupancy */
            .cal-cells { gap: 4px !important; padding: 0 6px 8px !important; }
            .cal-cell-empty { min-height: 0 !important; aspect-ratio: 1 / 1 !important; }
            .cal-cell {
                aspect-ratio: 1 / 1 !important; min-height: 0 !important;
                padding: 4px !important; overflow: hidden !important;
                border-radius: 9px !important; gap: 2px !important;
            }
            .cal-cell-events { display: none !important; }  /* in/out shown on tap in detail panel */
            /* The "FULL" / "N/M" occupancy badge overflows the tiny square cells
               and overlaps the day number — drop it on phones; the colour dot(s)
               below already signal a booking, and full detail is one tap away. */
            .cal-cell-occ { display: none !important; }

            /* Guest-name chips → wrapped row of colour-coded dots */
            .cal-cell-chips {
                flex-direction: row !important; flex-wrap: wrap !important;
                gap: 3px !important; align-content: flex-start;
            }
            .cal-cell-chips > span { background: transparent !important; padding: 0 !important; gap: 0 !important; }
            .cal-cell-chips > span > span:first-child {
                width: 11px !important; height: 11px !important; font-size: 0 !important;  /* keep colour dot, drop initial */
            }
            .cal-cell-chips > span > span:nth-child(2) { display: none !important; }       /* drop guest name */
        }
    </style>

    <div class="cal-root" style="display:flex; flex-direction:column; gap:16px;">

        {{-- Toolbar --}}
        <div class="cal-toolbar" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            @if ($properties->count() > 1)
                <div class="card" style="display:flex; padding:3px; gap:2px;">
                    @foreach ($properties as $p)
                        @php $active = $p->id === $propertyId; @endphp
                        <a href="{{ route('tenant.calendar', ['property_id' => $p->id, 'cursor' => $cursor->format('Y-m')]) }}"
                           class="btn btn-sm"
                           style="border:0; border-radius: var(--r-sm); text-decoration:none;
                                  background: {{ $active ? 'var(--primary-tint)' : 'transparent' }};
                                  color: {{ $active ? 'var(--primary)' : 'var(--ink-2)' }};
                                  font-weight: {{ $active ? '600' : '500' }};">
                            {{ $p->name }}
                        </a>
                    @endforeach
                </div>
            @elseif ($property)
                <div style="font-size:14px; font-weight:600;">{{ $property->name }}</div>
            @endif

            <div style="flex:1;"></div>

            <a href="{{ route('tenant.calendar', ['property_id' => $propertyId, 'cursor' => $todayCursor]) }}"
               class="btn btn-sm">{{ __('Today') }}</a>

            <div style="display:flex; border: 1px solid var(--line-2); border-radius: var(--r-md); overflow:hidden;">
                <a href="{{ route('tenant.calendar', ['property_id' => $propertyId, 'cursor' => $prevCursor]) }}"
                   class="btn btn-sm" style="border-radius:0; border:0; background: var(--bg-elev);"
                   aria-label="{{ __('Previous month') }}">
                    <x-icon name="arrow-left" :size="12"/>
                </a>
                <div style="padding: 0 14px; display:flex; align-items:center;
                            border-left: 1px solid var(--line-2); border-right: 1px solid var(--line-2);
                            font-size:13px; font-weight:600; min-width:140px; justify-content:center;
                            background: var(--bg-elev);">
                    {{ $monthLabel }}
                </div>
                <a href="{{ route('tenant.calendar', ['property_id' => $propertyId, 'cursor' => $nextCursor]) }}"
                   class="btn btn-sm" style="border-radius:0; border:0; background: var(--bg-elev);"
                   aria-label="{{ __('Next month') }}">
                    <x-icon name="arrow-right" :size="12"/>
                </a>
            </div>

            <a href="{{ route('tenant.bookings.create') }}" class="btn btn-sm btn-primary">
                <x-icon name="plus" :size="12"/> {{ __('New booking') }}
            </a>
        </div>

        @if ($properties->isEmpty())
            <div class="card" style="padding:32px; text-align:center;">
                <div class="display-3" style="margin-bottom:6px;">{{ __('No properties to schedule') }}</div>
                <p style="margin:0 0 14px; color: var(--ink-3); font-size:13px;">{{ __('Add a property to start managing its calendar.') }}</p>
                <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary btn-sm">{{ __('Add property') }}</a>
            </div>
        @else

            {{-- Property summary card --}}
            @php
                // Whole-house properties carry a single synthetic "Whole house"
                // Room, so a raw room count reads as "1 room" — misleading. Show
                // "Whole house · N bedrooms" (bedrooms live on that room's `beds`).
                $isWholeHouse = (bool) $property?->isWholeHousePricing();
                $bedroomCount = (int) ($rooms->first()?->beds ?? 0);
                $unitLabel = $isWholeHouse
                    ? __('Whole house').($bedroomCount > 0
                        ? ' · '.trans_choice('{1} :n bedroom|[2,*] :n bedrooms', $bedroomCount, ['n' => $bedroomCount])
                        : '')
                    : trans_choice('{0} No rooms|{1} :n room|[2,*] :n rooms', $rooms->count(), ['n' => $rooms->count()]);

                $stats = [
                    ['label' => __('Occupancy').' · '.$cursor->format('M'), 'value' => $occupancyPct.'%'],
                    ['label' => __('Revenue'), 'value' => 'RM '.number_format($monthRevenue, 0)],
                    ['label' => __('Bookings'), 'value' => (string) $monthBookings],
                    ['label' => __('Nights booked'), 'value' => (string) $monthNights],
                ];
            @endphp
            <div class="card cal-summary" style="padding: 18px 20px; display:flex; gap:20px 28px; align-items:center; justify-content:space-between; flex-wrap:wrap;">

                {{-- Identity --}}
                <div class="cal-summary-id" style="min-width:0; flex: 1 1 240px; display:flex; flex-direction:column; gap:7px;">
                    <div style="display:inline-flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <span style="display:inline-flex; align-items:center; gap:4px; font-size:12px; color: var(--ink-3);">
                            <x-icon name="pin" :size="11"/>{{ $property?->city ?? '—' }}
                        </span>
                        <span style="display:inline-flex; align-items:center; gap:5px; height:21px; padding:0 9px;
                                     border-radius:999px; background: var(--primary-tint); color: var(--primary);
                                     font-size:11px; font-weight:600; white-space:nowrap;">
                            {{ $isWholeHouse ? '🏠' : '🛏️' }} {{ $unitLabel }}
                        </span>
                    </div>
                    <div style="font-family: var(--font-display); font-size:22px; font-weight:600; letter-spacing:-.02em; line-height:1.15; color: var(--ink);">
                        {{ $property?->name ?? $monthLabel }}
                    </div>
                </div>

                {{-- Stats + legend --}}
                <div class="cal-summary-meta" style="display:flex; flex-direction:column; align-items:flex-start; gap:12px;">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        @foreach ($stats as $s)
                            <div style="background: var(--bg-sunk); border:1px solid var(--line); border-radius:12px;
                                        padding:9px 14px; min-width:88px;">
                                <div class="cm-eyebrow" style="margin-bottom:3px; white-space:nowrap;">{{ $s['label'] }}</div>
                                <div class="mono" style="font-size:17px; font-weight:700; line-height:1; color: var(--ink);">{{ $s['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div style="display:flex; gap:14px; font-size:11px; flex-wrap:wrap;">
                        @foreach ([['Fully paid','var(--ok)'], ['Booking fee paid','var(--warn)'], ['Pending','var(--err)']] as [$lbl, $clr])
                            <span style="display:inline-flex; align-items:center; gap:5px; color: var(--ink-3);">
                                <span style="width:8px; height:8px; border-radius:999px; background: {{ $clr }};"></span>{{ __($lbl) }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Mobile colour legend — hidden on desktop (the summary card above
                 already carries the inline legend there); shown as a compact
                 centred strip on phones via the .cal-legend-mobile rule. --}}
            <div class="cal-legend-mobile card" style="display:none; padding:9px 12px; gap:16px;
                        align-items:center; justify-content:center; flex-wrap:wrap;">
                @foreach ([['Fully paid','var(--ok)'], ['Booking fee paid','var(--warn)'], ['Pending','var(--err)']] as [$lbl, $clr])
                    <span style="display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:600; color: var(--ink-2);">
                        <span style="width:10px; height:10px; border-radius:999px; background: {{ $clr }}; flex-shrink:0;"></span>{{ __($lbl) }}
                    </span>
                @endforeach
            </div>

            {{-- Main: grid + (optional) detail panel --}}
            <div class="cal-main" style="display:grid; grid-template-columns: {{ $selectedDay ? '1fr 340px' : '1fr' }}; gap:16px; align-items:flex-start; transition: grid-template-columns 200ms;">

                {{-- Month grid --}}
                <div class="card cal-grid-card" style="padding:0; overflow:hidden;"
                     data-cal-swipe
                     data-prev="{{ route('tenant.calendar', ['property_id' => $propertyId, 'cursor' => $prevCursor]) }}"
                     data-next="{{ route('tenant.calendar', ['property_id' => $propertyId, 'cursor' => $nextCursor]) }}">
                    {{-- Weekday header --}}
                    <div class="cal-weekdays" style="display:grid; grid-template-columns: repeat(7, minmax(0, 1fr)); padding: 4px 0; background:transparent;">
                        @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $wd)
                            <div class="cal-weekday" style="padding: 12px 14px; font-size:11px; font-weight:600;
                                        color: {{ ($i === 0 || $i === 6) ? 'var(--primary)' : 'var(--ink-3)' }};
                                        text-transform:uppercase; letter-spacing:.09em; text-align:left;">
                                <span class="cal-weekday-full">{{ __($wd) }}</span><span class="cal-weekday-abbr">{{ __(substr($wd, 0, 1)) }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Day cells --}}
                    <div class="cal-cells" style="display:grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap:6px; padding: 0 10px 14px;">
                        @foreach ($days as $d)
                            @if (! $d)
                                <div class="cal-cell-empty" style="min-height:128px;"></div>
                            @else
                                @php
                                    $iso = $d->toDateString();
                                    $isToday = $iso === $todayIso;
                                    $isWknd = $d->isWeekend();
                                    $isSelected = $iso === $selectedDay;
                                    $isOtherMonth = $d->month !== $cursor->month;
                                    $bks = $bookingsByDate[$iso] ?? [];
                                    $events = $eventsByDate[$iso] ?? ['checkins' => [], 'checkouts' => []];
                                    $occupied = count($bks);
                                    $totalRooms = $totalRoomsCount;
                                    // Cell tint follows PAYMENT STATUS (not a grey occupancy heat):
                                    // green = every booking fully paid, yellow = booking fee paid,
                                    // red = something still unpaid. Most-urgent status wins the cell.
                                    $dayStatuses = array_map($paymentStatus, $bks);
                                    $cellPs = $occupied === 0 ? null
                                        : (in_array('unpaid', $dayStatuses, true) ? 'unpaid'
                                            : (in_array('deposit', $dayStatuses, true) ? 'deposit' : 'paid'));
                                    $cellColor = $cellPs === 'paid' ? 'var(--ok)'
                                        : ($cellPs === 'deposit' ? 'var(--warn)'
                                        : ($cellPs === 'unpaid' ? 'var(--err)' : null));
                                    $heatBg = $cellColor
                                        ? 'color-mix(in srgb, '.$cellColor.' 16%, transparent)'
                                        : ($isWknd ? 'var(--bg-sunk)' : 'var(--bg-elev)');
                                    $cellHref = route('tenant.calendar', [
                                        'property_id' => $propertyId,
                                        'cursor' => $cursor->format('Y-m'),
                                        'day' => $iso,
                                    ]);
                                @endphp
                                <a href="{{ $cellHref }}" class="cal-cell"
                                   style="min-height:128px; padding:10px; text-decoration:none; color: var(--ink);
                                          background: {{ $isSelected ? 'linear-gradient(160deg, color-mix(in srgb, var(--primary) 22%, transparent), color-mix(in srgb, var(--primary) 8%, transparent))' : $heatBg }};
                                          border: 1px solid {{ $isSelected ? 'var(--primary)' : ($cellColor ? 'color-mix(in srgb, '.$cellColor.' 45%, var(--line))' : 'var(--line)') }};
                                          border-radius:14px;
                                          opacity: {{ $isOtherMonth ? '0.4' : '1' }};
                                          display:flex; flex-direction:column; gap:6px;
                                          position:relative;
                                          box-shadow: {{ $isSelected ? '0 8px 24px -10px color-mix(in srgb, var(--primary) 35%, transparent), 0 1px 0 color-mix(in srgb, var(--primary) 8%, transparent) inset' : 'var(--sh-1)' }};
                                          transition: transform 120ms, box-shadow 120ms;">
                                    <div class="cal-cell-head" style="display:flex; justify-content:space-between; align-items:center;">
                                        @if ($isToday)
                                            <span style="font-family: var(--font-display); font-size:15px; font-weight:600; letter-spacing:-.01em;
                                                         color: var(--primary-ink);
                                                         background: var(--primary);
                                                         width:24px; height:24px; border-radius:999px;
                                                         display:inline-flex; align-items:center; justify-content:center;
                                                         box-shadow: 0 2px 6px color-mix(in srgb, var(--primary) 30%, transparent);">
                                                {{ $d->day }}
                                            </span>
                                        @else
                                            <span style="font-family: var(--font-mono); font-size:13px; font-weight:600; color: var(--ink);">{{ $d->day }}</span>
                                        @endif
                                        @if ($occupied > 0)
                                            @php $full = $occupied === $totalRooms; @endphp
                                            <span class="cal-cell-occ" style="font-size:10px; font-weight:700;
                                                         color: {{ $full ? 'var(--primary)' : 'var(--ink-3)' }};
                                                         font-family: var(--font-mono);
                                                         background: {{ $full ? 'var(--primary-tint)' : 'transparent' }};
                                                         padding: {{ $full ? '1px 6px' : '0' }};
                                                         border-radius:999px; white-space:nowrap;">
                                                {{ $full ? 'FULL' : $occupied.'/'.$totalRooms }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Booking chips --}}
                                    <div class="cal-cell-chips" style="display:flex; flex-direction:column; gap:3px; flex:1; min-height:0;">
                                        @foreach (array_slice($bks, 0, 3) as $b)
                                            @php
                                                $ps = $paymentStatus($b);
                                                $col = $ps === 'paid' ? 'var(--ok)' : ($ps === 'deposit' ? 'var(--warn)' : 'var(--err)');
                                                $tint = $ps === 'paid' ? 'var(--ok-tint)' : ($ps === 'deposit' ? 'var(--warn-tint)' : 'var(--err-tint)');
                                                $guestName = $b->guest?->name ?? __('Guest');
                                                $firstName = explode(' ', $guestName)[0];
                                            @endphp
                                            <span title="{{ $guestName }} · {{ $b->room?->name ?? '' }}"
                                                  style="background: {{ $tint }}; padding: 3px 4px; border-radius:999px;
                                                         display:flex; align-items:center; gap:5px;
                                                         font-size:10.5px; font-weight:500; color: var(--ink);
                                                         overflow:hidden;">
                                                <span style="width:16px; height:16px; border-radius:999px;
                                                             background: {{ $col }}; color: white; flex-shrink:0;
                                                             display:inline-flex; align-items:center; justify-content:center;
                                                             font-size:8.5px; font-weight:700;">{{ strtoupper(substr($firstName, 0, 1)) }}</span>
                                                <span style="overflow:hidden; white-space:nowrap; text-overflow:ellipsis; min-width:0;">{{ $firstName }}</span>
                                            </span>
                                        @endforeach
                                        @if (count($bks) > 3)
                                            <span style="font-size:10px; color: var(--ink-3); padding-left:6px; font-style:italic;">
                                                +{{ count($bks) - 3 }} {{ __('more') }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Event indicators --}}
                                    @php
                                        $cIn = count($events['checkins'] ?? []);
                                        $cOut = count($events['checkouts'] ?? []);
                                    @endphp
                                    @if ($cIn > 0 || $cOut > 0)
                                        <div class="cal-cell-events" style="display:flex; gap:6px; margin-top:auto; align-items:center;">
                                            @if ($cIn > 0)
                                                <span style="display:inline-flex; align-items:center; gap:3px; font-size:9.5px; font-weight:700; color: var(--primary);">
                                                    <span style="width:6px; height:6px; border-radius:999px; background: var(--primary);"></span>
                                                    {{ $cIn }} {{ __('in') }}
                                                </span>
                                            @endif
                                            @if ($cOut > 0)
                                                <span style="display:inline-flex; align-items:center; gap:3px; font-size:9.5px; font-weight:700; color: var(--ink-3);">
                                                    <span style="width:6px; height:6px; border-radius:999px; background: var(--ink-4);"></span>
                                                    {{ $cOut }} {{ __('out') }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Day detail panel --}}
                @if ($selectedDay)
                    @php
                        $dayBookings = $bookingsByDate[$selectedDay] ?? [];
                        $dayEvents = $eventsByDate[$selectedDay] ?? ['checkins' => [], 'checkouts' => []];
                        $occ = count($dayBookings);
                        $free = $rooms->count() - $occ;
                        $dayRevenue = 0;
                        foreach ($dayBookings as $b) {
                            if ($b->nights > 0) $dayRevenue += $b->total_amount / $b->nights;
                        }
                        $selectedCarbon = \Carbon\Carbon::parse($selectedDay);
                        $occupiedRoomIds = collect($dayBookings)->pluck('room_id')->all();
                    @endphp
                    <div class="card cal-detail" style="padding:0; overflow:hidden; position:sticky; top:16px;">
                        {{-- Header --}}
                        <div style="padding: 16px 18px; border-bottom: .5px solid var(--line); background: var(--bg-sunk);
                                    display:flex; align-items:flex-start; justify-content:space-between;">
                            <div>
                                <div class="cm-eyebrow" style="margin-bottom:2px;">{{ $selectedCarbon->isoFormat('dddd') }}</div>
                                <div style="font-family: var(--font-display); font-size:28px; font-weight:700; letter-spacing:-.02em; line-height:1.1;">
                                    {{ $selectedCarbon->isoFormat('D MMMM') }}
                                </div>
                            </div>
                            <a href="{{ route('tenant.calendar', ['property_id' => $propertyId, 'cursor' => $cursor->format('Y-m')]) }}"
                               style="background:transparent; border:0; color: var(--ink-3); padding:4px; text-decoration:none;">
                                <x-icon name="x" :size="14"/>
                            </a>
                        </div>

                        {{-- Stats --}}
                        <div style="padding: 14px 18px; display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; border-bottom:.5px solid var(--line);">
                            <div>
                                <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Occupied') }}</div>
                                <div class="mono" style="font-size:17px; font-weight:700;">{{ $occ }}/{{ $rooms->count() }}</div>
                            </div>
                            <div>
                                <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Free') }}</div>
                                <div class="mono" style="font-size:17px; font-weight:700; color: {{ $free > 0 ? 'var(--ok)' : 'var(--primary)' }};">{{ $free }}</div>
                            </div>
                            <div>
                                <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Revenue') }}</div>
                                <div class="mono" style="font-size:17px; font-weight:700;">RM {{ round($dayRevenue) }}</div>
                            </div>
                        </div>

                        {{-- Events --}}
                        @if (! empty($dayEvents['checkins']) || ! empty($dayEvents['checkouts']))
                            <div style="padding: 14px 18px; border-bottom:.5px solid var(--line);">
                                <div class="cm-eyebrow" style="margin-bottom:8px;">{{ __("Today's events") }}</div>
                                <div style="display:flex; flex-direction:column; gap:6px;">
                                    @foreach ($dayEvents['checkins'] ?? [] as $b)
                                        <div style="display:flex; align-items:center; gap:8px; font-size:12.5px;">
                                            <span style="background: var(--primary); color: var(--primary-ink); padding: 2px 6px; border-radius:4px; font-size:9.5px; font-weight:600;">{{ __('CHECK-IN') }}</span>
                                            <span style="font-weight:500;">{{ $b->guest?->name ?? __('Guest') }}</span>
                                            <span style="color: var(--ink-3);">· {{ $b->room?->name }}</span>
                                        </div>
                                    @endforeach
                                    @foreach ($dayEvents['checkouts'] ?? [] as $b)
                                        <div style="display:flex; align-items:center; gap:8px; font-size:12.5px;">
                                            <span style="background: var(--bg-tint); color: var(--ink-2); padding: 2px 6px; border-radius:4px; font-size:9.5px; font-weight:600;">{{ __('CHECK-OUT') }}</span>
                                            <span style="font-weight:500;">{{ $b->guest?->name ?? __('Guest') }}</span>
                                            <span style="color: var(--ink-3);">· {{ $b->room?->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Per-room status — each row is clickable: a booked room
                             opens that booking's edit form, a free room starts a
                             new booking pre-filled with this room + date. --}}
                        <div style="padding: 14px 18px;">
                            <div style="display:flex; align-items:baseline; justify-content:space-between; gap:8px; margin-bottom:10px;">
                                <div class="cm-eyebrow">{{ __('Room status') }}</div>
                                <div style="font-size:10.5px; color: var(--ink-3);">{{ __('View or send documents') }}</div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:10px;">
                                @foreach ($rooms as $r)
                                    @php
                                        $bk = collect($dayBookings)->firstWhere('room_id', $r->id);
                                        $ps = $bk ? $paymentStatus($bk) : null;
                                        $col = $bk
                                            ? ($ps === 'paid' ? 'var(--ok)' : ($ps === 'deposit' ? 'var(--warn)' : 'var(--err)'))
                                            : 'var(--ok)';
                                    @endphp
                                    @if ($bk)
                                        {{-- Booked room → mini booking card with guest, payment,
                                             quick links to the full booking, and invoice/receipt actions. --}}
                                        <div style="background: var(--bg-sunk); border-radius:10px; border: .5px solid var(--line); border-left: 3px solid {{ $col }}; padding: 12px 14px;">
                                            <div style="display:flex; align-items:flex-start; gap:10px;">
                                                <div style="flex:1; min-width:0;">
                                                    <div style="font-size:13px; font-weight:600;">{{ $bk->guestName() ?? __('Guest') }}</div>
                                                    <div style="font-size:11px; color: var(--ink-3); margin-top:1px;">
                                                        {{ $r->name }} · {{ $bk->nights }}n · RM {{ number_format($bk->total_amount, 0) }}
                                                    </div>
                                                </div>
                                                <span class="pill" style="height:18px; font-size:10px;
                                                                          background: {{ $ps === 'paid' ? 'var(--ok-tint)' : ($ps === 'deposit' ? 'var(--warn-tint)' : 'var(--err-tint)') }};
                                                                          color: {{ $col }};">
                                                    {{ $ps === 'paid' ? __('Fully paid') : ($ps === 'deposit' ? __('Booking fee paid') : __('Pending')) }}
                                                </span>
                                            </div>

                                            {{-- Quick links to the full booking --}}
                                            <div style="display:flex; gap:8px; margin-top:10px;">
                                                <a href="{{ route('tenant.bookings.show', $bk->id) }}" class="btn btn-sm" style="flex:1; justify-content:center; text-decoration:none;">
                                                    {{ __('View booking') }} →
                                                </a>
                                                <a href="{{ route('tenant.bookings.edit', $bk->id) }}" class="btn btn-sm" style="text-decoration:none;" title="{{ __('Edit booking') }}">
                                                    <x-icon name="pencil" :size="13"/>
                                                </a>
                                            </div>

                                            {{-- Invoice & receipt (shared component) --}}
                                            <div style="margin-top:4px;">
                                                <x-booking.documents :booking="$bk" :compact="true"/>
                                            </div>
                                        </div>
                                    @else
                                        {{-- Free room → start a new booking pre-filled with this room + date. --}}
                                        <a href="{{ route('tenant.bookings.create', ['property_id' => $propertyId, 'check_in' => $selectedDay, 'room_id' => $r->id]) }}" class="cal-room-row"
                                           style="padding: 10px 12px; background: var(--bg-sunk); border-radius:8px;
                                                    border: .5px solid var(--line); border-left: 3px solid {{ $col }};
                                                    display:flex; align-items:center; gap:10px;
                                                    text-decoration:none; color: var(--ink);">
                                            <div style="flex:1; min-width:0;">
                                                <div style="font-size:12.5px; font-weight:600;">{{ $r->name }}</div>
                                                <div style="font-size:11px; color: var(--ok); margin-top:1px; font-weight:500;">{{ __('Available') }}</div>
                                            </div>
                                            <span class="cal-room-edit" style="display:inline-flex; align-items:center; gap:3px; color: var(--ink-3); font-size:10.5px; font-weight:600; flex-shrink:0;">
                                                <x-icon name="plus" :size="12"/> {{ __('Add') }}
                                            </span>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div style="padding:14px 18px; border-top:.5px solid var(--line); display:flex; gap:8px;">
                            <a href="{{ route('tenant.bookings.create', ['property_id' => $propertyId, 'check_in' => $selectedDay]) }}"
                               class="btn btn-sm btn-primary" style="flex:1; justify-content:center;">
                                <x-icon name="plus" :size="12"/> {{ __('Add booking') }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Swipe left/right on the month grid to page between months (touch devices).
         Horizontal-dominant swipes only, so vertical scrolling is unaffected; a
         detected swipe also suppresses the trailing synthetic click so it doesn't
         accidentally open a day's detail panel. --}}
    <script>
        (function () {
            var grid = document.querySelector('[data-cal-swipe]');
            if (!grid) return;
            var startX = 0, startY = 0, swiped = false;
            var prev = grid.getAttribute('data-prev');
            var next = grid.getAttribute('data-next');

            grid.addEventListener('touchstart', function (e) {
                var t = e.changedTouches[0];
                startX = t.clientX; startY = t.clientY; swiped = false;
            }, { passive: true });

            grid.addEventListener('touchend', function (e) {
                var t = e.changedTouches[0];
                var dx = t.clientX - startX, dy = t.clientY - startY;
                // Require a clear horizontal gesture (>60px and mostly sideways).
                if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) {
                    swiped = true;
                    var url = dx < 0 ? next : prev; // swipe left → next month
                    if (url) window.location.href = url;
                }
            }, { passive: true });

            // Swallow the click the browser may fire after a swipe so it doesn't
            // also open the day cell the finger lifted on.
            grid.addEventListener('click', function (e) {
                if (swiped) { e.preventDefault(); e.stopPropagation(); swiped = false; }
            }, true);
        })();
    </script>
</x-app-layout>
