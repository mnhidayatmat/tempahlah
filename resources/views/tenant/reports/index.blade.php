<x-app-layout :title="__('Reports')">
    @php
        $deltaPill = function ($delta) {
            if ($delta === null) return ['cls' => 'pill', 'text' => '—'];
            $sign = $delta >= 0 ? '+' : '';
            $pct = round($delta * 100);
            $cls = $delta >= 0 ? 'pill-ok' : 'pill-err';
            return ['cls' => $cls, 'text' => $sign.$pct.'%'];
        };
    @endphp

    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Performance') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Reports') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ $periodStart->format('M Y') }} → {{ $periodEnd->format('M Y') }} · {{ __('trailing 12 months') }}
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <x-btn-link class="btn btn-sm" :href="route('tenant.reports.export-pdf')">{{ __('Export PDF') }}</x-btn-link>
            </div>
        </div>

        {{-- KPIs --}}
        @php
            $margin = $totalNetRevenue > 0 ? $totalProfit / $totalNetRevenue : null;
            $marginPill = $margin === null
                ? ['cls' => 'pill', 'text' => '—']
                : ['cls' => $totalProfit >= 0 ? 'pill-ok' : 'pill-err', 'text' => round($margin * 100).'% '.__('margin')];
            $kpis = [
                [__('Gross sales'), 'RM '.number_format($totalRevenue, 0), $deltaPill($revDelta), __('total billed to guests')],
                [__('Net profit'), 'RM '.number_format($totalProfit, 0), $marginPill, __('net revenue − expenses')],
                [__('Expenses'), 'RM '.number_format($totalExpenses, 0), ['cls' => 'pill', 'text' => '—'], __('cleaning, laundry, upkeep')],
                [__('Occupancy avg'), number_format($occupancyAvg * 100, 1).'%', $deltaPill($occDelta), __('across active rooms')],
                [__('ADR'), 'RM '.number_format($adr, 0), $deltaPill($adrDelta), __('blended weekday & weekend')],
                [__('RevPAR'), 'RM '.number_format($revPAR, 0), ['cls' => 'pill', 'text' => '—'], __('RM/available room/night')],
            ];
        @endphp
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 14px;">
            @foreach ($kpis as [$label, $value, $delta, $sub])
                <div class="hauz-card" style="padding: 18px;">
                    <div class="kicker" style="margin-bottom: 8px;">{{ $label }}</div>
                    <div style="display:flex; align-items:baseline; gap: 6px;">
                        <div class="display-3" style="line-height: 1;">{{ $value }}</div>
                        <span class="pill {{ $delta['cls'] }}" style="height: 18px; font-size: 10.5px;">{{ $delta['text'] }}</span>
                    </div>
                    <div style="margin-top: 6px; font-size: 12px; color: var(--ink-3);">{{ $sub }}</div>
                </div>
            @endforeach
        </div>

        {{-- Profit & loss trend: Sales, Revenue, Expenses and Profit drawn as
             four lines on one shared RM axis (all the same unit, so no dual
             axis needed). The zero baseline is emphasised whenever profit dips
             negative. Hover any month for the exact figures. --}}
        <div class="hauz-card" style="padding: 22px;">
            @php
                $series = [
                    ['key' => 'sales',      'label' => __('Sales'),    'color' => 'var(--info)'],
                    ['key' => 'netRevenue', 'label' => __('Revenue'),  'color' => 'var(--primary)'],
                    ['key' => 'expenses',   'label' => __('Expenses'), 'color' => 'var(--err)'],
                    ['key' => 'profit',     'label' => __('Profit'),   'color' => 'var(--ok)'],
                ];
            @endphp
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div class="kicker" style="margin-bottom: 4px;">{{ __('Profit & loss') }}</div>
                    <div style="font-size: 12.5px; color: var(--ink-3); max-width: 460px; line-height: 1.5;">
                        {{ __('Last 12 months. Sales = billed to guests · Revenue = sales − SST & tourism tax · Profit = revenue − expenses.') }}
                    </div>
                </div>
                <div style="display:flex; gap:16px; flex-wrap:wrap; font-size: 11.5px; color: var(--ink-2);">
                    @foreach ($series as $s)
                        <span style="display:inline-flex; align-items:center; gap:6px;">
                            <span style="display:inline-block; width:14px; height:3px; border-radius:2px; background: {{ $s['color'] }};"></span>{{ $s['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>

            @php
                $months = $monthly->values();
                $n = max(1, $months->count());

                // viewBox geometry — responsive (svg scales to container width).
                $W = 760; $H = 300;
                $padL = 54; $padR = 16; $padT = 14; $padB = 30;
                $plotW = $W - $padL - $padR;
                $plotH = $H - $padT - $padB;
                $ticks = 4;

                // Y-range spans every series and always includes the zero
                // baseline (profit can go negative in a loss-making month).
                $seriesKeys = ['sales', 'netRevenue', 'expenses', 'profit'];
                $allVals = [];
                foreach ($months as $m) {
                    foreach ($seriesKeys as $k) { $allVals[] = (float) ($m[$k] ?? 0); }
                }
                $rawMax = max(1.0, max($allVals));
                $rawMin = min(0.0, min($allVals));

                // "Nice" rounded bound (1/2/2.5/5/10 × 10ⁿ) so tick labels land
                // on clean numbers, applied to each side of zero independently.
                $niceCeil = function (float $v): float {
                    if ($v <= 0) return 1.0;
                    $exp = floor(log10($v));
                    $base = pow(10, $exp);
                    $frac = $v / $base;
                    $nf = $frac <= 1 ? 1 : ($frac <= 2 ? 2 : ($frac <= 2.5 ? 2.5 : ($frac <= 5 ? 5 : 10)));
                    return $nf * $base;
                };
                $yMax = $niceCeil($rawMax);
                $yMin = $rawMin < 0 ? -$niceCeil(abs($rawMin)) : 0.0;
                $range = max(1.0, $yMax - $yMin);

                // Points span the full plot width (edge to edge) for a line chart.
                $x = fn ($i) => $n > 1 ? $padL + $plotW * ($i / ($n - 1)) : $padL + $plotW / 2;
                $y = fn ($v) => $padT + $plotH * (($yMax - $v) / $range);
                $slot = $n > 1 ? $plotW / ($n - 1) : $plotW;

                $fmtK = function ($v) {
                    $a = abs($v);
                    $s = $a >= 1000 ? rtrim(rtrim(number_format($a / 1000, 1), '0'), '.').'k' : number_format($a, 0);
                    return ($v < 0 ? '-' : '').$s;
                };
            @endphp

            <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet"
                 style="width:100%; height:auto; display:block; overflow:visible;"
                 role="img" aria-label="{{ __('Monthly sales, revenue, expenses and profit') }}">

                {{-- Gridlines + RM tick labels --}}
                @for ($t = 0; $t <= $ticks; $t++)
                    @php
                        $gy = round($padT + $plotH * ($t / $ticks), 1);
                        $val = $yMax - $range * ($t / $ticks);
                    @endphp
                    <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}"
                          stroke="var(--line)" stroke-width="1" @if ($t !== 0 && $t !== $ticks) stroke-dasharray="2 5" @endif />
                    <text x="{{ $padL - 8 }}" y="{{ $gy + 3 }}" text-anchor="end" font-size="9"
                          fill="var(--ink-3)" font-family="ui-monospace, monospace">{{ $fmtK($val) }}</text>
                @endfor

                {{-- Zero baseline, emphasised when profit runs negative --}}
                @if ($yMin < 0)
                    <line x1="{{ $padL }}" y1="{{ round($y(0), 1) }}" x2="{{ $W - $padR }}" y2="{{ round($y(0), 1) }}"
                          stroke="var(--ink-3)" stroke-width="1.25" />
                @endif

                {{-- Invisible per-month hover columns → one tooltip listing all
                     four figures for that month. --}}
                @foreach ($months as $i => $m)
                    <rect x="{{ round($x($i) - $slot / 2, 1) }}" y="{{ $padT }}" width="{{ round($slot, 1) }}" height="{{ round($plotH, 1) }}" fill="transparent">
                        <title>{{ $m['label'] }}
{{ __('Sales') }}: RM {{ number_format($m['sales'], 0) }}
{{ __('Revenue') }}: RM {{ number_format($m['netRevenue'], 0) }}
{{ __('Expenses') }}: RM {{ number_format($m['expenses'], 0) }}
{{ __('Profit') }}: RM {{ number_format($m['profit'], 0) }}</title>
                    </rect>
                @endforeach

                {{-- Series lines (profit emphasised) --}}
                @foreach ($series as $s)
                    @php
                        $hero = $s['key'] === 'profit';
                        $pts = $months->map(fn ($m, $i) => round($x($i), 1).','.round($y((float) ($m[$s['key']] ?? 0)), 1))->implode(' ');
                    @endphp
                    <polyline points="{{ $pts }}" fill="none" stroke="{{ $s['color'] }}"
                              stroke-width="{{ $hero ? '2.75' : '1.75' }}" stroke-linejoin="round" stroke-linecap="round"
                              opacity="{{ $hero ? '1' : '0.9' }}" />
                @endforeach

                {{-- Data-point dots, drawn on top of the lines --}}
                @foreach ($series as $s)
                    @foreach ($months as $i => $m)
                        <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y((float) ($m[$s['key']] ?? 0)), 1) }}"
                                r="{{ $s['key'] === 'profit' ? '2.75' : '2' }}" fill="{{ $s['color'] }}"
                                stroke="var(--bg-elev)" stroke-width="1" />
                    @endforeach
                @endforeach

                {{-- Month labels --}}
                @foreach ($months as $i => $m)
                    <text x="{{ round($x($i), 1) }}" y="{{ $H - 12 }}" text-anchor="middle"
                          font-size="9" fill="var(--ink-3)">{{ $m['label'] }}</text>
                @endforeach
            </svg>
        </div>

        {{-- Two-up: property + source --}}
        <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap: 16px;">
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Revenue by property') }}</div>
                <div style="display:flex; flex-direction:column; gap: 12px;">
                    @forelse ($properties as $p)
                        @php $pct = $totalRevenue > 0 ? ($p['rev'] / $totalRevenue) * 100 : 0; @endphp
                        <div>
                            <div style="display:flex; justify-content:space-between; margin-bottom: 5px; font-size: 12.5px;">
                                <div>
                                    <span style="font-weight: 500;">{{ $p['name'] }}</span>
                                    <span style="color: var(--ink-3); margin-left: 8px;">· {{ $p['stays'] }} {{ __('stays') }} · ADR RM{{ $p['adr'] }}</span>
                                </div>
                                <span class="mono" style="font-weight: 500;">RM {{ number_format($p['rev'], 0) }}</span>
                            </div>
                            <div style="height: 6px; background: var(--bg-sunk); border-radius: 999px; overflow: hidden;">
                                <div style="width: {{ $pct }}%; height: 100%; background: var(--primary);"></div>
                            </div>
                        </div>
                    @empty
                        <div style="color: var(--ink-3); font-size: 13px;">{{ __('No property revenue yet — bookings will populate this chart.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Channel mix') }}</div>
                <div style="display:flex; flex-direction:column; gap: 12px;">
                    @forelse ($channels as $name => $value)
                        @php $pct = ($value / $channelTotal) * 100; @endphp
                        <div>
                            <div style="display:flex; justify-content:space-between; margin-bottom: 5px; font-size: 12.5px;">
                                <span style="font-weight: 500;">{{ ucfirst((string) $name) }}</span>
                                <span class="mono" style="color: var(--ink-3);">{{ number_format($pct, 0) }}% · RM {{ number_format($value, 0) }}</span>
                            </div>
                            <div style="height: 6px; background: var(--bg-sunk); border-radius: 999px; overflow: hidden;">
                                <div style="width: {{ $pct }}%; height: 100%; background: {{ $name === 'direct' ? 'var(--primary)' : 'var(--accent)' }};"></div>
                            </div>
                        </div>
                    @empty
                        <div style="color: var(--ink-3); font-size: 13px;">{{ __('No bookings in range yet.') }}</div>
                    @endforelse
                </div>
                @if ($channels->isNotEmpty())
                    <div style="margin-top: 16px; padding: 12px; background: var(--bg-sunk); border-radius: 8px; font-size: 12px; color: var(--ink-2); line-height: 1.5;">
                        <strong>{{ __('Insight:') }}</strong> {{ __('Direct bookings drive the majority — invest in WhatsApp templates and your booking link before adding more channels.') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
