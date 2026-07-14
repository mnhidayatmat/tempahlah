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

        {{-- Trend chart: revenue + number of bookings as grouped bars on a
             dual axis (left = RM revenue, right = booking count), with the
             monthly PROFIT overlaid as a single line on the RM axis. The RM
             axis extends below zero (with an emphasised baseline) only when a
             month runs at a loss. Hover a bar for exact figures. --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 18px; flex-wrap: wrap; gap: 8px;">
                <div>
                    <div class="kicker" style="margin-bottom: 4px;">{{ __('Revenue, bookings, expenses & profit') }}</div>
                    <div style="font-size: 13px; color: var(--ink-3);">{{ __('Last 12 months · % above bars = occupancy · hover for exact figures') }}</div>
                </div>
                <div style="display:flex; gap:14px; flex-wrap:wrap; font-size: 11.5px; color: var(--ink-2);">
                    <span style="display:inline-flex; align-items:center; gap:5px;"><span style="display:inline-block; width:10px; height:10px; background: var(--primary); border-radius: 2px;"></span>{{ __('Revenue (RM)') }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px;"><span style="display:inline-block; width:10px; height:10px; background: var(--accent); border-radius: 2px;"></span>{{ __('Bookings') }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px;"><span style="display:inline-block; width:10px; height:10px; background: var(--err); border-radius: 2px;"></span>{{ __('Expenses (RM)') }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px;"><span style="display:inline-block; width:14px; height:3px; background: var(--ok); border-radius: 2px;"></span>{{ __('Profit (RM)') }}</span>
                </div>
            </div>

            @php
                $months = $monthly->values();
                $n = max(1, $months->count());

                // viewBox geometry — responsive (svg scales to container width).
                $W = 800; $H = 270;
                $padL = 52; $padR = 44; $padT = 18; $padB = 34;
                $plotW = $W - $padL - $padR;
                $plotH = $H - $padT - $padB;
                $slot = $plotW / $n;
                $ticks = 4;

                // Three bars per month (revenue + bookings + expenses), centred
                // in the slot with two gaps between them.
                $pairGap = 2.5;
                $barW = max(3.5, min(15, ($slot * 0.64 - 2 * $pairGap) / 3));
                $groupW = $barW * 3 + $pairGap * 2;

                // "Nice" rounded bound (1/2/2.5/5/10 × 10ⁿ) so gridline labels
                // land on clean numbers.
                $niceCeil = function (float $v): float {
                    if ($v <= 0) return 1.0;
                    $exp = floor(log10($v));
                    $base = pow(10, $exp);
                    $frac = $v / $base;
                    $nf = $frac <= 1 ? 1 : ($frac <= 2 ? 2 : ($frac <= 2.5 ? 2.5 : ($frac <= 5 ? 5 : 10)));
                    return $nf * $base;
                };

                // RM axis: top from revenue (profit ≤ revenue always), bottom
                // drops below zero only if a month is loss-making.
                $revTop  = max((float) $months->max('revenue'), (float) $months->max('profit'), (float) $months->max('expenses'));
                $rmMax   = $niceCeil(max(1.0, $revTop));
                $minProfit = (float) $months->min('profit');
                $rmMin   = $minProfit < 0 ? -$niceCeil(abs($minProfit)) : 0.0;
                $rmSpan  = max(1.0, $rmMax - $rmMin);

                // Booking-count axis rounds up to a multiple of $ticks so every
                // right-hand tick label is a whole number.
                $maxBookings = (int) $months->max('bookings');
                $countMax = max($ticks, (int) (ceil($maxBookings / $ticks) * $ticks));

                $xCenter = fn ($i) => $padL + $slot * ($i + 0.5);
                $yRev    = fn ($v) => $padT + $plotH * (($rmMax - $v) / $rmSpan);   // RM axis, negatives-aware
                $zeroY   = $padT + $plotH * (($rmMax - 0) / $rmSpan);               // the RM zero line
                $posH    = max(1.0, $zeroY - $padT);                                // height of the positive region
                $yCount  = fn ($c) => $zeroY - $posH * ($c / $countMax);            // bookings grow up from zero

                $fmtK = function ($v) {
                    $a = abs($v);
                    $s = $a >= 1000 ? rtrim(rtrim(number_format($a / 1000, 1), '0'), '.').'k' : number_format($a, 0);
                    return ($v < 0 ? '-' : '').$s;
                };
            @endphp

            <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet"
                 style="width:100%; height:auto; display:block; overflow:visible;"
                 role="img" aria-label="{{ __('Monthly revenue, bookings, expenses and profit') }}">

                {{-- Gridlines + dual-axis tick labels (RM left, bookings right) --}}
                @for ($t = 0; $t <= $ticks; $t++)
                    @php
                        $rmVal = $rmMax - $rmSpan * ($t / $ticks);
                        $gy = round($yRev($rmVal), 1);
                        $cv = $rmVal >= 0 ? $countMax * ($rmVal / max(1.0, $rmMax)) : null;
                    @endphp
                    <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}"
                          stroke="var(--line)" stroke-width="1" @if ($t !== 0 && $t !== $ticks) stroke-dasharray="2 5" @endif />
                    <text x="{{ $padL - 8 }}" y="{{ $gy + 3 }}" text-anchor="end" font-size="9"
                          fill="var(--ink-3)" font-family="ui-monospace, monospace">{{ $fmtK($rmVal) }}</text>
                    @if ($cv !== null)
                        <text x="{{ $W - $padR + 8 }}" y="{{ $gy + 3 }}" text-anchor="start" font-size="9"
                              fill="var(--accent)" font-family="ui-monospace, monospace">{{ number_format($cv, 0) }}</text>
                    @endif
                @endfor

                {{-- Emphasised RM zero baseline (only meaningful once a loss pushes the axis negative) --}}
                @if ($rmMin < 0)
                    <line x1="{{ $padL }}" y1="{{ round($zeroY, 1) }}" x2="{{ $W - $padR }}" y2="{{ round($zeroY, 1) }}"
                          stroke="var(--ink-3)" stroke-width="1.25" />
                @endif

                {{-- Grouped bars: revenue (left of centre) + bookings (right),
                     both growing up from the RM zero line. --}}
                @foreach ($months as $i => $m)
                    @php
                        $cx = $xCenter($i);
                        $x0 = $cx - $groupW / 2;                     // left edge of the 3-bar group
                        $revX = round($x0, 1);                        // 1st: revenue (RM axis)
                        $cntX = round($x0 + $barW + $pairGap, 1);     // 2nd: bookings (count axis)
                        $expX = round($x0 + 2 * ($barW + $pairGap), 1); // 3rd: expenses (RM axis)
                        $revY = round($yRev($m['revenue']), 1);
                        $cntY = round($yCount($m['bookings']), 1);
                        $expY = round($yRev($m['expenses']), 1);
                        $revH = max(0, round($zeroY - $revY, 1));
                        $cntH = max(0, round($zeroY - $cntY, 1));
                        $expH = max(0, round($zeroY - $expY, 1));
                        $occPct = number_format($m['occupancy'] * 100, 0);
                        $revLabelX = round($revX + $barW / 2, 1);
                        $revLabelY = round(max($padT + 8, $revY - 6), 1);
                        $occLabelX = round($cntX + $barW / 2, 1);
                        $occLabelY = round(max($padT + 8, $cntY - 6), 1);
                        $expLabelX = round($expX + $barW / 2, 1);
                        $expLabelY = round(max($padT + 8, $expY - 6), 1);
                        $tip = $m['label'].' — RM '.number_format($m['revenue'], 0).' · '.$m['bookings'].' '.trans_choice('{1} booking|[2,*] bookings', $m['bookings']).' · '.$occPct.'% '.__('occupancy').' · '.__('Expenses').' RM '.number_format($m['expenses'], 0).' · '.__('Profit').' RM '.number_format($m['profit'], 0);
                    @endphp
                    <rect x="{{ $revX }}" y="{{ $revY }}" width="{{ round($barW, 1) }}" height="{{ $revH }}"
                          rx="2" fill="var(--primary)" opacity="0.9"><title>{{ $tip }}</title></rect>
                    <rect x="{{ $cntX }}" y="{{ $cntY }}" width="{{ round($barW, 1) }}" height="{{ $cntH }}"
                          rx="2" fill="var(--accent)" opacity="0.9"><title>{{ $tip }}</title></rect>
                    <rect x="{{ $expX }}" y="{{ $expY }}" width="{{ round($barW, 1) }}" height="{{ $expH }}"
                          rx="2" fill="var(--err)" opacity="0.9"><title>{{ $tip }}</title></rect>
                    @if ($m['revenue'] > 0)
                        <text x="{{ $revLabelX }}" y="{{ $revLabelY }}" text-anchor="middle"
                              font-size="8" font-weight="600" fill="var(--ink-2)"
                              font-family="ui-monospace, monospace">{{ $fmtK($m['revenue']) }}</text>
                    @endif
                    <text x="{{ $occLabelX }}" y="{{ $occLabelY }}" text-anchor="middle"
                          font-size="8" font-weight="600" fill="var(--ink-2)"
                          font-family="ui-monospace, monospace">{{ $occPct }}%</text>
                    @if ($m['expenses'] > 0)
                        <text x="{{ $expLabelX }}" y="{{ $expLabelY }}" text-anchor="middle"
                              font-size="8" font-weight="600" fill="var(--err)"
                              font-family="ui-monospace, monospace">{{ $fmtK($m['expenses']) }}</text>
                    @endif
                @endforeach

                {{-- Profit line on the RM axis --}}
                @php
                    $profitPts = $months->map(fn ($m, $i) => round($xCenter($i), 1).','.round($yRev((float) $m['profit']), 1))->implode(' ');
                @endphp
                <polyline points="{{ $profitPts }}" fill="none" stroke="var(--ok)"
                          stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
                @foreach ($months as $i => $m)
                    <circle cx="{{ round($xCenter($i), 1) }}" cy="{{ round($yRev((float) $m['profit']), 1) }}"
                            r="2.75" fill="var(--ok)" stroke="var(--bg-elev)" stroke-width="1"><title>{{ $m['label'] }} — {{ __('Profit') }} RM {{ number_format($m['profit'], 0) }}</title></circle>
                @endforeach

                {{-- Month labels --}}
                @foreach ($months as $i => $m)
                    <text x="{{ round($xCenter($i), 1) }}" y="{{ $H - 14 }}" text-anchor="middle"
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

        {{-- Expenses breakdown — where the operating spend goes. Sums to the
             Expenses KPI above (cleaning + laundry + maintenance + ledger). --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
                <div class="kicker">{{ __('Expenses breakdown') }}</div>
                <div class="mono" style="font-size: 12.5px; color: var(--ink-3);">{{ __('Total') }} RM {{ number_format($totalExpenses, 0) }}</div>
            </div>
            @php
                $expenseRows = [
                    [__('Cleaning'), (float) $expenseBreakdown['cleaning'], 'var(--primary)'],
                    [__('Laundry'), (float) $expenseBreakdown['laundry'], 'var(--accent)'],
                    [__('Maintenance'), (float) $expenseBreakdown['maintenance'], 'var(--warn)'],
                    [__('Other expenses'), (float) $expenseBreakdown['other'], 'var(--ink-3)'],
                ];
            @endphp
            @if ($totalExpenses > 0)
                <div style="display:flex; flex-direction:column; gap: 12px;">
                    @foreach ($expenseRows as [$name, $value, $color])
                        @php $pct = $totalExpenses > 0 ? ($value / $totalExpenses) * 100 : 0; @endphp
                        <div>
                            <div style="display:flex; justify-content:space-between; margin-bottom: 5px; font-size: 12.5px;">
                                <span style="font-weight: 500;">{{ $name }}</span>
                                <span class="mono" style="color: var(--ink-3);">{{ number_format($pct, 0) }}% · RM {{ number_format($value, 0) }}</span>
                            </div>
                            <div style="height: 6px; background: var(--bg-sunk); border-radius: 999px; overflow: hidden;">
                                <div style="width: {{ $pct }}%; height: 100%; background: {{ $color }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div style="color: var(--ink-3); font-size: 13px;">{{ __('No expenses recorded in range — cleaning, laundry, maintenance costs and ledger entries will appear here.') }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
