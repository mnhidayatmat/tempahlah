@props(['property', 'size' => 40])
@php
    $hue = (crc32($property->id ?? $property->name ?? '') % 360);
@endphp
<div style="width:{{ $size }}px; height:{{ $size }}px; border-radius: var(--r-md); flex-shrink:0;
    background: linear-gradient(135deg, oklch(72% 0.08 {{ $hue }}), oklch(58% 0.10 {{ ($hue + 30) % 360 }}));
    display:flex; align-items:center; justify-content:center; color: white;">
    <x-icon name="building" :size="round($size * 0.45)"/>
</div>
