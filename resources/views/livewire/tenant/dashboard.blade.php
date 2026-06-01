@php
    $firstName = explode(' ', auth()->user()->name)[0] ?? '';
    $propertyCount = $shelf->count() ?: ($stats['properties'] ?? 0);
    $first = $shelf->first();

    // Build the SVG path for the income chart
    $W = 720; $H = 240; $PAD_T = 20; $PAD_B = 40;
    $innerW = $W; $innerH = $H - $PAD_T - $PAD_B;
    $vals = $series['values'];
    $max = max(1, ...$vals);
    $count = count($vals);
    $step = $count > 1 ? $innerW / ($count - 1) : 0;
    $pts = [];
    foreach ($vals as $i => $v) {
        $pts[] = [round($i * $step, 1), round($PAD_T + $innerH - ($v / $max) * $innerH, 1)];
    }
    $pathD = '';
    foreach ($pts as $i => $p) {
        if ($i === 0) {
            $pathD .= 'M'.$p[0].','.$p[1];
        } else {
            $prev = $pts[$i - 1];
            $cx1 = round($prev[0] + ($p[0] - $prev[0]) * 0.5, 1);
            $cx2 = round($p[0] - ($p[0] - $prev[0]) * 0.5, 1);
            $pathD .= ' C'.$cx1.','.$prev[1].' '.$cx2.','.$p[1].' '.$p[0].','.$p[1];
        }
    }
    $fillD = $pathD . ' L'.end($pts)[0].','.($PAD_T + $innerH).' L'.$pts[0][0].','.($PAD_T + $innerH).' Z';
    $lastPt = end($pts);
@endphp

<div wire:poll.60s style="display:flex; flex-direction:column; gap:24px;">

    {{-- === PROFILE HEADER === --}}
    <div class="dash-hero">
        {{-- glow blobs --}}
        <div class="dash-hero-blob dash-hero-blob-a"></div>
        <div class="dash-hero-blob dash-hero-blob-b"></div>

        <div class="dash-hero-avatar">
            <x-avatar :name="auth()->user()->name" :size="64"/>
        </div>

        <div class="dash-hero-body">
            <div class="cm-eyebrow-primary" style="margin-bottom:6px;">{{ __('Host Lounge') }} · {{ now()->isoFormat('dddd, D MMM') }}</div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <h1 class="dash-hero-title">
                    {{ __('Welcome back') }}, <span style="color: var(--primary);">{{ $firstName }}</span>!
                </h1>
                <span style="font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
                             padding:4px 9px; border-radius: var(--r-sm);
                             background: var(--ok-tint); color: var(--ok);
                             border: 1px solid color-mix(in oklab, var(--ok) 30%, transparent);
                             display:inline-flex; align-items:center; gap:5px;">
                    <span class="pulse-dot" style="width:6px; height:6px; border-radius:999px; background: var(--ok);"></span>
                    {{ ucfirst($plan) }} {{ __('account') }}
                </span>
            </div>
            <div class="dash-hero-sub">
                @if ($stats['bookings'] > 0)
                    {{ __('You have :n active bookings · :p properties live.', ['n' => $stats['bookings'], 'p' => $stats['properties']]) }}
                @else
                    {{ __('Quiet stretch — a good time to refresh listings or pricing.') }}
                @endif
            </div>
        </div>

        <div class="dash-hero-actions">
            <a href="{{ route('tenant.settings.index') }}" class="btn">{{ __('Profile Settings') }}</a>
            <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary">
                <x-icon name="plus" :size="13"/> {{ __('Add homestay') }}
            </a>
        </div>
    </div>

    {{-- === STAT CARDS === --}}
    {{-- auto-fit so 4 cols on desktop, 2 cols on tablet/mobile naturally
         (no inline grid-template that has to be CSS-overridden later). --}}
    <div class="dash-stats">
        @foreach ([
            ['label' => __('Total Earnings'),       'value' => 'RM '.number_format($stats['revenue'], 2), 'sub' => __('Net payout · last 30 days'), 'icon' => 'card',     'tone' => 'primary'],
            ['label' => __('Active Bookings'),      'value' => $stats['bookings'].' '.__('guests'),       'sub' => __('Across :n rooms', ['n' => $stats['rooms']]), 'icon' => 'users', 'tone' => 'warn'],
            ['label' => __('Property Portfolio'),   'value' => $stats['properties'] === 1 ? __('1 homestay') : trans(':n homestays', ['n' => $stats['properties']]), 'sub' => __(':n bookable rooms', ['n' => $stats['rooms']]), 'icon' => 'building', 'tone' => 'ok'],
            ['label' => __('Guest Review Index'),   'value' => $stats['rating'].' / 5.0',                 'sub' => __('Based on :n verified stays', ['n' => $stats['reviews']]), 'icon' => 'star', 'tone' => 'info'],
        ] as $card)
            @php
                $tone = match($card['tone']) {
                    'primary' => ['bg' => 'var(--primary-tint)', 'fg' => 'var(--primary)'],
                    'warn'    => ['bg' => 'var(--warn-tint)',    'fg' => 'var(--warn)'],
                    'ok'      => ['bg' => 'var(--ok-tint)',      'fg' => 'var(--ok)'],
                    'info'    => ['bg' => 'var(--info-tint)',    'fg' => 'var(--info)'],
                };
            @endphp
            <div class="card" style="padding:22px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px;">
                    <div class="cm-eyebrow">{{ $card['label'] }}</div>
                    <div style="width:34px; height:34px; border-radius: var(--r-md);
                                background: {{ $tone['bg'] }}; color: {{ $tone['fg'] }};
                                display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <x-icon :name="$card['icon']" :size="16"/>
                    </div>
                </div>
                <div style="font-size:26px; font-weight:700; letter-spacing:-.025em; line-height:1.05; color: var(--ink);">
                    {{ $card['value'] }}
                </div>
                <div style="font-size:11.5px; color: var(--ink-3); margin-top:10px;">
                    {{ $card['sub'] }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- === MAIN: CHART + TRANSACTIONS === --}}
    <div class="dash-main">

        {{-- INCOME CHART --}}
        <div class="card" style="padding:24px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:22px;">
                <div>
                    <div class="cm-eyebrow-primary" style="margin-bottom:6px;">{{ __('Weekly Metrics Rhythm') }}</div>
                    <h3 style="margin:0; font-size:18px; font-weight:700; letter-spacing:-.02em;">{{ __('Booking Income Stream') }}</h3>
                    <div style="font-size:12.5px; color: var(--ink-3); margin-top:4px;">
                        {{ __('Track your net earnings split. Showing direct booking velocity.') }}
                    </div>
                </div>
                <div style="display:flex; gap:2px; padding:3px;
                            background: var(--bg-elev); border-radius:999px; border:.5px solid var(--line);">
                    @foreach (['30d' => __('Last 30 Days'), 'qtr' => __('Quarterly'), 'ytd' => __('YTD')] as $key => $label)
                        @php $active = $range === $key; @endphp
                        <button wire:click="setRange('{{ $key }}')"
                                class="btn btn-sm"
                                style="border:0; background: {{ $active ? 'var(--primary)' : 'transparent' }};
                                       color: {{ $active ? 'var(--primary-ink)' : 'var(--ink-2)' }};
                                       font-weight: {{ $active ? '600' : '500' }};
                                       border-radius:999px;">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            {{-- SVG income chart --}}
            <div style="width:100%; overflow:hidden;">
                <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" style="width:100%; height:240px; display:block;">
                    <defs>
                        <linearGradient id="dash-income-fill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.18"/>
                            <stop offset="100%" stop-color="var(--primary)" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <path d="{{ $fillD }}" fill="url(#dash-income-fill)"/>
                    <path d="{{ $pathD }}" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="{{ $lastPt[0] }}" cy="{{ $lastPt[1] }}" r="6" fill="var(--primary)" opacity="0.2"/>
                    <circle cx="{{ $lastPt[0] }}" cy="{{ $lastPt[1] }}" r="3.5" fill="var(--primary)"/>
                </svg>
                <div style="display:flex; justify-content:space-between; font-size:11px; color: var(--ink-3); padding: 0 4px;">
                    @foreach ($series['labels'] as $i => $l)
                        @if ($i % 2 === 0)<span>{{ $l }}</span>@endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- TRANSACTIONS LOG --}}
        <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding:18px 20px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between;">
                <div class="cm-eyebrow" style="color: var(--ink-2);">{{ __('Checkout Transactions Log') }}</div>
                <span class="pulse-dot" style="width:8px; height:8px; border-radius:999px; background: var(--ok); box-shadow: 0 0 0 4px var(--ok-tint);"></span>
            </div>

            @forelse ($transactions as $i => $t)
                <div style="padding:14px 20px; {{ $i < $transactions->count() - 1 ? 'border-bottom:1px solid var(--line);' : '' }}">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:4px;">
                        <div style="font-size:13.5px; font-weight:700;">{{ $t['guest'] }}</div>
                        <div class="mono" style="font-size:13.5px; font-weight:700; color: var(--primary);">
                            RM {{ number_format($t['amount'], 2) }}
                        </div>
                    </div>
                    <div style="font-size:12px; color: var(--ink-3); margin-bottom:8px;">{{ $t['property'] }}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:11.5px; color: var(--ink-3);">{{ $t['when'] }}</span>
                        <span style="font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:999px;
                                     background: var(--ok-tint); color: var(--ok); font-family: var(--font-mono);">
                            {{ __('Payout') }}: RM {{ number_format($t['payout'], 2) }}
                        </span>
                    </div>
                </div>
            @empty
                <div style="padding:32px 20px; text-align:center; color: var(--ink-3); font-size:12.5px;">
                    {{ __('No transactions yet.') }}
                </div>
            @endforelse

            <div style="padding:12px;">
                <a href="{{ route('tenant.payments.index') }}"
                   style="width:100%; padding:12px; background:transparent;
                          border:1px dashed var(--primary); color: var(--primary);
                          border-radius:12px; font-size:12.5px; font-weight:600;
                          display:flex; align-items:center; justify-content:center; gap:6px;
                          text-decoration:none;">
                    <x-icon name="receipt" :size="13"/> {{ __('View all payments') }}
                </a>
            </div>
        </div>
    </div>

    {{-- === ACTION QUEUE === --}}
    @if (!empty($actions))
        <div class="card" style="padding:18px 22px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <div>
                    <div class="cm-eyebrow">{{ __('Action queue') }}</div>
                    <h3 style="margin:4px 0 0; font-size:16px; font-weight:700; letter-spacing:-.01em;">{{ __('What needs attention') }}</h3>
                </div>
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:10px;">
                @foreach ($actions as $a)
                    <div style="display:flex; align-items:flex-start; gap:10px; padding:12px;
                                border-radius: var(--r-md); background: var(--bg-sunk); border:.5px solid var(--line);">
                        <div style="width:28px; height:28px; border-radius: var(--r-sm);
                                    background: var(--{{ $a['tone'] }}-tint); color: var(--{{ $a['tone'] }});
                                    display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <x-icon :name="$a['icon']" :size="14"/>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; line-height:1.4; color: var(--ink);">{{ $a['title'] }}</div>
                            @if (!empty($a['cta']) && !empty($a['route']))
                                <a href="{{ $a['route'] }}" style="font-size:11.5px; color: var(--primary); text-decoration:none; font-weight:600; margin-top:4px; display:inline-block;">{{ $a['cta'] }} →</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- === HOMESTAY SHELF === --}}
    <div>
        <div style="margin-bottom:16px;">
            <div class="cm-eyebrow" style="margin-bottom:6px;">{{ __('Active Cohort') }}</div>
            <div style="display:flex; justify-content:space-between; align-items:baseline; gap:12px;">
                <h3 style="margin:0; font-size:18px; font-weight:700; letter-spacing:-.02em;">
                    {{ __('My Listed Homestays') }}
                    <span style="color: var(--ink-3); font-weight:600;">({{ $shelf->count() }})</span>
                </h3>
                <a href="{{ route('tenant.properties.index') }}" class="btn btn-sm">{{ __('Manage all') }} →</a>
            </div>
        </div>

        @if ($shelf->isEmpty())
            <div class="card" style="padding:32px; text-align:center;">
                <div style="font-family: var(--font-display); font-size:22px; margin-bottom:6px;">{{ __('No properties yet') }}</div>
                <p style="margin: 0 0 14px; color: var(--ink-3); font-size:13px;">{{ __('Add your first homestay or room to start receiving bookings.') }}</p>
                <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary btn-sm">
                    <x-icon name="plus" :size="13"/> {{ __('Add property') }}
                </a>
            </div>
        @else
            <div style="display:flex; flex-direction:column; gap:10px;">
                @foreach ($shelf as $p)
                    <a href="{{ route('tenant.properties.show', $p->id) }}"
                       class="card"
                       style="padding:14px; display:grid; grid-template-columns: auto 1fr auto auto auto;
                              gap:18px; align-items:center; text-decoration:none; color: inherit;
                              transition: border-color 200ms;">
                        <div style="width:56px; height:56px; border-radius:10px; overflow:hidden; flex-shrink:0;">
                            <x-property-visual :property="$p" :size="56"/>
                        </div>
                        <div style="min-width:0;">
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <span style="font-size:9.5px; font-weight:700; letter-spacing:.10em; text-transform:uppercase;
                                             padding:3px 7px; border-radius:4px;
                                             background: var(--primary-tint); color: var(--primary);">
                                    {{ $p->city ?? $p->state ?? __('Listed') }}
                                </span>
                                <span style="font-size:11px; color: var(--ink-3);">
                                    {{ __('Published') }}: {{ optional($p->created_at)->format('d M Y') ?? '—' }}
                                </span>
                            </div>
                            <div style="font-size:15px; font-weight:700; letter-spacing:-.02em;">{{ $p->name }}</div>
                            <div style="font-size:12px; color: var(--ink-3); margin-top:2px;">
                                {{ $p->rooms_count ?? 0 }} {{ __('rooms') }} · ★ {{ $p->rating ?? '—' }}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="cm-eyebrow" style="margin-bottom:5px;">{{ __('Revenue · 30d') }}</div>
                            <div class="mono" style="font-size:15px; font-weight:700; color: var(--primary);">
                                RM {{ number_format($p->stats_revenue_30d ?? 0) }}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="cm-eyebrow" style="margin-bottom:5px;">{{ __('From / night') }}</div>
                            <div class="mono" style="font-size:15px; font-weight:700;">
                                RM {{ number_format($p->stats_starting_rate ?? 0) }}
                            </div>
                            <span style="display:inline-block; margin-top:4px; font-size:9.5px; font-weight:600;
                                         padding:2px 7px; border-radius:999px;
                                         background: var(--ok-tint); color: var(--ok);">
                                {{ ucfirst($p->status ?? 'active') }}
                            </span>
                        </div>
                        <div style="width:32px; height:32px; border-radius:999px;
                                    border:1px solid var(--line); background: var(--bg-sunk);
                                    color: var(--ink-3);
                                    display:flex; align-items:center; justify-content:center;">
                            <x-icon name="more" :size="14"/>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>
