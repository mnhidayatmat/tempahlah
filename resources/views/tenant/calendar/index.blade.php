<x-app-layout :title="__('Calendar')">
    <div style="display:flex; flex-direction:column; gap: 20px;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between;">
            <div>
                <div class="kicker">{{ __('Availability') }}</div>
                <h2 class="display-2" style="margin: 4px 0 0;">{{ __('Calendar') }}</h2>
                <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">
                    {{ $start->format('d M') }} — {{ $start->copy()->addDays(13)->format('d M Y') }}
                </p>
            </div>
            <div style="display:flex; gap:8px;">
                <button class="btn btn-sm">←</button>
                <button class="btn btn-sm">{{ __('Today') }}</button>
                <button class="btn btn-sm">→</button>
            </div>
        </div>

        @if ($properties->isEmpty())
            <div class="hauz-card" style="padding: 32px; text-align:center;">
                <div style="font-family: var(--font-display); font-size: 22px; margin-bottom: 6px;">{{ __('No properties to schedule') }}</div>
                <p style="margin: 0 0 14px; color: var(--ink-3); font-size: 13px;">{{ __('Add a property to start managing its calendar.') }}</p>
                <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary btn-sm">{{ __('Add property') }}</a>
            </div>
        @else
            <div class="hauz-card" style="padding: 0; overflow:hidden;">
                <div style="overflow-x: auto;">
                    <div style="display:grid; grid-template-columns: 200px repeat(14, minmax(72px, 1fr)); min-width: 1200px;">
                        {{-- Header row --}}
                        <div style="padding: 10px 14px; border-bottom: .5px solid var(--line); background: var(--bg-sunk);"></div>
                        @foreach ($days as $d)
                            @php $isToday = $d->isToday(); $isWknd = $d->isWeekend(); @endphp
                            <div style="padding: 8px; border-bottom: .5px solid var(--line); border-left: .5px solid var(--line); text-align:center; background: {{ $isToday ? 'var(--primary-tint)' : ($isWknd ? 'var(--bg-sunk)' : 'var(--bg-elev)') }};">
                                <div class="kicker" style="font-size:9.5px;">{{ $d->format('D') }}</div>
                                <div style="font-family: var(--font-display); font-size: 18px; line-height:1.1; color: {{ $isToday ? 'var(--primary)' : 'var(--ink)' }};">{{ $d->format('d') }}</div>
                            </div>
                        @endforeach

                        {{-- Property rows --}}
                        @foreach ($properties as $p)
                            <div style="padding: 14px; border-bottom: .5px solid var(--line); display:flex; align-items:center; gap: 10px;">
                                <x-property-visual :property="$p" :size="28"/>
                                <div style="min-width:0;">
                                    <div style="font-size: 13px; font-weight: 500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $p->name }}</div>
                                    <div style="font-size: 11px; color: var(--ink-3);">{{ $p->city ?? '—' }}</div>
                                </div>
                            </div>
                            @foreach ($days as $d)
                                @php
                                    $booked = $bookings->get($p->id, collect())->first(function ($b) use ($d) {
                                        return Carbon\Carbon::parse($b->check_in)->lte($d) && Carbon\Carbon::parse($b->check_out)->gt($d);
                                    });
                                    $isWknd = $d->isWeekend();
                                @endphp
                                <div style="border-bottom: .5px solid var(--line); border-left: .5px solid var(--line); padding: 8px 4px; min-height: 56px; background: {{ $isWknd && !$booked ? 'var(--bg-sunk)' : 'var(--bg-elev)' }}; position:relative;">
                                    @if ($booked)
                                        <div style="background: var(--primary); color: var(--primary-ink); border-radius: 6px; padding: 4px 6px; font-size: 10.5px; font-weight: 500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            {{ $booked->guest_name ?? __('Booked') }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @php $isPro = (app(\App\Support\Tenancy\TenantContext::class)->current()?->subscription?->plan ?? 'free') !== 'free'; @endphp
        @if (! $isPro)
            <x-pro-lock
                :title="__('Two-way Google Calendar sync')"
                :reason="__('Auto-sync availability to Google Calendar / iCal — no double-bookings across channels.')"
                :cta="__('Upgrade — RM49/mo')"/>
        @endif
    </div>
</x-app-layout>
