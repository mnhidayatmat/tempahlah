<div wire:poll.60s style="display:flex; flex-direction:column; gap: 24px;">

    {{-- Greeting + headline --}}
    <section style="padding: 4px 0 0;">
        <div class="kicker">{{ now()->isoFormat('dddd, D MMM YYYY') }}</div>
        <h2 class="display-2" style="margin: 4px 0 8px;">
            {{ $greeting }}, {{ explode(' ', auth()->user()->name)[0] }}.
        </h2>
        <p style="margin:0; font-size:15px; color: var(--ink-2); max-width: 640px; line-height:1.5;">
            @php
                $checkInTomorrow = $upcoming->filter(fn ($b) => \Carbon\Carbon::parse($b->check_in)->isTomorrow())->count();
                $pendingPay = collect($actions)->firstWhere('tone', 'warn');
            @endphp
            @if ($checkInTomorrow)
                {{ trans_choice(':count check-in tomorrow|:count check-ins tomorrow', $checkInTomorrow) }}.
            @endif
            @if ($pendingPay)
                {{ $pendingPay['title'] }}.
            @endif
            @if (! $checkInTomorrow && ! $pendingPay)
                {{ __('Quiet day ahead — a good time to review pricing or replies.') }}
            @endif
        </p>
    </section>

    {{-- Stat cards --}}
    <section style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 14px;">
        <x-stat-card
            :label="__('Revenue · 30d')"
            unit="RM"
            :value="number_format($stats['revenue'])"
            :delta="$stats['revenue_delta']"
            :sparkline="$stats['revenue_spark']"
            color="primary"/>
        <x-stat-card
            :label="__('Bookings · 30d')"
            :value="$stats['bookings']"
            :delta="$stats['bookings_delta']"
            :sparkline="$stats['bookings_spark']"
            color="accent"/>
        <x-stat-card
            :label="__('Occupancy')"
            :value="$stats['occupancy'] . '%'"
            :delta="$stats['occupancy_delta']"
            :sparkline="$stats['occupancy_spark']"
            color="ok"/>
        <x-stat-card
            :label="__('ADR')"
            unit="RM"
            :value="number_format($stats['adr'])"
            :delta="$stats['adr_delta']"
            :sparkline="$stats['adr_spark']"
            color="warn"/>
    </section>

    {{-- Two-column: upcoming + side rail --}}
    <section style="display:grid; grid-template-columns: 1.6fr 1fr; gap: 18px;">

        {{-- Upcoming bookings --}}
        <div class="hauz-card" style="padding: 18px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 14px;">
                <div>
                    <div class="kicker">{{ __('Upcoming') }}</div>
                    <div style="font-family: var(--font-display); font-size: 22px; line-height:1.1; margin-top:2px;">
                        {{ __('Next 14 days') }}
                    </div>
                </div>
                <a href="#" class="btn btn-sm">{{ __('View all') }} →</a>
            </div>

            @if ($upcoming->isEmpty())
                <div style="padding: 32px 8px; text-align:center; color: var(--ink-3); font-size: 13px;">
                    {{ __('No bookings in the next 14 days.') }}
                </div>
            @else
                <div style="display:flex; flex-direction:column;">
                    @foreach ($upcoming as $b)
                        <div style="display:flex; align-items:center; gap: 12px; padding: 10px 0; border-bottom: .5px solid var(--line);">
                            <x-avatar :name="$b->guest_name ?? 'Guest'" :size="32"/>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size: 13.5px; font-weight: 500;">{{ $b->guest_name ?? __('Guest') }}</div>
                                <div style="font-size: 11.5px; color: var(--ink-3);">
                                    {{ optional($b->property)->name ?? '—' }} ·
                                    <span class="mono">{{ \Carbon\Carbon::parse($b->check_in)->format('d M') }}–{{ \Carbon\Carbon::parse($b->check_out)->format('d M') }}</span>
                                </div>
                            </div>
                            @php
                                $ps = $b->payment_status ?? 'pending';
                                $variant = $ps === 'paid' ? 'ok' : ($ps === 'pending' ? 'warn' : 'err');
                            @endphp
                            <x-pill :variant="$variant" :dot="true">{{ ucfirst($ps) }}</x-pill>
                            <span class="mono" style="font-size: 12.5px; color: var(--ink-2); min-width: 72px; text-align:right;">
                                RM{{ number_format($b->total_amount ?? 0) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Side rail: action queue + channel mix --}}
        <div style="display:flex; flex-direction:column; gap: 14px;">

            {{-- Action queue --}}
            <div class="hauz-card" style="padding: 16px;">
                <div class="kicker" style="margin-bottom: 10px;">{{ __('Action queue') }}</div>
                <div style="display:flex; flex-direction:column; gap: 8px;">
                    @foreach ($actions as $a)
                        <div style="display:flex; align-items:flex-start; gap:10px; padding: 8px; border-radius: var(--r-md); background: var(--bg-sunk);">
                            <div style="width:24px; height:24px; border-radius:6px; background: var({{ '--' . $a['tone'] . '-tint' }}); color: var({{ '--' . $a['tone'] }}); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <x-icon :name="$a['icon']" :size="13"/>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:12.5px; line-height:1.4;">{{ $a['title'] }}</div>
                                @if (!empty($a['cta']))
                                    <a href="#" style="font-size:11.5px; color: var(--primary); text-decoration:none; font-weight:500;">{{ $a['cta'] }} →</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Channel mix --}}
            <div class="hauz-card" style="padding: 16px;">
                <div class="kicker" style="margin-bottom: 10px;">{{ __('Channel mix · 30d') }}</div>
                <div style="display:flex; flex-direction:column; gap: 8px;">
                    @foreach ($channelMix as $c)
                        <div>
                            <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                                <span style="color: var(--ink-2);">{{ $c['label'] }}</span>
                                <span class="mono" style="color: var(--ink-3);">{{ $c['count'] }} · {{ $c['pct'] }}%</span>
                            </div>
                            <div style="height:6px; background: var(--bg-sunk); border-radius:999px; overflow:hidden;">
                                <div style="width: {{ $c['pct'] }}%; height: 100%; background: var(--primary); border-radius:999px;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Tonight's status --}}
    <section>
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 12px;">
            <div>
                <div class="kicker">{{ __('Tonight') }}</div>
                <div style="font-family: var(--font-display); font-size: 22px; line-height:1.1; margin-top:2px;">
                    {{ __('Property status') }}
                </div>
            </div>
            <a href="{{ route('tenant.properties.index') }}" class="btn btn-sm">{{ __('Manage properties') }} →</a>
        </div>

        @if ($properties->isEmpty())
            <div class="hauz-card" style="padding: 32px; text-align:center;">
                <div style="font-family: var(--font-display); font-size: 22px; margin-bottom: 6px;">{{ __('No properties yet') }}</div>
                <p style="margin: 0 0 14px; color: var(--ink-3); font-size: 13px;">{{ __('Add your first homestay or room to start receiving bookings.') }}</p>
                <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary btn-sm">
                    <x-icon name="plus" :size="13"/> {{ __('Add property') }}
                </a>
            </div>
        @else
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 14px;">
                @foreach ($properties as $p)
                    @php
                        $st = $p->tonight_status['state'];
                        $variant = $st === 'occupied' ? 'info' : ($st === 'checkin' ? 'warn' : 'ok');
                    @endphp
                    <div class="hauz-card" style="padding: 14px; display:flex; gap: 12px; align-items:flex-start;">
                        <x-property-visual :property="$p" :size="44"/>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size: 13.5px; font-weight: 600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $p->name }}</div>
                            <div style="font-size: 11.5px; color: var(--ink-3); margin-bottom: 6px;">
                                {{ $p->city ?? $p->location ?? '—' }}
                            </div>
                            <x-pill :variant="$variant" :dot="true">{{ $p->tonight_status['label'] }}</x-pill>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Pro lock teaser (if free) --}}
    @if (! $isPro)
        <section>
            <x-pro-lock
                feature="feature_paid_tier"
                :title="__('Auto-reminders, payment links, multi-property')"
                :reason="__('Send WhatsApp deposit reminders, accept Toyyibpay/FPX, and manage unlimited listings — RM49/month.')"
                :cta="__('Upgrade — RM49/mo')"/>
        </section>
    @endif

</div>
