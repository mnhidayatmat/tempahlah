@props(['name', 'size' => 32])
@php
    $initial = strtoupper(mb_substr($name ?? '?', 0, 1));
@endphp
{{-- Single, consistent brand-tinted avatar for everyone — no per-name colour
     coding (was a crc32 hue per name); a clean, professional look app-wide. --}}
<div style="width:{{ $size }}px; height:{{ $size }}px; border-radius:999px;
    background: var(--primary-tint); color: var(--primary);
    display:inline-flex; align-items:center; justify-content:center;
    font-size:{{ round($size * 0.4) }}px; font-weight:600; flex-shrink:0;">
    {{ $initial }}
</div>
