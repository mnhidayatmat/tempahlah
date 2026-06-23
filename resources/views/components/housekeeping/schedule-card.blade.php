@props([
    'tab',
    'title',
    'subtitle' => null,
    'scheduleDate',
    'text',
    'refName' => 'sched',
])
@php
    $rows = min(24, max(6, substr_count($text, "\n") + 1));
@endphp
<div class="hauz-card" style="padding: 16px;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
        <div>
            <div style="font-size: 14px; font-weight: 600;">{{ $title }}</div>
            @if ($subtitle)
                <div style="font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ $subtitle }}</div>
            @endif
        </div>
        <form method="GET" action="{{ route('tenant.housekeeping.index') }}" style="display:flex; gap: 6px; align-items:center;">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="kicker" style="font-size: 10.5px;">{{ __('Date') }}</label>
            <input type="date" name="schedule_date" value="{{ $scheduleDate->format('Y-m-d') }}" class="input"
                   style="width: auto; padding: 6px 8px; font-size: 12px;" onchange="this.form.submit()">
        </form>
    </div>

    <div x-data="{ copied: false }" style="margin-top: 12px;">
        <textarea x-ref="{{ $refName }}" readonly rows="{{ $rows }}" class="input mono"
                  style="width: 100%; font-size: 12px; line-height: 1.55; white-space: pre; overflow: auto; resize: vertical; background: var(--bg-sunk);">{{ $text }}</textarea>
        <div style="display:flex; gap: 8px; margin-top: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-sm btn-primary"
                    @click="navigator.clipboard.writeText($refs.{{ $refName }}.value); copied = true; setTimeout(() => copied = false, 2000)">
                <span x-text="copied ? @js(__('Copied!')) : @js(__('Copy text'))"></span>
            </button>
            <a class="btn btn-sm" href="https://wa.me/?text={{ rawurlencode($text) }}" target="_blank" rel="noopener">
                <x-icon name="message" :size="13" style="margin-right: 4px;"/> {{ __('Share on WhatsApp') }}
            </a>
        </div>
    </div>
</div>
