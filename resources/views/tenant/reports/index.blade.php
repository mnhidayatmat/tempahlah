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

        {{-- Trend chart --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 18px; flex-wrap: wrap; gap: 8px;">
                <div>
                    <div class="kicker" style="margin-bottom: 4px;">{{ __('Monthly revenue') }}</div>
                    <div style="font-size: 13px; color: var(--ink-3);">{{ __('RM thousands · last 12 months') }}</div>
                </div>
                <div style="display:flex; gap:12px; font-size: 11.5px; color: var(--ink-3);">
                    <span><span style="display:inline-block; width:10px; height:10px; background: var(--primary); border-radius: 2px; margin-right: 5px; vertical-align: middle;"></span>{{ __('Revenue') }}</span>
                    <span><span style="display:inline-block; width:10px; height:2px; background: var(--accent); margin-right: 5px; vertical-align: middle;"></span>{{ __('Occupancy %') }}</span>
                </div>
            </div>
            <div style="display:grid; grid-template-columns: repeat({{ $monthly->count() }}, 1fr); gap: 8px; align-items: end; height: 180px;">
                @foreach ($monthly as $m)
                    @php
                        $h = ($m['revenue'] / $maxRevenue) * 100;
                        $occH = $m['occupancy'] * 100;
                    @endphp
                    <div style="display:flex; flex-direction:column; align-items:center; gap: 6px; height: 100%; justify-content:flex-end; position: relative;">
                        <div style="position: absolute; top: {{ 100 - $occH }}%; left: 0; right: 0; height: 2px; background: var(--accent); opacity: .7;"></div>
                        <div class="mono" style="font-size: 9.5px; color: var(--ink-3);">{{ number_format($m['revenue'] / 1000, 0) }}k</div>
                        <div style="width: 100%; max-width: 28px; height: {{ $h }}%; background: var(--primary); border-radius: 3px 3px 0 0; min-height: 4px;"></div>
                        <div style="font-size: 10px; color: var(--ink-3);">{{ $m['label'] }}</div>
                    </div>
                @endforeach
            </div>
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
