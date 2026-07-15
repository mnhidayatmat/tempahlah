@props(['property', 'size' => 40, 'index' => 0])
@php
    // On-brand placeholder — primary colour first, then the same order as the
    // dashboard income chart (blue → yellow → teal → green → info). Deterministic
    // by list position, never a random off-brand hue (no pink). Darkened via
    // color-mix so the white icon reads on every colour, including the yellow.
    $brandVars = ['--primary', '--accent', '--secondary', '--ok', '--info'];
    $c = $brandVars[((int) $index) % count($brandVars)];
@endphp
<div style="width:{{ $size }}px; height:{{ $size }}px; border-radius: var(--r-md); flex-shrink:0;
    background: linear-gradient(135deg,
        color-mix(in oklab, var({{ $c }}) 85%, #000),
        color-mix(in oklab, var({{ $c }}) 52%, #000));
    display:flex; align-items:center; justify-content:center; color:#fff;">
    <x-icon name="building" :size="round($size * 0.45)"/>
</div>
