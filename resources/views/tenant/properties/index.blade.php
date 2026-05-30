<x-app-layout :title="__('Properties')">
    <div style="display:flex; flex-direction:column; gap: 20px;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between;">
            <div>
                <div class="kicker">{{ __('Listings') }}</div>
                <h2 class="display-2" style="margin: 4px 0 0;">{{ __('Properties') }}</h2>
                <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">
                    {{ trans_choice(':count property|:count properties', $properties->count()) }}
                </p>
            </div>
            <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary">
                <x-icon name="plus" :size="14"/> {{ __('Add property') }}
            </a>
        </div>

        @php
            $isPro = ($tenant?->subscription?->plan ?? 'free') !== 'free';
            $maxFree = 1;
        @endphp
        @if (! $isPro && $properties->count() >= $maxFree)
            <x-pro-lock
                :title="__('Multi-property is a Pro feature')"
                :reason="__('Free plan supports 1 property. Upgrade to list unlimited rooms or homestays.')"
                :cta="__('Upgrade — RM49/mo')"/>
        @endif

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--ok-tint); color: var(--ok); border-color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--err-tint); color: var(--err); border-color: var(--err); font-size: 13px;">{{ session('error') }}</div>
        @endif

        @if ($properties->isEmpty())
            <div class="hauz-card" style="padding: 48px 32px; text-align:center;">
                <div style="font-family: var(--font-display); font-size: 28px; margin-bottom: 8px;">{{ __('No properties yet') }}</div>
                <p style="margin: 0 auto 20px; color: var(--ink-3); font-size: 14px; max-width: 420px;">
                    {{ __('Add your first homestay or room. You can edit details, photos, and pricing anytime.') }}
                </p>
                <a href="{{ route('tenant.properties.create') }}" class="btn btn-primary">
                    <x-icon name="plus" :size="13"/> {{ __('Add property') }}
                </a>
            </div>
        @else
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                @foreach ($properties as $p)
                    @php
                        $isActive = $p->status === 'active';
                        $rooms = $p->relationLoaded('rooms') ? $p->rooms : $p->rooms()->get();
                        $startingRate = (float) ($rooms->min('base_price') ?? 0);
                    @endphp
                    <div class="hauz-card" style="padding: 0; overflow: hidden; display:flex; flex-direction:column;">
                        {{-- Clickable area (cover + main info) → show page --}}
                        <a href="{{ route('tenant.properties.show', $p->id) }}" style="text-decoration:none; color: inherit; display:block;">
                            <div style="height: 140px; position: relative;
                                background: linear-gradient(135deg,
                                    oklch(72% 0.08 {{ (crc32($p->id) % 360) }}),
                                    oklch(58% 0.10 {{ ((crc32($p->id) + 30) % 360) }}));
                                display:flex; align-items:flex-end; padding: 12px;">
                                <span class="pill" style="background: rgba(255,255,255,.92); color: var(--ink); height:20px; font-size:10.5px;">
                                    <span class="pill-dot" style="background: {{ $isActive ? 'var(--ok)' : 'var(--ink-3)' }};"></span>
                                    {{ $isActive ? __('Live') : __('Draft') }}
                                </span>
                            </div>
                            <div style="padding: 14px 14px 12px;">
                                <div style="font-weight:600; font-size:14.5px; margin-bottom:2px;">{{ $p->name }}</div>
                                <div style="font-size:12px; color: var(--ink-3); margin-bottom:10px;">
                                    {{ $p->city ?: '—' }} · {{ trans_choice('{1} :count room|[2,*] :count rooms', $rooms->count(), ['count' => $rooms->count()]) }}
                                </div>
                                <div style="display:flex; align-items:baseline; gap:4px;">
                                    <span class="mono" style="font-size:11px; color: var(--ink-3);">{{ $startingRate > 0 ? __('From') : '' }} RM</span>
                                    <span style="font-weight:600; font-size:18px;">{{ number_format($startingRate) }}</span>
                                    <span style="font-size:11.5px; color: var(--ink-3);">/{{ __('night') }}</span>
                                </div>
                            </div>
                        </a>

                        {{-- Actions row — NOT inside the link so the buttons don't trigger navigation --}}
                        <div style="border-top: 1px solid var(--line); padding: 8px 10px; display:flex; gap: 6px; align-items:center; justify-content:flex-end; background: var(--bg-elev);">
                            <a href="{{ route('tenant.properties.edit', ['property' => $p->public_id]) }}"
                               class="btn btn-sm btn-ghost"
                               style="display:inline-flex; align-items:center; gap:5px; font-size:11.5px; color: var(--ink-2);"
                               title="{{ __('Edit') }}">
                                <x-icon name="cog" :size="13"/> {{ __('Edit') }}
                            </a>
                            <form method="POST" action="{{ route('tenant.properties.destroy', ['property' => $p->public_id]) }}" style="margin:0;"
                                  onsubmit="return confirm('{{ addslashes(__('Delete \':name\'? This cannot be undone. Bookings already on this property must be cancelled first.', ['name' => $p->name])) }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-ghost"
                                        style="display:inline-flex; align-items:center; gap:5px; font-size:11.5px; color: var(--err); border-color: transparent;"
                                        title="{{ __('Delete') }}">
                                    <x-icon name="x" :size="13"/> {{ __('Delete') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
