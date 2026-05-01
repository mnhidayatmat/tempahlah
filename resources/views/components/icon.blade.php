@props(['name', 'size' => 16])
@php
    $view = 'icons.' . str_replace('_', '-', $name);
@endphp
@if (view()->exists($view))
    @include($view, ['size' => $size, 'attributes' => $attributes])
@else
    <span style="display:inline-block; width:{{ $size }}px; height:{{ $size }}px;"></span>
@endif
