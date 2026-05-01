<x-app-layout :title="$property->name">
    <div style="display:flex; flex-direction:column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.properties.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Properties') }}</a>
            <div style="display:flex; align-items:flex-end; justify-content:space-between; margin-top: 6px;">
                <div>
                    <div class="kicker">{{ $property->city ?? '—' }}</div>
                    <h2 class="display-2" style="margin: 4px 0 0;">{{ $property->name }}</h2>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="#" class="btn btn-sm">{{ __('Edit') }}</a>
                    <a href="#" class="btn btn-sm">{{ __('Calendar') }}</a>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 18px;">
            <div class="hauz-card" style="padding: 0; overflow:hidden;">
                <div style="height: 280px; background: linear-gradient(135deg,
                    oklch(72% 0.08 {{ (crc32($property->id) % 360) }}),
                    oklch(58% 0.10 {{ ((crc32($property->id) + 30) % 360) }}));"></div>
                <div style="padding: 18px;">
                    <div class="kicker" style="margin-bottom: 6px;">{{ __('About') }}</div>
                    <p style="margin:0; font-size: 14px; line-height: 1.55; color: var(--ink-2);">
                        {{ $property->description ?? __('No description yet.') }}
                    </p>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap: 12px;">
                <div class="hauz-card" style="padding: 16px;">
                    <div class="kicker" style="margin-bottom: 8px;">{{ __('Pricing') }}</div>
                    <div style="display:flex; align-items:baseline; gap:4px;">
                        <span class="mono" style="font-size:13px; color: var(--ink-3);">RM</span>
                        <span style="font-family: var(--font-display); font-size:32px; line-height:1;">{{ number_format($property->base_price ?? 0) }}</span>
                        <span style="font-size:12.5px; color: var(--ink-3);">/{{ __('night') }}</span>
                    </div>
                </div>
                <div class="hauz-card" style="padding: 16px;">
                    <div class="kicker" style="margin-bottom: 8px;">{{ __('Details') }}</div>
                    <div style="display:flex; flex-direction:column; gap: 6px; font-size: 13px;">
                        <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Bedrooms') }}</span><span class="mono">{{ $property->bedrooms ?? '—' }}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Status') }}</span><x-pill variant="ok" :dot="true">{{ __('Live') }}</x-pill></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
