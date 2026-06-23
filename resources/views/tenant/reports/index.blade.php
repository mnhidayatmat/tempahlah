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

        {{-- Trend chart: revenue + number of bookings as grouped bars on a
             dual axis. Left axis = RM (revenue), right axis = booking count.
             Two bars per month sit side by side so the months line up and
             both series stay easy to compare. Hover any bar for exact figures. --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 18px; flex-wrap: wrap; gap: 8px;">
                <div>
                    <div class="kicker" style="margin-bottom: 4px;">{{ __('Revenue & bookings') }}</div>
                    <div style="font-size: 13px; color: var(--ink-3);">{{ __('Last 12 months · % above bars = occupancy · hover for exact figures') }}</div>
                </div>
                <div style="display:flex; gap:14px; font-size: 11.5px; color: var(--ink-3);">
                    <span><span style="display:inline-block; width:10px; height:10px; background: var(--primary); border-radius: 2px; margin-right: 5px; vertical-align: middle;"></span>{{ __('Revenue (RM)') }}</span>
                    <span><span style="display:inline-block; width:10px; height:10px; background: var(--accent); border-radius: 2px; margin-right: 5px; vertical-align: middle;"></span>{{ __('Bookings') }}</span>
                </div>
            </div>

            @php
                $months = $monthly->values();
                $n = max(1, $months->count());

                // viewBox geometry — responsive (svg scales to container width).
                $W = 760; $H = 280;
                $padL = 52; $padR = 44; $padT = 16; $padB = 38;
                $plotW = $W - $padL - $padR;
                $plotH = $H - $padT - $padB;
                $slot = $plotW / $n;
                $ticks = 4;

                // Two bars per month (revenue + bookings), centred in the slot.
                $pairGap = 3;
                $barW = max(4, min(18, ($slot * 0.62 - $pairGap) / 2));

                // "Nice" rounded max for the RM axis (1/2/2.5/5/10 × 10ⁿ) so the
                // gridline labels land on clean numbers.
                $niceCeil = function (float $v): float {
                    if ($v <= 0) return 1.0;
                    $exp = floor(log10($v));
                    $base = pow(10, $exp);
                    $frac = $v / $base;
                    $nf = $frac <= 1 ? 1 : ($frac <= 2 ? 2 : ($frac <= 2.5 ? 2.5 : ($frac <= 5 ? 5 : 10)));
                    return $nf * $base;
                };
                $revMax = max(1.0, $niceCeil((float) $months->max('revenue')));

                // Booking-count axis rounds up to a multiple of $ticks so every
                // right-hand tick label is a whole number.
                $maxBookings = (int) $months->max('bookings');
                $countMax = max($ticks, (int) (ceil($maxBookings / $ticks) * $ticks));

                $xCenter = fn ($i) => $padL + $slot * ($i + 0.5);
                $yRev = fn ($v) => $padT + $plotH * (1 - ($v / $revMax));
                $yCount = fn ($c) => $padT + $plotH * (1 - ($c / $countMax));
                $baseline = $padT + $plotH;

                $fmtK = fn ($v) => $v >= 1000
                    ? rtrim(rtrim(number_format($v / 1000, 1), '0'), '.').'k'
                    : number_format($v, 0);
            @endphp

            <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet"
                 style="width:100%; height:auto; display:block; overflow:visible;"
                 role="img" aria-label="{{ __('Monthly revenue and number of bookings') }}">

                {{-- Gridlines + dual-axis tick labels (RM left, bookings right) --}}
                @for ($t = 0; $t <= $ticks; $t++)
                    @php
                        $gy = round($padT + $plotH * ($t / $ticks), 1);
                        $rv = $revMax * (1 - $t / $ticks);
                        $cv = $countMax * (1 - $t / $ticks);
                    @endphp
                    <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}"
                          stroke="var(--line)" stroke-width="1" @if ($t !== $ticks) stroke-dasharray="2 5" @endif />
                    <text x="{{ $padL - 8 }}" y="{{ $gy + 3 }}" text-anchor="end" font-size="9"
                          fill="var(--ink-3)" font-family="ui-monospace, monospace">{{ $fmtK($rv) }}</text>
                    <text x="{{ $W - $padR + 8 }}" y="{{ $gy + 3 }}" text-anchor="start" font-size="9"
                          fill="var(--accent)" font-family="ui-monospace, monospace">{{ number_format($cv, 0) }}</text>
                @endfor

                {{-- Grouped bars: revenue (left of centre) + bookings (right).
                     Revenue value is printed above its bar, the month's
                     occupancy % above the bookings bar. --}}
                @foreach ($months as $i => $m)
                    @php
                        $cx = $xCenter($i);
                        $revX = round($cx - $pairGap / 2 - $barW, 1);
                        $cntX = round($cx + $pairGap / 2, 1);
                        $revY = round($yRev($m['revenue']), 1);
                        $cntY = round($yCount($m['bookings']), 1);
                        $revH = max(0, round($baseline - $revY, 1));
                        $cntH = max(0, round($baseline - $cntY, 1));
                        $occPct = number_format($m['occupancy'] * 100, 0);
                        // Centre each value over its own bar, floated just above
                        // the bar's top — never riding off the top of the plot.
                        $revLabelX = round($revX + $barW / 2, 1);
                        $revLabelY = round(max($padT + 8, $revY - 6), 1);
                        $occLabelX = round($cntX + $barW / 2, 1);
                        $occLabelY = round(max($padT + 8, $cntY - 6), 1);
                    @endphp
                    <rect x="{{ $revX }}" y="{{ $revY }}" width="{{ round($barW, 1) }}" height="{{ $revH }}"
                          rx="2" fill="var(--primary)" opacity="0.9">
                        <title>{{ $m['label'] }} — RM {{ number_format($m['revenue'], 0) }} · {{ $m['bookings'] }} {{ trans_choice('{1} booking|[2,*] bookings', $m['bookings']) }} · {{ $occPct }}% {{ __('occupancy') }}</title>
                    </rect>
                    <rect x="{{ $cntX }}" y="{{ $cntY }}" width="{{ round($barW, 1) }}" height="{{ $cntH }}"
                          rx="2" fill="var(--accent)" opacity="0.9">
                        <title>{{ $m['label'] }} — {{ $m['bookings'] }} {{ trans_choice('{1} booking|[2,*] bookings', $m['bookings']) }} · RM {{ number_format($m['revenue'], 0) }} · {{ $occPct }}% {{ __('occupancy') }}</title>
                    </rect>
                    @if ($m['revenue'] > 0)
                        <text x="{{ $revLabelX }}" y="{{ $revLabelY }}" text-anchor="middle"
                              font-size="8.5" font-weight="600" fill="var(--ink-2)"
                              font-family="ui-monospace, monospace">{{ $fmtK($m['revenue']) }}</text>
                    @endif
                    <text x="{{ $occLabelX }}" y="{{ $occLabelY }}" text-anchor="middle"
                          font-size="8.5" font-weight="600" fill="var(--ink-2)"
                          font-family="ui-monospace, monospace">{{ $occPct }}%</text>
                @endforeach

                {{-- Month labels --}}
                @foreach ($months as $i => $m)
                    <text x="{{ round($xCenter($i), 1) }}" y="{{ $H - 16 }}" text-anchor="middle"
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
