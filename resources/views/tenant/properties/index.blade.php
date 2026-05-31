<x-app-layout :title="__('Properties')">
    <style>
        /* Property card — subtle lift + cover zoom on hover, status pulse */
        .prop-card { transition: transform .18s ease, box-shadow .18s ease; }
        .prop-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px -14px rgba(0,0,0,.18); }
        .prop-card .prop-hero { transition: transform .4s ease; }
        .prop-card:hover .prop-hero { transform: scale(1.025); }
        @keyframes prop-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(106,139,63,.55); }
            50%      { box-shadow: 0 0 0 4px rgba(106,139,63,0); }
        }
    </style>
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
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 18px;">
                @foreach ($properties as $p)
                    @php
                        $isActive = $p->status === 'active';
                        $rooms = $p->relationLoaded('rooms') ? $p->rooms : $p->rooms()->get();
                        $startingRate = (float) ($rooms->min('base_price') ?? 0);
                        $isWholeHouse = $p->isWholeHousePricing();
                        // For whole-house properties, "rooms" count is misleading —
                        // there's always exactly 1 synthetic "Whole house" Room row
                        // representing the entire property. Show the bedroom count
                        // (carried on the single room's `beds` column) instead.
                        $bedroomCount = $isWholeHouse
                            ? (int) ($rooms->first()?->beds ?? 0)
                            : $rooms->count();
                        $unitLabel = $isWholeHouse
                            ? trans_choice('{0} :n bedroom|{1} :n bedroom|[2,*] :n bedrooms', $bedroomCount, ['n' => $bedroomCount])
                            : trans_choice('{1} :n room|[2,*] :n rooms', $rooms->count(), ['n' => $rooms->count()]);
                        // Cover photo: prefer the explicit hero, otherwise the first
                        // by sort order, otherwise null (fallback to gradient).
                        $cover = $p->relationLoaded('photos')
                            ? ($p->photos->firstWhere('is_hero', true) ?? $p->photos->first())
                            : null;
                        $coverUrl = $cover?->url();
                        $hue = crc32($p->id) % 360;
                    @endphp
                    <div class="hauz-card prop-card" style="padding: 0; overflow: hidden; display:flex; flex-direction:column;">
                        {{-- Clickable area (cover + main info) → show page --}}
                        <a href="{{ route('tenant.properties.show', $p->id) }}" style="text-decoration:none; color: inherit; display:block;">
                            <div class="prop-hero" style="
                                height: 168px; position: relative; overflow: hidden;
                                @if ($coverUrl)
                                    background: #1a1614 url('{{ $coverUrl }}') center/cover no-repeat;
                                @else
                                    background: linear-gradient(135deg,
                                        oklch(74% 0.10 {{ $hue }}),
                                        oklch(56% 0.12 {{ ($hue + 36) % 360 }}) 60%,
                                        oklch(68% 0.08 {{ ($hue + 80) % 360 }}));
                                @endif
                            ">
                                {{-- Bottom-up scrim so overlaid pills always read clean --}}
                                <div style="position:absolute; inset:0;
                                    background: linear-gradient(180deg,
                                        rgba(0,0,0,0) 25%,
                                        rgba(0,0,0,.55) 100%);
                                    pointer-events:none;"></div>

                                {{-- Status pill (top-left) --}}
                                <span class="pill" style="
                                    position:absolute; top:10px; left:10px;
                                    background: rgba(255,255,255,.96); color: var(--ink);
                                    height:22px; font-size:10.5px; font-weight:600;
                                    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
                                    box-shadow: 0 1px 3px rgba(0,0,0,.18);">
                                    <span class="pill-dot" style="background: {{ $isActive ? 'var(--ok)' : 'var(--ink-3)' }};
                                        {{ $isActive ? 'animation: prop-pulse 1.8s ease-in-out infinite;' : '' }}"></span>
                                    {{ $isActive ? __('Live') : __('Draft') }}
                                </span>

                                {{-- Pricing-mode chip (top-right) --}}
                                <span class="pill" style="
                                    position:absolute; top:10px; right:10px;
                                    background: rgba(0,0,0,.45); color:#fff;
                                    height:22px; font-size:10.5px; font-weight:600;
                                    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
                                    border-color: transparent;">
                                    {{ $isWholeHouse ? '🏠 ' . __('Whole house') : '🛏️ ' . __('Per room') }}
                                </span>

                                {{-- City pill (bottom-left, glassy white) --}}
                                @if ($p->city)
                                    <span class="pill" style="
                                        position:absolute; bottom:10px; left:10px;
                                        background: rgba(255,255,255,.92); color: var(--ink);
                                        height:22px; font-size:10.5px; font-weight:600;
                                        backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                                        📍 {{ $p->city }}
                                    </span>
                                @endif
                            </div>
                            <div style="padding: 14px 16px 12px;">
                                <div style="font-family: var(--font-display); font-size: 19px; line-height: 1.2; font-weight: 600; margin-bottom: 6px; letter-spacing: -0.01em;">
                                    {{ $p->name }}
                                </div>

                                {{-- Facts row: bedrooms + baths + toilets --}}
                                <div style="display:flex; align-items:center; gap:10px; font-size:12px; color: var(--ink-3); margin-bottom:12px; flex-wrap:wrap;">
                                    <span style="display:inline-flex; align-items:center; gap:4px;">
                                        <span style="font-family: var(--font-mono); font-weight:600; color: var(--ink-2);">{{ $isWholeHouse ? $bedroomCount : $rooms->count() }}</span>
                                        {{ $isWholeHouse ? __('bedroom', ['n' => $bedroomCount]) : __('room') }}{{ ($isWholeHouse ? $bedroomCount : $rooms->count()) === 1 ? '' : 's' }}
                                    </span>
                                    @if ((int)$p->bathrooms > 0)
                                        <span style="color: var(--line);">·</span>
                                        <span style="display:inline-flex; align-items:center; gap:4px;">
                                            <span style="font-family: var(--font-mono); font-weight:600; color: var(--ink-2);">{{ (int)$p->bathrooms }}</span>
                                            {{ trans_choice('{1} bath|[2,*] baths', (int)$p->bathrooms) }}
                                        </span>
                                    @endif
                                    @if ((int)$p->toilets > 0)
                                        <span style="color: var(--line);">·</span>
                                        <span style="display:inline-flex; align-items:center; gap:4px;">
                                            <span style="font-family: var(--font-mono); font-weight:600; color: var(--ink-2);">{{ (int)$p->toilets }}</span>
                                            {{ trans_choice('{1} toilet|[2,*] toilets', (int)$p->toilets) }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Price --}}
                                <div style="display:flex; align-items:baseline; gap:5px; padding-top:10px; border-top: 1px dashed var(--line);">
                                    @if ($startingRate > 0 && ! $isWholeHouse)
                                        <span class="mono" style="font-size:10.5px; color: var(--ink-3); text-transform: uppercase; letter-spacing: 0.04em;">{{ __('From') }}</span>
                                    @endif
                                    <span class="mono" style="font-size:11px; color: var(--ink-3); font-weight:600;">RM</span>
                                    <span style="font-family: var(--font-mono); font-weight:700; font-size:21px; color: var(--ink); letter-spacing:-0.02em;">{{ number_format($startingRate) }}</span>
                                    <span style="font-size:11.5px; color: var(--ink-3);">/ {{ __('night') }}</span>
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
