@props([
    'points' => [],
    'width' => 80,
    'height' => 32,
    'color' => 'primary',
])
@php
    $stroke = match($color) {
        'accent' => 'var(--accent)',
        'ok' => 'var(--ok)',
        'warn' => 'var(--warn)',
        default => 'var(--primary)',
    };
    $pts = collect($points)->values();
    if ($pts->isEmpty()) { $pts = collect([0,0]); }
    $min = $pts->min();
    $max = $pts->max();
    $range = max(1, $max - $min);
    $stepX = $width / max(1, $pts->count() - 1);
    $coords = $pts->map(function ($v, $i) use ($stepX, $min, $range, $height) {
        $x = round($i * $stepX, 2);
        $y = round($height - (($v - $min) / $range) * ($height - 4) - 2, 2);
        return "$x,$y";
    })->implode(' ');
@endphp
<svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" fill="none" style="flex-shrink:0;">
    <polyline points="{{ $coords }}" stroke="{{ $stroke }}" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
