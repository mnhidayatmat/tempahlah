@props([
    'feature' => null,        // optional Pennant flag name
    'title' => null,
    'reason' => null,         // text shown next to lock
    'cta' => null,            // CTA label
])
@php
    $unlocked = $feature ? \Laravel\Pennant\Feature::active($feature) : false;
@endphp
@if ($unlocked)
    {{ $slot }}
@else
    <div class="hauz-card" style="padding: 18px; position: relative; overflow: hidden;">
        <div style="display:flex; align-items:flex-start; gap:14px;">
            <div style="width:40px; height:40px; border-radius: var(--r-md); background: var(--pro-tint); color: var(--pro); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <x-icon name="lock" :size="18"/>
            </div>
            <div style="flex:1; min-width:0;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                    <span class="pill pill-pro" style="height:18px; font-size:10px;">
                        <x-icon name="sparkle" :size="10"/> Pro
                    </span>
                    <span style="font-weight:600; font-size:14px;">{{ $title ?? __('Pro feature') }}</span>
                </div>
                <p style="margin:0 0 12px; font-size:12.5px; color: var(--ink-3); line-height:1.5;">
                    {{ $reason ?? __('Upgrade to unlock this feature.') }}
                </p>
                <a href="{{ route('tenant.subscription') }}" class="btn btn-primary btn-sm">
                    {{ $cta ?? __('Upgrade — RM49/mo') }} →
                </a>
            </div>
        </div>
    </div>
@endif
