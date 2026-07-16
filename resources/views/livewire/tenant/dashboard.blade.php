@php
    $firstName = explode(' ', auth()->user()->name)[0] ?? '';
    $propertyCount = $shelf->count() ?: ($stats['properties'] ?? 0);
    $first = $shelf->first();

    // Income chart geometry — one smoothed cubic path per homestay series.
    $W = 720; $H = 240; $PAD_T = 20; $PAD_B = 40;
    $innerW = $W; $innerH = $H - $PAD_T - $PAD_B;
    $chartSeries = $series['series'] ?? [];
    $labels = $series['labels'] ?? [];
    $max = max(1, $series['max'] ?? 1);
    $n = max(count($labels), 1);
    $step = $n > 1 ? $innerW / ($n - 1) : 0;
    $singleSeries = count($chartSeries) === 1;

    // id → line colour, so each homestay's row inside the stat cards matches
    // its line in the income chart.
    $homeColors = collect($chartSeries)->pluck('color', 'id')->all();

    // Returns [pathD, points[]] for a series' value array.
    $buildPath = function (array $vals) use ($step, $PAD_T, $innerH, $max) {
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = [round($i * $step, 1), round($PAD_T + $innerH - ($v / $max) * $innerH, 1)];
        }
        if (empty($pts)) {
            return ['', []];
        }
        $d = '';
        foreach ($pts as $i => $p) {
            if ($i === 0) {
                $d .= 'M'.$p[0].','.$p[1];
            } else {
                $prev = $pts[$i - 1];
                $cx1 = round($prev[0] + ($p[0] - $prev[0]) * 0.5, 1);
                $cx2 = round($p[0] - ($p[0] - $prev[0]) * 0.5, 1);
                $d .= ' C'.$cx1.','.$prev[1].' '.$cx2.','.$p[1].' '.$p[0].','.$p[1];
            }
        }
        return [$d, $pts];
    };
@endphp

<div wire:poll.60s class="dash-root">

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

            {{-- Mobile-only public booking link. The full share button lives in
                 .dash-hero-actions, which is hidden on phones — so surface the
                 link right here in the hero card. Tap the chip to copy; the
                 round button shares to WhatsApp. Both stamp the onboarding step. --}}
            @if ($publicUrl)
                <style>
                    .dash-hero-link { display: none; }
                    @media (max-width: 768px) {
                        .dash-hero-link { display: flex; align-items: center; gap: 6px; margin-top: 10px; }
                    }
                    .dash-hero-link-main { flex: 1; min-width: 0; display: flex; align-items: center; gap: 7px;
                        background: var(--bg-sunk); border: .5px solid var(--line); border-radius: 999px;
                        padding: 7px 12px; cursor: pointer; color: var(--ink); text-align: left; }
                    .dash-hero-link-url { flex: 1; min-width: 0; font-family: var(--font-mono, monospace); font-size: 11.5px;
                        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                    .dash-hero-link-copy { flex-shrink: 0; font-size: 11px; font-weight: 700; color: var(--primary); }
                    .dash-hero-link-wa { flex-shrink: 0; width: 34px; height: 34px; border-radius: 999px;
                        display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
                        background: var(--primary); color: var(--primary-ink); }
                </style>
                <div class="dash-hero-link" x-data="{
                        copied: false,
                        url: @js($publicUrl),
                        stamp() { @this.call('shareBookingLink'); },
                        copy() {
                            navigator.clipboard.writeText(this.url).then(() => {
                                this.copied = true; this.stamp();
                                setTimeout(() => this.copied = false, 1800);
                            });
                        },
                     }">
                    <button type="button" class="dash-hero-link-main" @click="copy()" :aria-label="'{{ __('Copy booking link') }}'">
                        <x-icon name="link" :size="12"/>
                        <span class="dash-hero-link-url">{{ preg_replace('#^https?://#', '', $publicUrl) }}</span>
                        <span class="dash-hero-link-copy" x-text="copied ? @js(__('Copied!')) : @js(__('Copy'))"></span>
                    </button>
                    <a class="dash-hero-link-wa" target="_blank" rel="noopener" aria-label="WhatsApp"
                       :href="'https://wa.me/?text=' + encodeURIComponent(@js(__('Book our homestay directly 👉')) + ' ' + url)"
                       @click="stamp()"><x-icon name="message" :size="14"/></a>
                </div>
            @endif
        </div>

        <div class="dash-hero-actions">
            @if ($publicUrl)
                {{-- Small, always-visible share button. Copies / opens / WhatsApps the
                     public booking page so the host can post it on social media.
                     Stamps the onboarding "shared" step on first use. --}}
                <div class="dash-share" x-data="{
                        open: false,
                        copied: false,
                        url: @js($publicUrl),
                        stamp() { @this.call('shareBookingLink'); },
                        copy() {
                            navigator.clipboard.writeText(this.url).then(() => {
                                this.copied = true; this.stamp();
                                setTimeout(() => this.copied = false, 1800);
                            });
                        },
                     }" @click.outside="open = false" style="position:relative;">
                    <button type="button" class="btn" @click="open = !open">
                        <x-icon name="link" :size="13"/> {{ __('Share booking link') }}
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top
                         style="position:absolute; top:calc(100% + 6px); right:0; z-index:40; width:280px;
                                background: var(--bg-elev); border:1px solid var(--line); border-radius: var(--r-md);
                                box-shadow: var(--sh-pop, 0 10px 30px rgba(0,0,0,.14)); padding:12px;">
                        <div style="font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-3); margin-bottom:6px;">
                            {{ __('Your public booking page') }}
                        </div>
                        <div style="font-family:var(--font-mono, monospace); font-size:12px; color:var(--ink); background:var(--bg-sunk);
                                    border:.5px solid var(--line); border-radius: var(--r-sm); padding:8px 10px; margin-bottom:10px;
                                    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ preg_replace('#^https?://#', '', $publicUrl) }}</div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="btn btn-sm" style="flex:1; justify-content:center;" @click="copy()">
                                <span x-show="!copied">{{ __('Copy') }}</span>
                                <span x-show="copied" x-cloak>✓ {{ __('Copied!') }}</span>
                            </button>
                            <a class="btn btn-sm" style="flex:1; justify-content:center;" :href="url" target="_blank" rel="noopener" @click="stamp()">{{ __('Open') }}</a>
                            <a class="btn btn-sm btn-primary" style="flex:1; justify-content:center;" target="_blank" rel="noopener"
                               :href="'https://wa.me/?text=' + encodeURIComponent(@js(__('Book our homestay directly 👉')) + ' ' + url)"
                               @click="stamp()"><x-icon name="message" :size="12"/></a>
                        </div>
                    </div>
                </div>
            @endif
            <a href="{{ route('tenant.settings.index') }}" class="btn">{{ __('Profile Settings') }}</a>
            <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary">
                <x-icon name="plus" :size="13"/> {{ __('Add homestay') }}
            </a>
        </div>
    </div>

    {{-- === FIRST-RUN SETUP CHECKLIST ===
         Sits above the stat cards on purpose: for a brand-new tenant every stat
         below reads RM 0.00, so the actionable thing belongs first. Removes
         itself once all steps are satisfied. --}}
    @include('partials.setup-checklist')

    {{-- === STAT CARDS === --}}
    {{-- auto-fit so 4 cols on desktop, 2 cols on tablet/mobile naturally
         (no inline grid-template that has to be CSS-overridden later). --}}
    <div class="dash-stats @if (!empty($breakdown)) dash-stats--split @endif">
        @foreach ([
            ['label' => __('Total Earnings'),    'value' => 'RM '.number_format($stats['cumulative'], 2),    'sub' => __('All-time confirmed earnings'), 'icon' => 'card',    'tone' => 'primary', 'bkey' => 'earnings'],
            ['label' => __('This Month'),        'value' => 'RM '.number_format($stats['month_revenue'], 2), 'sub' => now()->isoFormat('MMMM').' '.__('earnings'), 'icon' => 'chart', 'tone' => 'ok', 'bkey' => 'month'],
            ['label' => __('Expected Payments'), 'value' => 'RM '.number_format($stats['expected'], 2),      'sub' => $stats['expected_count'] > 0 ? trans_choice('Balance due from :count booking|Balance due from :count bookings', $stats['expected_count']) : __('No balances outstanding'), 'icon' => 'clock', 'tone' => 'accent', 'bkey' => 'expected'],
            ['label' => __('This Month Cost'),   'value' => 'RM '.number_format($stats['month_cost'], 2),    'sub' => __('Cleaning · laundry · upkeep'), 'icon' => 'receipt', 'tone' => 'warn', 'bkey' => 'cost'],
        ] as $card)
            <div class="card dash-stat dash-stat--{{ $card['tone'] }}">
                <div class="dash-stat-top">
                    <div class="cm-eyebrow dash-stat-label">{{ $card['label'] }}</div>
                    <div class="dash-stat-icon">
                        <x-icon :name="$card['icon']" :size="16"/>
                    </div>
                </div>
                <div class="dash-stat-value">{{ $card['value'] }}</div>
                <div class="dash-stat-sub">{{ $card['sub'] }}</div>

                {{-- Per-homestay split, embedded in the card (multi-property only).
                     Each row's dot matches that homestay's line in the income chart.
                     The card value above stays the grand total. --}}
                @if (!empty($breakdown))
                    <div class="dash-stat-split">
                        @foreach ($breakdown as $b)
                            <div class="dash-stat-split-row">
                                <span class="dash-stat-split-name">
                                    <span class="dash-stat-split-dot" style="background: {{ $homeColors[$b['id']] ?? 'var(--ink-4)' }};"></span>
                                    <span class="dash-stat-split-label">{{ $b['name'] }}</span>
                                </span>
                                <span class="dash-stat-split-val">RM {{ number_format($b[$card['bkey']], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- === MAIN: CHART + TRANSACTIONS === --}}
    <div class="dash-main">

        {{-- INCOME CHART --}}
        <style>
            .dash-chart-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; }
            .dash-chart-range { display:inline-flex; gap:2px; padding:3px; background: var(--bg-elev); border-radius:999px; border:.5px solid var(--line); flex-shrink:0; }
            .dash-chart-range button { border:0; border-radius:999px; padding:7px 14px; font-size:12.5px; line-height:1.2; white-space:nowrap; cursor:pointer; }
            @media (max-width: 640px) {
                .dash-chart-head { flex-direction:column; align-items:stretch; gap:14px; }
                .dash-chart-range { display:flex; width:100%; }
                .dash-chart-range button { flex:1 1 0; padding:8px 6px; font-size:12px; }
            }
        </style>
        <div class="card dash-chart-card" style="padding:24px;">
            <div class="dash-chart-head">
                <div>
                    <div class="cm-eyebrow-primary" style="margin-bottom:6px;">{{ __('Weekly Metrics Rhythm') }}</div>
                    <h3 style="margin:0; font-size:18px; font-weight:700; letter-spacing:-.02em;">{{ __('Booking Income Stream') }}</h3>
                    <div class="dash-chart-desc" style="font-size:12.5px; color: var(--ink-3); margin-top:4px;">
                        @if ($singleSeries)
                            {{ __('Cumulative booking income across the period.') }}
                        @else
                            {{ __('Cumulative income per homestay — one line each.') }}
                        @endif
                    </div>
                </div>
                <div class="dash-chart-range">
                    @foreach (['30d' => __('Last 30 Days'), 'qtr' => __('Quarterly'), 'ytd' => __('YTD')] as $key => $label)
                        @php $active = $range === $key; @endphp
                        <button wire:click="setRange('{{ $key }}')"
                                style="background: {{ $active ? 'var(--primary)' : 'transparent' }};
                                       color: {{ $active ? 'var(--primary-ink)' : 'var(--ink-2)' }};
                                       font-weight: {{ $active ? '600' : '500' }};">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            {{-- SVG income chart — one line per homestay --}}
            <div style="width:100%; overflow:hidden;">
                <svg class="dash-chart-svg" viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="dash-income-fill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.18"/>
                            <stop offset="100%" stop-color="var(--primary)" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    @foreach ($chartSeries as $s)
                        @php [$d, $pts] = $buildPath($s['values']); @endphp
                        @if ($d !== '')
                            @if ($singleSeries)
                                {{-- Soft area fill only when there's a single line, else it'd muddy the graph. --}}
                                <path d="{{ $d.' L'.end($pts)[0].','.($PAD_T + $innerH).' L'.$pts[0][0].','.($PAD_T + $innerH).' Z' }}" fill="url(#dash-income-fill)"/>
                            @endif
                            <path d="{{ $d }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            @php $lp = end($pts); @endphp
                            <circle cx="{{ $lp[0] }}" cy="{{ $lp[1] }}" r="3.5" fill="{{ $s['color'] }}"/>
                        @endif
                    @endforeach
                </svg>
                <div style="display:flex; justify-content:space-between; font-size:11px; color: var(--ink-3); padding: 0 4px;">
                    @foreach ($labels as $i => $l)
                        @if ($i % 2 === 0)<span>{{ $l }}</span>@endif
                    @endforeach
                </div>

                {{-- Legend (only when more than one homestay) --}}
                @if (count($chartSeries) > 1)
                    <div style="display:flex; flex-wrap:wrap; gap:8px 16px; margin-top:14px; padding-top:12px; border-top:1px solid var(--line);">
                        @foreach ($chartSeries as $s)
                            <span style="display:inline-flex; align-items:center; gap:6px; font-size:12px; color: var(--ink-2);">
                                <span style="width:11px; height:11px; border-radius:3px; background: {{ $s['color'] }}; flex-shrink:0;"></span>
                                {{ $s['name'] }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- TRANSACTIONS LOG --}}
        <div class="card dash-txn-card" style="padding:0; overflow:hidden;">
            <div style="padding:18px 20px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between;">
                <div class="cm-eyebrow" style="color: var(--ink-2);">{{ __('Checkout Transactions Log') }}</div>
                <span class="pulse-dot" style="width:8px; height:8px; border-radius:999px; background: var(--ok); box-shadow: 0 0 0 4px var(--ok-tint);"></span>
            </div>

            @forelse ($transactions as $i => $t)
                <div class="dash-txn-row" style="padding:14px 20px; {{ $i < $transactions->count() - 1 ? 'border-bottom:1px solid var(--line);' : '' }}">
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
        <div class="card dash-actions" style="padding:18px 22px;">
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
    <div class="dash-shelf-section">
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
            <div class="dash-shelf">
                @foreach ($shelf as $p)
                    <a href="{{ route('tenant.properties.show', $p->id) }}" class="card dash-shelf-row">
                        <div class="dash-shelf-thumb">
                            <x-property-visual :property="$p" :size="56" :index="$loop->index"/>
                        </div>

                        <div class="dash-shelf-main">
                            <div class="dash-shelf-tags">
                                <span class="dash-shelf-city">{{ $p->city ?? $p->state ?? __('Listed') }}</span>
                                <span class="dash-shelf-status">{{ ucfirst($p->status ?? 'active') }}</span>
                                <span class="dash-shelf-published">
                                    {{ __('Published') }}: {{ optional($p->created_at)->timezone(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->format('d M Y') ?? '—' }}
                                </span>
                            </div>
                            <div class="dash-shelf-name">{{ $p->name }}</div>
                            <div class="dash-shelf-meta">
                                {{ $p->rooms_count ?? 0 }} {{ __('rooms') }} · ★ {{ $p->rating ?? '—' }}
                            </div>
                        </div>

                        <div class="dash-shelf-stat dash-shelf-stat-revenue">
                            <div class="cm-eyebrow dash-shelf-stat-label">{{ __('Revenue · 30d') }}</div>
                            <div class="mono dash-shelf-stat-value" style="color: var(--primary);">
                                RM {{ number_format($p->stats_revenue_30d ?? 0) }}
                            </div>
                        </div>

                        <div class="dash-shelf-stat dash-shelf-stat-rate">
                            <div class="cm-eyebrow dash-shelf-stat-label">{{ __('From / night') }}</div>
                            <div class="mono dash-shelf-stat-value">
                                RM {{ number_format($p->stats_starting_rate ?? 0) }}
                            </div>
                        </div>

                        <div class="dash-shelf-more" aria-hidden="true">
                            <x-icon name="more" :size="14"/>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>
