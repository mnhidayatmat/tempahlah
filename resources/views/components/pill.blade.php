@props(['variant' => 'default', 'dot' => false])
@php
    $class = 'pill';
    if ($variant !== 'default') $class .= ' pill-' . $variant;
@endphp
<span {{ $attributes->merge(['class' => $class]) }}>
    @if ($dot)<span class="pill-dot"></span>@endif
    {{ $slot }}
</span>
