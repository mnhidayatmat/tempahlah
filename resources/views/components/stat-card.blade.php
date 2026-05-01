@props([
    'label',
    'value',
    'unit' => null,
    'delta' => null,        // e.g. "+12%" or "-3%"
    'sparkline' => null,    // array of numbers
    'color' => 'primary',   // primary | accent | ok | warn
])
@php
    $deltaPositive = $delta && !str_starts_with($delta, '-');
@endphp
<div class="hauz-card" style="padding: 16px;">
    <div class="kicker" style="margin-bottom: 10px;">{{ $label }}</div>
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:12px;">
        <div style="min-width:0;">
            <div style="display:flex; align-items:baseline; gap:4px;">
                @if ($unit)<span style="font-size:13px; color: var(--ink-3);" class="mono">{{ $unit }}</span>@endif
                <span style="font-family: var(--font-display); font-size:34px; line-height:1; letter-spacing:-.02em;">{{ $value }}</span>
            </div>
            @if ($delta)
                <div style="margin-top:6px; display:flex; align-items:center; gap:6px;">
                    <span class="pill pill-{{ $deltaPositive ? 'ok' : 'err' }}" style="height:18px; font-size:10.5px;">
                        {{ $delta }}
                    </span>
                    <span style="font-size:11px; color: var(--ink-3);">{{ __('vs last 30d') }}</span>
                </div>
            @endif
        </div>
        @if ($sparkline)
            <x-sparkline :points="$sparkline" :color="$color" :width="80" :height="32"/>
        @endif
    </div>
</div>
