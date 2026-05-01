<div style="height:60px; padding: 0 28px; display:flex; align-items:center; gap:16px; border-bottom:.5px solid var(--line); background: var(--bg); flex-shrink:0;">
    <div style="flex:1; min-width:0;">
        <h1 style="margin:0; font-size:18px; font-weight:600; letter-spacing:-.01em;">
            {{ $title ?? __('Dashboard') }}
        </h1>
    </div>
    <div style="display:flex; align-items:center; gap: 8px;">
        @auth
            <span style="font-size: 12.5px; color: var(--ink-3);">{{ auth()->user()->name }}</span>
        @endauth
    </div>
</div>
