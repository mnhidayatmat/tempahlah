<x-app-layout :title="__('Calendar')" :subtitle="__('Month view')" :breadcrumbs="[$property?->name ?? __('Calendar')]">
    @php
        $paymentStatus = function ($b) {
            if ($b->balance_paid_at) return 'paid';
            if ($b->deposit_paid_at) return 'deposit';
            return 'unpaid';
        };
        $totalRoomsCount = $rooms->count() ?: 1;
    @endphp

    <div style="display:flex; flex-direction:column; gap:16px;">

        {{-- Toolbar --}}
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
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

            {{-- Property summary strip --}}
            <div class="card" style="padding: 18px 22px; display:flex; gap:24px; align-items:center; flex-wrap:wrap;">
                <div style="min-width:0; flex: 1 1 240px;">
                    <div style="font-size:12.5px; color: var(--ink-3); margin-bottom:2px; display:inline-flex; align-items:center; gap:4px;">
                        <x-icon name="pin" :size="10"/>
                        {{ $property?->city ?? '—' }} · {{ $rooms->count() }} {{ trans_choice('{1} room|[2,*] rooms', $rooms->count()) }}
                    </div>
                    <div style="font-family: var(--font-display); font-size:22px; font-weight:600; letter-spacing:-.02em; line-height:1.15; color: var(--ink);">
                        {{ $property?->name ?? $monthLabel }}
                    </div>
                </div>
                <div style="width:1px; height:40px; background: var(--line);"></div>
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Occupancy') }} · {{ $cursor->format('M') }}</div>
                    <div class="mono" style="font-size:16px; font-weight:700;">{{ $occupancyPct }}%</div>
                </div>
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Revenue') }}</div>
                    <div class="mono" style="font-size:16px; font-weight:700;">RM {{ number_format($monthRevenue, 0) }}</div>
                </div>
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Bookings') }}</div>
                    <div class="mono" style="font-size:16px; font-weight:700;">{{ $monthBookings }}</div>
                </div>
                <div style="flex:1;"></div>
                <div style="display:flex; gap:12px; font-size:11.5px;">
                    <span style="display:inline-flex; align-items:center; gap:5px; color: var(--ink-2);">
                        <span style="width:10px; height:10px; border-radius:3px; background: var(--primary);"></span>{{ __('Confirmed') }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px; color: var(--ink-2);">
                        <span style="width:10px; height:10px; border-radius:3px; background: var(--warn);"></span>{{ __('Deposit') }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px; color: var(--ink-2);">
                        <span style="width:10px; height:10px; border-radius:3px; background: var(--err);"></span>{{ __('Unpaid') }}</span>
                </div>
            </div>

            {{-- Main: grid + (optional) detail panel --}}
            <div style="display:grid; grid-template-columns: {{ $selectedDay ? '1fr 340px' : '1fr' }}; gap:16px; align-items:flex-start; transition: grid-template-columns 200ms;">

                {{-- Month grid --}}
                <div class="card" style="padding:0; overflow:hidden;">
                    {{-- Weekday header --}}
                    <div style="display:grid; grid-template-columns: repeat(7, 1fr); padding: 4px 0; background:transparent;">
                        @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $wd)
                            <div style="padding: 12px 14px; font-size:11px; font-weight:600;
                                        color: {{ ($i === 0 || $i === 6) ? 'var(--primary)' : 'var(--ink-3)' }};
                                        text-transform:uppercase; letter-spacing:.09em; text-align:left;">
                                {{ __($wd) }}
                            </div>
                        @endforeach
                    </div>

                    {{-- Day cells --}}
                    <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:6px; padding: 0 10px 14px;">
                        @foreach ($days as $d)
                            @if (! $d)
                                <div style="min-height:128px;"></div>
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
                                    $occRatio = min(1, $occupied / $totalRooms);
                                    $heatBg = $occupied > 0
                                        ? 'rgba(217, 119, 87, '.(0.06 + $occRatio * 0.18).')'
                                        : ($isWknd ? 'var(--bg-sunk)' : 'var(--bg-elev)');
                                    $cellHref = route('tenant.calendar', [
                                        'property_id' => $propertyId,
                                        'cursor' => $cursor->format('Y-m'),
                                        'day' => $iso,
                                    ]);
                                @endphp
                                <a href="{{ $cellHref }}"
                                   style="min-height:128px; padding:10px; text-decoration:none; color: var(--ink);
                                          background: {{ $isSelected ? 'linear-gradient(160deg, rgba(217,119,87,0.22), rgba(217,119,87,0.08))' : $heatBg }};
                                          border: 1px solid {{ $isSelected ? 'var(--primary)' : 'var(--line)' }};
                                          border-radius:14px;
                                          opacity: {{ $isOtherMonth ? '0.4' : '1' }};
                                          display:flex; flex-direction:column; gap:6px;
                                          position:relative;
                                          box-shadow: {{ $isSelected ? '0 8px 24px -10px oklch(67% 0.16 45 / 0.35), 0 1px 0 oklch(67% 0.16 45 / 0.06) inset' : 'var(--sh-1)' }};
                                          transition: transform 120ms, box-shadow 120ms;">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
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
                                            <span style="font-size:10px; font-weight:700;
                                                         color: {{ $full ? 'var(--err)' : 'var(--ink-3)' }};
                                                         font-family: var(--font-mono);
                                                         background: {{ $full ? 'var(--err-tint)' : 'transparent' }};
                                                         padding: {{ $full ? '1px 6px' : '0' }};
                                                         border-radius:999px;">
                                                {{ $full ? 'FULL' : $occupied.'/'.$totalRooms }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Booking chips --}}
                                    <div style="display:flex; flex-direction:column; gap:3px; flex:1; min-height:0;">
                                        @foreach (array_slice($bks, 0, 3) as $b)
                                            @php
                                                $ps = $paymentStatus($b);
                                                $col = $ps === 'paid' ? 'var(--primary)' : ($ps === 'deposit' ? 'var(--warn)' : 'var(--err)');
                                                $tint = $ps === 'paid' ? 'var(--primary-tint)' : ($ps === 'deposit' ? 'var(--warn-tint)' : 'var(--err-tint)');
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
                                        <div style="display:flex; gap:6px; margin-top:auto; align-items:center;">
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
                    <div class="card" style="padding:0; overflow:hidden; position:sticky; top:16px;">
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
                                <div class="mono" style="font-size:17px; font-weight:700; color: {{ $free > 0 ? 'var(--ok)' : 'var(--err)' }};">{{ $free }}</div>
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

                        {{-- Per-room status --}}
                        <div style="padding: 14px 18px;">
                            <div class="cm-eyebrow" style="margin-bottom:10px;">{{ __('Room status') }}</div>
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                @foreach ($rooms as $r)
                                    @php
                                        $bk = collect($dayBookings)->firstWhere('room_id', $r->id);
                                        $col = $bk
                                            ? ($paymentStatus($bk) === 'paid' ? 'var(--primary)' : ($paymentStatus($bk) === 'deposit' ? 'var(--warn)' : 'var(--err)'))
                                            : 'var(--ok)';
                                    @endphp
                                    <div style="padding: 10px 12px; background: var(--bg-sunk); border-radius:8px;
                                                border: .5px solid var(--line);
                                                border-left: 3px solid {{ $col }};
                                                display:flex; align-items:center; gap:10px;">
                                        <div style="flex:1; min-width:0;">
                                            <div style="font-size:12.5px; font-weight:600;">{{ $r->name }}</div>
                                            @if ($bk)
                                                <div style="font-size:11px; color: var(--ink-3); margin-top:1px;">
                                                    {{ $bk->guest?->name ?? __('Guest') }} · {{ $bk->nights }}n · RM {{ number_format($bk->total_amount, 0) }}
                                                </div>
                                            @else
                                                <div style="font-size:11px; color: var(--ok); margin-top:1px; font-weight:500;">{{ __('Available') }}</div>
                                            @endif
                                        </div>
                                        @if ($bk)
                                            @php $ps = $paymentStatus($bk); @endphp
                                            <span class="pill" style="height:18px; font-size:10px;
                                                                      background: {{ $ps === 'paid' ? 'var(--primary-tint)' : ($ps === 'deposit' ? 'var(--warn-tint)' : 'var(--err-tint)') }};
                                                                      color: {{ $col }};">
                                                {{ $ps === 'paid' ? __('Paid') : ($ps === 'deposit' ? __('Deposit') : __('Unpaid')) }}
                                            </span>
                                        @endif
                                    </div>
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
</x-app-layout>
