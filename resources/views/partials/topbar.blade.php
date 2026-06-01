@php
    $current = app()->getLocale();
    $crumbs = $breadcrumbs ?? null;
    $sub = $subtitle ?? null;
@endphp
<div class="shell-topbar" style="height:64px; padding: 0 28px; display:flex; align-items:center; gap:16px;
            border-bottom: 1px solid var(--line);
            background: color-mix(in oklab, var(--bg) 80%, transparent);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            flex-shrink:0; z-index:10;">
    {{-- Hamburger (mobile only) --}}
    <button type="button"
            class="shell-mobile-only"
            @click.stop="sidebarOpen = true"
            aria-label="{{ __('Open menu') }}"
            style="width:36px; height:36px; padding:0; border:1px solid var(--line-2); background: var(--bg-elev); border-radius: var(--r-md); cursor:pointer; align-items:center; justify-content:center; color: var(--ink);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>

    <div style="flex:1; min-width:0;">
        @if ($crumbs)
            <div class="shell-topbar-breadcrumbs" style="display:flex; align-items:center; gap:6px; font-size:11px; color: var(--ink-3); margin-bottom:2px; text-transform:uppercase; letter-spacing:.08em; font-weight:700;">
                @foreach ((array) $crumbs as $i => $c)
                    @if ($i > 0)<span style="opacity:.4;">/</span>@endif
                    <span>{{ $c }}</span>
                @endforeach
            </div>
        @endif
        <div style="display:flex; align-items:baseline; gap:12px;">
            <h1 class="shell-topbar-title" style="margin:0; font-size:20px; font-weight:700; letter-spacing:-.02em; color: var(--ink);">
                {{ $title ?? __('Dashboard') }}
            </h1>
            @if ($sub)
                <span style="font-size:12.5px; color: var(--ink-3);">{{ $sub }}</span>
            @endif
        </div>
    </div>

    {{-- Search --}}
    <div class="shell-topbar-search" style="position:relative;">
        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color: var(--ink-3); pointer-events:none; display:inline-flex;">
            <x-icon name="search" :size="14"/>
        </span>
        <input type="search" placeholder="{{ __('Search bookings, guests…') }}"
               class="input"
               style="height:34px; padding-left:32px; padding-right:44px; width:260px; font-size:12.5px; border-radius: var(--r-pill);"/>
        <kbd style="position:absolute; right:10px; top:50%; transform:translateY(-50%);
                    font-family: var(--font-mono); font-size:10px; color: var(--ink-3);
                    padding:1px 6px; border:1px solid var(--line-2); border-radius:4px;
                    background: var(--bg-sunk);">⌘K</kbd>
    </div>

    {{-- BM/EN toggle --}}
    <div class="shell-topbar-locale" style="display:inline-flex; border: 1px solid var(--line-2); border-radius: var(--r-pill); padding:2px; background: var(--bg-sunk);">
        <a href="{{ route('locale.switch', 'ms') }}"
           style="padding:4px 10px; border-radius: var(--r-pill); font-size:11px; font-weight:600; text-decoration:none;
                  {{ $current === 'ms' ? 'background: var(--bg-elev); color: var(--primary); box-shadow: var(--sh-1);' : 'color: var(--ink-3);' }}">BM</a>
        <a href="{{ route('locale.switch', 'en') }}"
           style="padding:4px 10px; border-radius: var(--r-pill); font-size:11px; font-weight:600; text-decoration:none;
                  {{ $current === 'en' ? 'background: var(--bg-elev); color: var(--primary); box-shadow: var(--sh-1);' : 'color: var(--ink-3);' }}">EN</a>
    </div>

    {{-- Notifications bell --}}
    <button type="button" class="btn btn-sm btn-ghost"
            style="width:34px; height:34px; padding:0; position:relative; border-radius: var(--r-pill);">
        <x-icon name="bell" :size="15"/>
        <span style="position:absolute; top:7px; right:7px; width:7px; height:7px;
                     border-radius:999px; background: var(--err);
                     box-shadow: 0 0 0 2px var(--bg);"></span>
    </button>

    {{-- User avatar + logout --}}
    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
        @csrf
        <button type="submit"
                title="{{ __('Logout') }}"
                style="display:inline-flex; align-items:center; gap:8px; padding:0; border:0; background:transparent; cursor:pointer;">
            <x-avatar :name="auth()->user()->name" :size="32"/>
        </button>
    </form>
</div>
