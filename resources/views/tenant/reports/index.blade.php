<x-app-layout :title="__('Reports')">
    @php
        $maxRevenue = max(1, (float) $monthly->max('revenue'));
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
                <a href="{{ route('tenant.reports.export-pdf') }}" class="btn btn-sm">{{ __('Export PDF') }}</a>
            </div>
        </div>

        {{-- KPIs --}}
        @php
            $kpis = [
                [__('Total revenue'), 'RM '.number_format($totalRevenue, 0), $deltaPill($revDelta), __('vs prior 12 months')],
                [__('Occupancy avg'), number_format($occupancyAvg * 100, 1).'%', $deltaPill($occDelta), __('across active rooms')],
                [__('ADR'), 'RM '.number_format($adr, 0), $deltaPill($adrDelta), __('blended weekday & weekend')],
                [__('RevPAR'), 'RM '.number_format($revPAR, 0), ['cls' => 'pill', 'text' => '—'], __('RM/available room/night')],
            ];
        @endphp
        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 14px;">
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

        {{-- Trend chart: revenue (bars) + occupancy (line) as a dual-axis combo
             chart. Left axis = RM, right axis = occupancy %, drawn as a real
             connected SVG line with markers + gridlines so both series read
             clearly. Hover any bar/dot for the exact figures. --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 18px; flex-wrap: wrap; gap: 8px;">
                <div>
                    <div class="kicker" style="margin-bottom: 4px;">{{ __('Revenue & occupancy') }}</div>
                    <div style="font-size: 13px; color: var(--ink-3);">{{ __('Last 12 months · hover for exact figures') }}</div>
                </div>
                <div style="display:flex; gap:14px; font-size: 11.5px; color: var(--ink-3);">
                    <span><span style="display:inline-block; width:10px; height:10px; background: var(--primary); border-radius: 2px; margin-right: 5px; vertical-align: middle;"></span>{{ __('Revenue (RM)') }}</span>
                    <span><span style="display:inline-block; width:14px; height:2px; background: var(--accent); margin-right: 5px; vertical-align: middle;"></span>{{ __('Occupancy %') }}</span>
                </div>
            </div>

            @php
                $months = $monthly->values();
                $n = max(1, $months->count());

                // viewBox geometry — responsive (svg scales to container width).
                $W = 760; $H = 280;
                $padL = 52; $padR = 48; $padT = 16; $padB = 38;
                $plotW = $W - $padL - $padR;
                $plotH = $H - $padT - $padB;
                $slot = $plotW / $n;
                $barW = min(30, $slot * 0.5);

                // "Nice" rounded max for the revenue axis (1/2/2.5/5/10 × 10ⁿ)
                // so the gridline labels land on clean numbers.
                $niceCeil = function (float $v): float {
                    if ($v <= 0) return 1.0;
                    $exp = floor(log10($v));
                    $base = pow(10, $exp);
                    $frac = $v / $base;
                    $nf = $frac <= 1 ? 1 : ($frac <= 2 ? 2 : ($frac <= 2.5 ? 2.5 : ($frac <= 5 ? 5 : 10)));
                    return $nf * $base;
                };
                $revMax = max(1.0, $niceCeil((float) $months->max('revenue')));
                $ticks = 4;

                $xCenter = fn ($i) => $padL + $slot * ($i + 0.5);
                $yRev = fn ($v) => $padT + $plotH * (1 - ($v / $revMax));
                $yOcc = fn ($frac) => $padT + $plotH * (1 - max(0, min(1, (float) $frac)));

                $occPoints = $months
                    ->map(fn ($m, $i) => round($xCenter($i), 1).','.round($yOcc($m['occupancy']), 1))
                    ->implode(' ');

                $fmtK = fn ($v) => $v >= 1000
                    ? rtrim(rtrim(number_format($v / 1000, 1), '0'), '.').'k'
                    : number_format($v, 0);
            @endphp

            <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet"
                 style="width:100%; height:auto; display:block; overflow:visible;"
                 role="img" aria-label="{{ __('Monthly revenue and occupancy') }}">

                {{-- Gridlines + dual-axis tick labels (RM left, % right) --}}
                @for ($t = 0; $t <= $ticks; $t++)
                    @php
                        $gy = round($padT + $plotH * ($t / $ticks), 1);
                        $rv = $revMax * (1 - $t / $ticks);
                        $ov = 100 * (1 - $t / $ticks);
                    @endphp
                    <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}"
                          stroke="var(--line)" stroke-width="1" @if ($t !== $ticks) stroke-dasharray="2 5" @endif />
                    <text x="{{ $padL - 9 }}" y="{{ $gy + 3.5 }}" text-anchor="end" font-size="10.5"
                          fill="var(--ink-3)" font-family="ui-monospace, monospace">{{ $fmtK($rv) }}</text>
                    <text x="{{ $W - $padR + 9 }}" y="{{ $gy + 3.5 }}" text-anchor="start" font-size="10.5"
                          fill="var(--accent)" font-family="ui-monospace, monospace">{{ number_format($ov, 0) }}%</text>
                @endfor

                {{-- Revenue bars --}}
                @foreach ($months as $i => $m)
                    @php
                        $bx = round($xCenter($i) - $barW / 2, 1);
                        $by = round($yRev($m['revenue']), 1);
                        $bh = max(0, round(($padT + $plotH) - $by, 1));
                    @endphp
                    <rect x="{{ $bx }}" y="{{ $by }}" width="{{ round($barW, 1) }}" height="{{ $bh }}"
                          rx="2.5" fill="var(--primary)" opacity="0.9">
                        <title>{{ $m['label'] }} — RM {{ number_format($m['revenue'], 0) }} · {{ number_format($m['occupancy'] * 100, 1) }}% {{ __('occupancy') }}</title>
                    </rect>
                @endforeach

                {{-- Occupancy line + markers --}}
                <polyline points="{{ $occPoints }}" fill="none" stroke="var(--accent)"
                          stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
                @foreach ($months as $i => $m)
                    <circle cx="{{ round($xCenter($i), 1) }}" cy="{{ round($yOcc($m['occupancy']), 1) }}"
                            r="3.2" fill="var(--bg-elev)" stroke="var(--accent)" stroke-width="2">
                        <title>{{ $m['label'] }} — {{ number_format($m['occupancy'] * 100, 1) }}% {{ __('occupancy') }}</title>
                    </circle>
                @endforeach

                {{-- Month labels --}}
                @foreach ($months as $i => $m)
                    <text x="{{ round($xCenter($i), 1) }}" y="{{ $H - 14 }}" text-anchor="middle"
                          font-size="10.5" fill="var(--ink-3)">{{ $m['label'] }}</text>
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
