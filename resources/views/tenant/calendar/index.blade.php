<x-app-layout :title="__('Calendar')">
    @php
        $cellW = 64; $headerH = 48; $rowH = 56; $labelW = 180;
        $rangeLabel = $start->isSameMonth($end)
            ? $start->format('M j').' – '.$end->format('j, Y')
            : $start->format('M j').' – '.$end->format('M j, Y');

        $isPro = (app(\App\Support\Tenancy\TenantContext::class)->current()?->subscription?->plan ?? 'free') !== 'free';
        $tenant = app(\App\Support\Tenancy\TenantContext::class)->current();

        $paymentStatus = function ($b) {
            if ($b->balance_paid_at) return 'paid';
            if ($b->deposit_paid_at) return 'deposit';
            return 'unpaid';
        };

        $colorMap = [
            'paid'    => ['line' => 'var(--primary)',         'tint' => 'var(--primary-tint)'],
            'deposit' => ['line' => 'var(--warn)',            'tint' => 'var(--warn-tint)'],
            'unpaid'  => ['line' => 'var(--err)',             'tint' => 'var(--err-tint)'],
            'block'   => ['line' => 'var(--ink-4)',           'tint' => 'var(--bg-sunk)'],
        ];

        $property = $properties->firstWhere('id', $propertyId);
    @endphp

    <div style="display:flex; flex-direction:column; gap: 16px;">

        {{-- Toolbar --}}
        <div style="display:flex; align-items:center; gap: 10px; flex-wrap: wrap;">
            <div class="hauz-card" style="display:flex; padding:3px; gap:2px;">
                @forelse ($properties as $p)
                    @php
                        $active = $p->id === $propertyId;
                        $locked = ! $isPro && $p->id !== $properties->first()?->id;
                    @endphp
                    <a href="{{ route('tenant.calendar', ['property_id' => $p->id, 'start' => $start->toDateString()]) }}"
                       class="btn btn-sm"
                       @if($locked) onclick="return false" aria-disabled="true" @endif
                       style="border:0; background: {{ $active ? 'var(--primary-tint)' : 'transparent' }};
                              color: {{ $active ? 'var(--primary)' : 'var(--ink-2)' }};
                              font-weight: {{ $active ? 600 : 500 }};
                              opacity: {{ $locked ? '0.6' : '1' }};
                              text-decoration:none;">
                        {{ $p->name }}
                        @if ($locked)
                            <x-icon name="lock" :size="11" style="color: var(--pro);"/>
                        @endif
                    </a>
                @empty
                    <span style="padding: 4px 10px; font-size:12px; color: var(--ink-3);">{{ __('No properties yet') }}</span>
                @endforelse
            </div>

            <div style="flex:1;"></div>

            <div style="display:flex; border:.5px solid var(--line-2); border-radius: var(--r-md); overflow:hidden;">
                <a href="{{ route('tenant.calendar', ['property_id' => $propertyId, 'start' => $prevStart]) }}"
                   class="btn btn-sm" style="border-radius:0; border:0; background: var(--bg-elev);" aria-label="{{ __('Previous range') }}">
                    <x-icon name="arrow-left" :size="12"/>
                </a>
                <div style="padding: 0 12px; display:flex; align-items:center; border-left: .5px solid var(--line-2); border-right: .5px solid var(--line-2); font-size: 12px; font-weight: 500; background: var(--bg-elev);">
                    {{ $rangeLabel }}
                </div>
                <a href="{{ route('tenant.calendar', ['property_id' => $propertyId, 'start' => $nextStart]) }}"
                   class="btn btn-sm" style="border-radius:0; border:0; background: var(--bg-elev);" aria-label="{{ __('Next range') }}">
                    <x-icon name="arrow-right" :size="12"/>
                </a>
            </div>

            <a href="{{ route('tenant.bookings.index') }}" class="btn btn-sm btn-primary">
                <x-icon name="plus" :size="12"/> {{ __('New booking') }}
            </a>
        </div>

        @if ($properties->isEmpty())
            <div class="hauz-card" style="padding: 32px; text-align:center;">
                <div class="display-3" style="margin-bottom: 6px;">{{ __('No properties to schedule') }}</div>
                <p style="margin: 0 0 14px; color: var(--ink-3); font-size: 13px;">{{ __('Add a property to start managing its calendar.') }}</p>
                <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary btn-sm">{{ __('Add property') }}</a>
            </div>
        @else
            {{-- Property summary strip --}}
            <div class="hauz-card" style="padding: 12px 16px; display:flex; gap:24px; align-items:center; flex-wrap: wrap;">
                <div>
                    <div style="font-size: 13px; font-weight: 600;">{{ $property->name }}</div>
                    <div style="font-size: 11.5px; color: var(--ink-3); display:inline-flex; align-items:center; gap:4px; margin-top: 2px;">
                        <x-icon name="pin" :size="10"/>
                        {{ $property->city ?? '—' }}{{ $property->state ? ', '.$property->state : '' }}
                        · {{ $rooms->count() }} {{ trans_choice('{1} room|[2,*] rooms', $rooms->count()) }}
                    </div>
                </div>
                <div style="width:1px; height:28px; background: var(--line);"></div>

                <div>
                    <div class="kicker" style="font-size: 10.5px; margin-bottom: 2px;">{{ __('Occupancy (14d)') }}</div>
                    <div class="mono" style="font-size: 14px; font-weight: 600;">{{ number_format($stats['occupancy'] * 100, 0) }}%</div>
                </div>
                <div>
                    <div class="kicker" style="font-size: 10.5px; margin-bottom: 2px;">{{ __('Revenue (14d)') }}</div>
                    <div class="mono" style="font-size: 14px; font-weight: 600;">RM {{ number_format($stats['revenue'], 0) }}</div>
                </div>
                <div>
                    <div class="kicker" style="font-size: 10.5px; margin-bottom: 2px;">{{ __('Avg. rate') }}</div>
                    <div class="mono" style="font-size: 14px; font-weight: 600;">RM {{ number_format($stats['rate'], 0) }}</div>
                </div>

                <div style="flex:1;"></div>

                <div style="display:flex; gap:12px; font-size: 11.5px; color: var(--ink-2); flex-wrap: wrap;">
                    <span style="display:inline-flex; align-items:center; gap:5px;"><span style="width:10px; height:10px; border-radius:3px; background: var(--primary);"></span>{{ __('Confirmed') }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px;"><span style="width:10px; height:10px; border-radius:3px; background: var(--warn);"></span>{{ __('Deposit') }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px;"><span style="width:10px; height:10px; border-radius:3px; background: var(--err);"></span>{{ __('Unpaid') }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px;"><span style="width:10px; height:10px; border-radius:3px; background: var(--ink-4);"></span>{{ __('Blocked') }}</span>
                </div>
            </div>

            {{-- Timeline --}}
            <div class="hauz-card" style="padding: 0; overflow:hidden;">
                @if ($rooms->isEmpty())
                    <div style="padding: 40px; text-align:center; color: var(--ink-3); font-size: 13px;">
                        {{ __('This property has no rooms yet.') }}
                        <div style="margin-top: 10px;">
                            <a href="{{ route('tenant.properties.show', ['id' => $propertyId]) }}" class="btn btn-sm">{{ __('Add rooms') }}</a>
                        </div>
                    </div>
                @else
                    <div class="thin-scroll" style="overflow-x: auto;">
                        <div style="min-width: {{ $labelW + $cellW * $rangeDays }}px;">

                            {{-- Header row --}}
                            <div style="display:flex; height: {{ $headerH }}px; border-bottom: .5px solid var(--line); background: var(--bg-sunk);">
                                <div style="width: {{ $labelW }}px; flex-shrink:0; padding: 10px 14px; border-right: .5px solid var(--line);">
                                    <div class="kicker" style="font-size: 9.5px;">{{ __('Room') }}</div>
                                </div>
                                <div style="display:flex;">
                                    @foreach ($days as $i => $d)
                                        @php $isWknd = $d->isWeekend(); $isToday = $d->isToday(); @endphp
                                        <div style="width: {{ $cellW }}px; padding: 8px 0; text-align:center;
                                                    {{ $i < $rangeDays - 1 ? 'border-right: .5px solid var(--line);' : '' }}
                                                    background: {{ $isWknd ? 'oklch(95% 0.012 70)' : 'transparent' }};">
                                            <div style="font-size:10px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .06em;">
                                                {{ $d->format('D') }}
                                            </div>
                                            <div class="mono" style="font-size:14px; font-weight:600; {{ $isToday ? 'color: var(--primary);' : '' }}">
                                                {{ $d->format('j') }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Rows --}}
                            @foreach ($rooms as $ri => $room)
                                @php
                                    $roomBookings = $bookingsByRoom->get($room->id, collect());
                                    $roomBlocks = $blocksByRoom->get($room->id, collect());
                                @endphp
                                <div style="display:flex; height: {{ $rowH }}px; position: relative;
                                            {{ $ri < $rooms->count() - 1 ? 'border-bottom: .5px solid var(--line);' : '' }}">

                                    <div style="width: {{ $labelW }}px; flex-shrink:0; padding: 10px 14px; border-right: .5px solid var(--line);">
                                        <div style="font-size: 13px; font-weight: 500; line-height: 1.2;">{{ $room->name }}</div>
                                        <div style="font-size: 10.5px; color: var(--ink-3); margin-top: 2px; display:inline-flex; align-items:center; gap: 5px;">
                                            <x-icon name="bed" :size="10"/>
                                            {{ $room->beds }} · RM{{ number_format((float) $room->base_price, 0) }}
                                        </div>
                                    </div>

                                    {{-- Day cells (background grid) --}}
                                    <div style="position: relative; display:flex; flex: 1;">
                                        @foreach ($days as $i => $d)
                                            @php $isWknd = $d->isWeekend(); @endphp
                                            <div style="width: {{ $cellW }}px;
                                                        {{ $i < $rangeDays - 1 ? 'border-right: .5px solid var(--line);' : '' }}
                                                        background: {{ $isWknd ? 'oklch(97% 0.008 70)' : 'transparent' }};"></div>
                                        @endforeach

                                        {{-- Booking bars (absolute) --}}
                                        @foreach ($roomBookings as $b)
                                            @php
                                                $startOffset = $start->diffInDays($b->check_in, false);
                                                $span = (int) $b->nights ?: max(1, $b->check_in->diffInDays($b->check_out));
                                                if ($startOffset + $span < 0 || $startOffset >= $rangeDays) continue;

                                                $clippedStart = max(0, $startOffset);
                                                $clippedEnd = min($rangeDays, $startOffset + $span);
                                                $startsInRange = $startOffset >= 0;

                                                $left = $clippedStart * $cellW + ($startsInRange ? $cellW * 0.5 : 0);
                                                $width = ($clippedEnd - $clippedStart) * $cellW
                                                    - ($startsInRange ? $cellW * 0.5 : 0)
                                                    - $cellW * 0.5;
                                                $width = max(30, $width);

                                                $payState = $paymentStatus($b);
                                                $color = $colorMap[$payState];
                                                $label = $b->guest?->name ?? __('Guest');
                                            @endphp
                                            <a href="{{ route('tenant.bookings.show', ['id' => $b->public_id ?? $b->id]) }}"
                                               title="{{ $label.' · '.$b->reference.' · '.$b->check_in->format('d M').' → '.$b->check_out->format('d M') }}"
                                               style="position:absolute; top: 8px; left: {{ $left }}px; width: {{ $width }}px; height: {{ $rowH - 16 }}px;
                                                      background: {{ $color['tint'] }}; border: .5px solid {{ $color['line'] }};
                                                      border-radius: 999px; padding: 0 10px;
                                                      display:flex; align-items:center; gap: 6px;
                                                      font-size: 11.5px; font-weight: 500; color: var(--ink);
                                                      overflow:hidden; white-space:nowrap;
                                                      text-decoration:none; box-shadow: var(--sh-1);">
                                                <span style="width:6px; height:6px; border-radius:999px; background: {{ $color['line'] }}; flex-shrink:0;"></span>
                                                <span style="overflow:hidden; text-overflow:ellipsis;">{{ $label }}</span>
                                                <span class="mono" style="color: var(--ink-3); font-size: 10.5px;">· {{ $span }}n</span>
                                            </a>
                                        @endforeach

                                        {{-- Block bars --}}
                                        @foreach ($roomBlocks as $blk)
                                            @php
                                                $blkStart = $start->diffInDays($blk->starts_on, false);
                                                $blkEnd = $start->diffInDays($blk->ends_on, false) + 1;
                                                if ($blkEnd <= 0 || $blkStart >= $rangeDays) continue;

                                                $cs = max(0, $blkStart);
                                                $ce = min($rangeDays, $blkEnd);
                                                $bLeft = $cs * $cellW + 4;
                                                $bWidth = ($ce - $cs) * $cellW - 8;
                                                $bWidth = max(20, $bWidth);
                                            @endphp
                                            <div title="{{ ucfirst(str_replace('_', ' ', $blk->reason)) }}"
                                                 style="position:absolute; top: 8px; left: {{ $bLeft }}px; width: {{ $bWidth }}px; height: {{ $rowH - 16 }}px;
                                                        background: repeating-linear-gradient(45deg, var(--ink-4) 0 4px, transparent 4px 8px);
                                                        opacity: .35; border-radius: 6px; pointer-events: none;"></div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Pricing rules upsell — only on free tier --}}
        @if (! $isPro)
            <div style="padding: 12px 16px; background: var(--pro-tint); border-radius: var(--r-md); border: .5px solid var(--line-2); display:flex; align-items:center; gap: 12px;">
                <x-icon name="sparkle" :size="16" style="color: var(--pro);"/>
                <div style="flex:1;">
                    <div style="font-size: 13px; font-weight: 600;">{{ __('Smart pricing & seasonal rules') }}</div>
                    <div style="font-size: 11.5px; color: var(--ink-3);">
                        {{ __('Auto-raise rates on weekends and school holidays. Drag-to-block dates in bulk. Available on Pro.') }}
                    </div>
                </div>
                <a href="{{ route('tenant.subscription') }}" class="btn btn-sm" style="background: var(--pro); color: white; border-color: transparent;">
                    {{ __('Upgrade to Pro') }}
                </a>
            </div>
        @endif
    </div>
</x-app-layout>
