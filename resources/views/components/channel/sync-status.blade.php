@props(['link' => null])

@if ($link && $link->active && $link->last_synced_at)
    @php
        $ok = $link->last_sync_status === 'ok';
        $count = $link->credentials_encrypted['last_event_count'] ?? null;
    @endphp
    <div style="margin-top: 5px; font-size: 11px; display:flex; align-items:center; gap: 6px; color: {{ $ok ? 'var(--ok)' : 'var(--err)' }};">
        <span class="pill-dot" style="background: {{ $ok ? 'var(--ok)' : 'var(--err)' }};"></span>
        @if ($ok)
            <span>{{ __('Synced :when', ['when' => $link->last_synced_at->diffForHumans()]) }}@if (! is_null($count)) · {{ trans_choice('{0} no reservations|{1} :count reservation|[2,*] :count reservations', (int) $count, ['count' => (int) $count]) }}@endif</span>
        @else
            <span>{{ $link->last_sync_error ?: __('Last sync failed.') }}</span>
        @endif
    </div>
@endif
