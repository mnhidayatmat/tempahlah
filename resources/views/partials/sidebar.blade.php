@php
    $current = app()->getLocale();
    $groups = [
        ['title' => __('Operate'), 'items' => [
            ['key' => 'tenant.dashboard',       'label' => __('Dashboard'),  'icon' => 'home',     'route' => 'tenant.dashboard'],
            ['key' => 'tenant.calendar',        'label' => __('Calendar'),   'icon' => 'calendar', 'route' => 'tenant.calendar'],
            ['key' => 'tenant.bookings.*',      'label' => __('Bookings'),   'icon' => 'receipt',  'route' => 'tenant.bookings.index'],
            ['key' => 'tenant.guests.*',        'label' => __('Guests'),     'icon' => 'users',    'route' => 'tenant.guests.index'],
        ]],
        ['title' => __('Manage'), 'items' => [
            ['key' => 'tenant.properties.*',    'label' => __('Properties'), 'icon' => 'building', 'route' => 'tenant.properties.index'],
            ['key' => 'tenant.payments.*',      'label' => __('Payments'),   'icon' => 'card',     'route' => 'tenant.payments.index'],
            ['key' => 'tenant.reports.*',       'label' => __('Reports'),    'icon' => 'chart',    'route' => 'tenant.reports.index'],
        ]],
        ['title' => __('Configure'), 'items' => [
            ['key' => 'tenant.integrations',    'label' => __('Integrations'), 'icon' => 'link',    'route' => 'tenant.integrations'],
            ['key' => 'tenant.subscription',    'label' => __('Subscription'), 'icon' => 'sparkle', 'route' => 'tenant.subscription'],
            ['key' => 'tenant.settings.*',      'label' => __('Settings'),     'icon' => 'cog',     'route' => 'tenant.settings.index'],
        ]],
    ];
@endphp

<aside style="width: 232px; flex-shrink: 0; background: var(--bg); border-right: .5px solid var(--line); display: flex; flex-direction: column; height: 100%;">
    <div style="padding: 16px 14px 12px;">
        <div style="display:flex; align-items:center; gap:10px; padding: 8px;">
            <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
                <rect x="0" y="0" width="32" height="32" rx="8" fill="var(--primary)"/>
                <path d="M7 17 L16 9 L25 17 V23 H7 Z" fill="oklch(96% 0.02 150)"/>
                <rect x="13" y="17" width="6" height="6" fill="var(--accent)"/>
            </svg>
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600; font-size:13.5px; letter-spacing:-.01em;">{{ config('app.name', 'Hauz') }}</div>
                <div style="font-size:11px; color: var(--ink-3); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    {{ $tenant?->business_name ?? auth()->user()->name }}
                </div>
            </div>
        </div>
    </div>

    <nav style="flex:1; padding: 0 8px; overflow-y:auto;">
        @foreach ($groups as $g)
            <div style="margin-bottom:14px;">
                <div class="kicker" style="padding: 6px 10px 4px; font-size: 9.5px;">{{ $g['title'] }}</div>
                @foreach ($g['items'] as $it)
                    @php
                        $active = request()->routeIs($it['key']);
                    @endphp
                    <a href="{{ route($it['route']) }}" style="
                        display:flex; align-items:center; gap:10px;
                        padding: 7px 10px; margin-bottom:1px;
                        border-radius: var(--r-md); font-size:13px; text-decoration:none;
                        {{ $active ? 'background: var(--primary-tint); color: var(--primary); font-weight:600;' : 'color: var(--ink-2); font-weight:500;' }}">
                        <x-icon :name="$it['icon']" :size="15"/>
                        <span>{{ $it['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Plan card --}}
    <div style="padding: 10px;">
        @if ($plan === 'free')
            <a href="{{ route('tenant.subscription') }}" style="display:block; text-decoration:none; padding: 12px;
                border:.5px solid var(--line-2);
                background: linear-gradient(135deg, oklch(96% 0.04 60), oklch(94% 0.05 80));
                border-radius: var(--r-lg);">
                <div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                    <x-icon name="sparkle" :size="12" style="color: var(--pro);"/>
                    <span class="kicker" style="color: var(--pro); font-size:9.5px;">{{ __("You're on Free") }}</span>
                </div>
                <div style="font-family: var(--font-display); font-size:19px; line-height:1.1; margin-bottom:4px;">
                    {{ __('Unlock Pro') }}
                </div>
                <div style="font-size:11.5px; color: var(--ink-3); line-height:1.4; margin-bottom:8px;">
                    {{ __('Multi-property, payment gateway, auto-reminders.') }}
                </div>
                <div style="display:flex; align-items:baseline; gap:4px;">
                    <span class="mono" style="font-size:11px; color: var(--ink-3);">RM</span>
                    <span style="font-weight:600; font-size:16px;">49</span>
                    <span style="font-size:11px; color: var(--ink-3);">/{{ __('mo') }}</span>
                    <span style="margin-left:auto; color: var(--pro);">→</span>
                </div>
            </a>
        @else
            <div style="padding: 10px 12px; border:.5px solid var(--line-2); background: var(--bg-elev); border-radius: var(--r-lg);">
                <span class="pill pill-pro" style="height:18px; font-size:10px;">
                    <x-icon name="sparkle" :size="10"/> Pro
                </span>
                <div style="font-size:11.5px; color: var(--ink-3); margin-top:6px;">
                    {{ __('Renews :date · RM49/mo', ['date' => now()->addMonth()->format('d M')]) }}
                </div>
            </div>
        @endif
    </div>

    {{-- Locale + user footer --}}
    <div style="padding: 8px 14px 14px; border-top:.5px solid var(--line); display:flex; align-items:center; gap:10px;">
        <div style="display:inline-flex; border:.5px solid var(--line-2); border-radius:6px; padding:2px; background: var(--bg-sunk);">
            <a href="{{ route('locale.switch', 'ms') }}" style="padding:3px 8px; border-radius:4px; font-size:10.5px; font-weight:500; text-decoration:none; {{ $current === 'ms' ? 'background: var(--bg-elev); color: var(--primary); box-shadow: var(--sh-1);' : 'color: var(--ink-3);' }}">BM</a>
            <a href="{{ route('locale.switch', 'en') }}" style="padding:3px 8px; border-radius:4px; font-size:10.5px; font-weight:500; text-decoration:none; {{ $current === 'en' ? 'background: var(--bg-elev); color: var(--primary); box-shadow: var(--sh-1);' : 'color: var(--ink-3);' }}">EN</a>
        </div>
        <form method="POST" action="{{ route('logout') }}" style="margin-left:auto;">
            @csrf
            <button type="submit" style="border:0; background:transparent; color: var(--ink-3); font-size:11.5px; cursor:pointer;">
                {{ __('Logout') }}
            </button>
        </form>
    </div>
</aside>
