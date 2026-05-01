@props(['name', 'size' => 32])
@php
    $initial = strtoupper(mb_substr($name ?? '?', 0, 1));
    $hue = (crc32($name ?? '') % 360);
@endphp
<div style="width:{{ $size }}px; height:{{ $size }}px; border-radius:999px;
    background: oklch(86% 0.04 {{ $hue }}); color: oklch(38% 0.06 {{ $hue }});
    display:inline-flex; align-items:center; justify-content:center;
    font-size:{{ round($size * 0.4) }}px; font-weight:600; flex-shrink:0;">
    {{ $initial }}
</div>
