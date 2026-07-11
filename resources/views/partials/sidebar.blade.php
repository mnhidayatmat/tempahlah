@php
    $current = app()->getLocale();

    // Platform-admin toggle: a flagged user can flip between their own
    // workspace and the cross-tenant Platform Admin area, same login.
    $isPlatformAdmin = (bool) (auth()->user()?->is_platform_admin);
    $inPlatform = request()->routeIs('platform.*');

    // Lightweight badge counts so the sidebar can show small action chips
    $pendingBookings = \App\Models\Booking::where('status', \App\Models\Booking::STATUS_PENDING)->count();
    $openCleaning = \App\Models\CleaningTask::whereIn('status', ['pending', 'in_progress'])->count();

    // Platform Admin nav (shown only while in platform mode).
    $platformGroups = [
        ['title' => __('Platform'), 'items' => [
            ['key' => 'platform.overview', 'label' => __('Subscribers'), 'icon' => 'chart', 'route' => 'platform.overview'],
            ['key' => 'platform.testimonials', 'label' => __('Testimonials'), 'icon' => 'star', 'route' => 'platform.testimonials'],
            ['key' => 'platform.settings', 'label' => __('Settings'), 'icon' => 'cog', 'route' => 'platform.settings'],
        ]],
    ];

    $groups = [
        ['title' => __('Operate'), 'items' => [
            ['key' => 'tenant.dashboard',       'label' => __('Dashboard'),   'icon' => 'home',     'route' => 'tenant.dashboard',           'tour' => 'dashboard'],
            ['key' => 'tenant.calendar',        'label' => __('Calendar'),    'icon' => 'calendar', 'route' => 'tenant.calendar',            'tour' => 'calendar'],
            ['key' => 'tenant.bookings.*',      'label' => __('Bookings'),    'icon' => 'receipt',  'route' => 'tenant.bookings.index',      'badge' => $pendingBookings ?: null, 'tour' => 'bookings'],
            ['key' => 'tenant.guests.*',        'label' => __('Guests'),      'icon' => 'users',    'route' => 'tenant.guests.index',        'tour' => 'guests'],
            ['key' => 'tenant.testimonials.*',  'label' => __('Testimonials'),'icon' => 'star',     'route' => 'tenant.testimonials.index'],
            ['key' => 'tenant.housekeeping.*',  'label' => __('Housekeeping'),'icon' => 'sparkle',  'route' => 'tenant.housekeeping.index',  'badge' => $openCleaning ?: null, 'tour' => 'housekeeping'],
            ['key' => 'tenant.directory.*',     'label' => __('Directory'),   'icon' => 'users',    'route' => 'tenant.directory.index'],
        ]],
        ['title' => __('Manage'), 'items' => [
            ['key' => 'tenant.properties.*',    'label' => __('Properties'),  'icon' => 'building', 'route' => 'tenant.properties.index',    'tour' => 'properties'],
            ['key' => 'tenant.payments.*',      'label' => __('Payments'),    'icon' => 'card',     'route' => 'tenant.payments.index',      'tour' => 'payments'],
            ['key' => 'tenant.expenses.*',      'label' => __('Expenses'),    'icon' => 'wallet',   'route' => 'tenant.expenses.index'],
            ['key' => 'tenant.reports.*',       'label' => __('Reports'),     'icon' => 'chart',    'route' => 'tenant.reports.index',       'tour' => 'reports'],
        ]],
        ['title' => __('Configure'), 'items' => [
            ['key' => 'tenant.integrations.*',  'label' => __('Integrations'),'icon' => 'link',     'route' => 'tenant.integrations.index',  'tour' => 'integrations'],
            ['key' => 'tenant.subscription',    'label' => __('Subscription'),'icon' => 'sparkle',  'route' => 'tenant.subscription',        'tour' => 'subscription'],
            ['key' => 'tenant.settings.*',      'label' => __('Settings'),    'icon' => 'cog',      'route' => 'tenant.settings.index',      'tour' => 'settings'],
        ]],
    ];
@endphp

<aside class="shell-sidebar" :class="{ 'is-open': sidebarOpen }" @click.away="if (!window.__tempahlahTourActive) sidebarOpen = false" style="width:232px; flex-shrink:0; background: var(--bg); border-right: 1px solid var(--line); display:flex; flex-direction:column; height:100%;">
    {{-- Brand + tenant switcher --}}
    <div style="padding: 14px 12px 10px;">
        <button type="button"
                aria-label="{{ __('Workspace switcher') }}"
                style="width:100%; display:flex; align-items:center; gap:10px; padding:7px 8px; border:1px solid transparent; background:transparent; border-radius: var(--r-md); text-align:left; cursor:pointer; color: var(--ink); transition: background 120ms ease, border-color 120ms ease;"
                onmouseover="this.style.background='var(--bg-elev)'; this.style.borderColor='var(--line)';"
                onmouseout="this.style.background='transparent'; this.style.borderColor='transparent';">
            <img src="{{ asset('icons/logo.svg') }}" alt="Tempahlah" width="40" height="34" style="display:block; flex-shrink:0; filter: var(--logo-filter, none);"/>
            <div style="flex:1; min-width:0; line-height:1.25;">
                <div style="font-weight:700; font-size:13.5px; letter-spacing:-0.005em; color: var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    {{ $tenant?->business_name ?? auth()->user()->name }}
                </div>
                <div style="margin-top:2px; font-size:9.5px; font-weight:600; letter-spacing:0.13em; text-transform:uppercase; color: var(--ink-3); white-space:nowrap;">
                    {{ __('Tempahlah workspace') }}
                </div>
            </div>
            <span style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; flex-shrink:0; color: var(--ink-3); transition: background 120ms ease;"
                  onmouseover="this.style.background='var(--bg-sunk)';"
                  onmouseout="this.style.background='transparent';">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 9l4-4 4 4"/>
                    <path d="M16 15l-4 4-4-4"/>
                </svg>
            </span>
        </button>
    </div>

    {{-- Platform-admin toggle: My Homestay ⇄ Platform (only for flagged users) --}}
    @if ($isPlatformAdmin)
        <div style="padding: 0 12px 12px;">
            <div style="display:flex; gap:3px; padding:3px; background: var(--bg-sunk); border:1px solid var(--line); border-radius: var(--r-md);">
                <a href="{{ route('tenant.dashboard') }}"
                   style="flex:1; text-align:center; padding:7px 6px; border-radius: var(--r-sm); text-decoration:none; font-size:11.5px; font-weight:600;
                          background: {{ $inPlatform ? 'transparent' : 'var(--bg-elev)' }};
                          color: {{ $inPlatform ? 'var(--ink-3)' : 'var(--primary)' }};
                          box-shadow: {{ $inPlatform ? 'none' : 'var(--sh-1)' }};">
                    {{ __('My Homestay') }}
                </a>
                <a href="{{ route('platform.overview') }}"
                   style="flex:1; text-align:center; padding:7px 6px; border-radius: var(--r-sm); text-decoration:none; font-size:11.5px; font-weight:600;
                          background: {{ $inPlatform ? 'var(--bg-elev)' : 'transparent' }};
                          color: {{ $inPlatform ? 'var(--primary)' : 'var(--ink-3)' }};
                          box-shadow: {{ $inPlatform ? 'var(--sh-1)' : 'none' }};">
                    {{ __('Platform') }}
                </a>
            </div>
        </div>
    @endif

    {{-- Nav --}}
    <nav class="thin-scroll" style="flex:1; padding: 0 8px; overflow-y:auto;">
        @foreach (($inPlatform ? $platformGroups : $groups) as $g)
            <div style="margin-bottom:18px;">
                <div class="kicker" style="padding: 6px 12px 6px; letter-spacing:.2em;">{{ $g['title'] }}</div>
                @foreach ($g['items'] as $it)
                    @php
                        $active = request()->routeIs($it['key']);
                        $badge = $it['badge'] ?? null;
                    @endphp
                    <a href="{{ route($it['route']) }}"
                       @isset($it['tour']) data-tour="{{ $it['tour'] }}" @endisset
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
