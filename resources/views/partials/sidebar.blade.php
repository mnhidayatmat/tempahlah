@php
    $current = app()->getLocale();

    // Lightweight badge counts so the sidebar can show small action chips
    $pendingBookings = \App\Models\Booking::where('status', \App\Models\Booking::STATUS_PENDING)->count();
    $openCleaning = \App\Models\CleaningTask::whereIn('status', ['pending', 'in_progress'])->count();

    $groups = [
        ['title' => __('Operate'), 'items' => [
            ['key' => 'tenant.dashboard',       'label' => __('Dashboard'),   'icon' => 'home',     'route' => 'tenant.dashboard'],
            ['key' => 'tenant.calendar',        'label' => __('Calendar'),    'icon' => 'calendar', 'route' => 'tenant.calendar'],
            ['key' => 'tenant.bookings.*',      'label' => __('Bookings'),    'icon' => 'receipt',  'route' => 'tenant.bookings.index', 'badge' => $pendingBookings ?: null],
            ['key' => 'tenant.guests.*',        'label' => __('Guests'),      'icon' => 'users',    'route' => 'tenant.guests.index'],
            ['key' => 'tenant.housekeeping.*',  'label' => __('Housekeeping'),'icon' => 'sparkle',  'route' => 'tenant.housekeeping.index', 'badge' => $openCleaning ?: null],
        ]],
        ['title' => __('Manage'), 'items' => [
            ['key' => 'tenant.properties.*',    'label' => __('Properties'),  'icon' => 'building', 'route' => 'tenant.properties.index'],
            ['key' => 'tenant.payments.*',      'label' => __('Payments'),    'icon' => 'card',     'route' => 'tenant.payments.index'],
            ['key' => 'tenant.reports.*',       'label' => __('Reports'),     'icon' => 'chart',    'route' => 'tenant.reports.index'],
        ]],
        ['title' => __('Configure'), 'items' => [
            ['key' => 'tenant.integrations.*',  'label' => __('Integrations'),'icon' => 'link',     'route' => 'tenant.integrations.index'],
            ['key' => 'tenant.subscription',    'label' => __('Subscription'),'icon' => 'sparkle',  'route' => 'tenant.subscription'],
            ['key' => 'tenant.settings.*',      'label' => __('Settings'),    'icon' => 'cog',      'route' => 'tenant.settings.index'],
        ]],
    ];
@endphp

<aside class="shell-sidebar" :class="{ 'is-open': sidebarOpen }" @click.away="sidebarOpen = false" style="width:232px; flex-shrink:0; background: var(--bg); border-right: 1px solid var(--line); display:flex; flex-direction:column; height:100%;">
    {{-- Brand + tenant switcher --}}
    <div style="padding: 16px 14px 14px;">
        <button type="button" style="width:100%; display:flex; align-items:center; gap:10px; padding:8px; border:0; background:transparent; border-radius: var(--r-md); text-align:left; cursor:pointer; color: var(--ink);">
            <img src="{{ asset('icons/logo.svg') }}" alt="Tempahlah" width="30" height="30" style="display:block; flex-shrink:0;"/>
            <div style="flex:1; min-width:0;">
                <div style="font-weight:700; font-size:16px; letter-spacing:-.02em; color: var(--primary);">
                    {{ config('app.name', 'Tempahlah') }}
                </div>
                <div style="font-size:11px; color: var(--ink-3); margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    {{ $tenant?->business_name ?? auth()->user()->name }}
                </div>
            </div>
            <x-icon name="more" :size="14" style="color: var(--ink-3);"/>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="thin-scroll" style="flex:1; padding: 0 8px; overflow-y:auto;">
        @foreach ($groups as $g)
            <div style="margin-bottom:18px;">
                <div class="kicker" style="padding: 6px 12px 6px; letter-spacing:.2em;">{{ $g['title'] }}</div>
                @foreach ($g['items'] as $it)
                    @php
                        $active = request()->routeIs($it['key']);
                        $badge = $it['badge'] ?? null;
                    @endphp
                    <a href="{{ route($it['route']) }}"
                       style="display:flex; align-items:center; gap:12px;
                              padding: {{ $active ? '10px 12px 10px 8px' : '10px 14px' }};
                              border-left: 4px solid {{ $active ? 'var(--primary)' : 'transparent' }};
                              background: {{ $active ? 'var(--bg-tint)' : 'transparent' }};
                              color: {{ $active ? 'var(--primary)' : 'var(--ink-2)' }};
                              border-radius: {{ $active ? '0 var(--r-sm) var(--r-sm) 0' : 'var(--r-sm)' }};
                              font-size:13px; font-weight: {{ $active ? '700' : '500' }};
                              text-decoration:none; margin-bottom:2px; transition: background 160ms, color 160ms;">
                        <x-icon :name="$it['icon']" :size="16"/>
                        <span style="flex:1;">{{ $it['label'] }}</span>
                        @if ($badge)
                            <span class="pill pill-primary" style="height:18px; font-size:10px; padding:0 7px; font-weight:700;">{{ $badge }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Beta access card --}}
    <div style="padding: 12px;">
        <div style="padding: 12px 14px; border: 1px solid var(--primary-edge); background: var(--primary-tint); border-radius: var(--r-lg);">
            <div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                <x-icon name="sparkle" :size="12" style="color: var(--primary);"/>
                <span class="cm-eyebrow-primary">{{ __('Beta access') }}</span>
            </div>
            <div style="font-size:11.5px; color: var(--ink-2); line-height:1.45;">
                {{ __('All features free while we improve. Help us shape Tempahlah.') }}
            </div>
        </div>
    </div>
</aside>
