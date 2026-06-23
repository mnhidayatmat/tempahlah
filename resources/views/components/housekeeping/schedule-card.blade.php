@props([
    'tab',
    'title',
    'subtitle' => null,
    'emoji' => '📋',
    'scheduleDate',
    'text',
    'refName' => 'sched',
])
@php
    // Render the WhatsApp-formatted source into a safe HTML preview:
    // escape first, then convert *bold* / _italic_; indentation + line breaks
    // are preserved by white-space:pre-wrap on the bubble (so no nl2br).
    $previewHtml = e($text);
    $previewHtml = preg_replace('/\*(.+?)\*/s', '<strong>$1</strong>', $previewHtml);
    $previewHtml = preg_replace('/_(.+?)_/s', '<em>$1</em>', $previewHtml);
    $sentLabel = now()->format('g:i A');
@endphp
<div class="hauz-card" style="padding: 0; overflow: hidden;">
    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:14px 18px; border-bottom:.5px solid var(--line);">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:11px; background:var(--primary-tint); display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0;">{{ $emoji }}</div>
            <div>
                <div style="font-size:14px; font-weight:600;">{{ $title }}</div>
                @if ($subtitle)
                    <div style="font-size:12px; color:var(--ink-3); margin-top:2px;">{{ $subtitle }}</div>
                @endif
            </div>
        </div>
        <form method="GET" action="{{ route('tenant.housekeeping.index') }}" style="display:flex; gap:6px; align-items:center;">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <x-icon name="calendar" :size="14" style="color:var(--ink-3);"/>
            <input type="date" name="schedule_date" value="{{ $scheduleDate->format('Y-m-d') }}" class="input"
                   style="width:auto; padding:6px 10px; font-size:12px;" onchange="this.form.submit()">
        </form>
    </div>

    <div x-data="{ copied: false }">
        {{-- WhatsApp-style chat preview --}}
        <div style="padding:18px; background:#efeae2; background-image:radial-gradient(var(--bg-sunk) 0.5px, transparent 0.5px); background-size:14px 14px;">
            <div style="max-width:430px; margin-left:auto;">
                <div style="position:relative; background:#d9fdd3; border-radius:12px; border-top-right-radius:4px; padding:8px 11px 7px; box-shadow:0 1px 1.5px rgba(0,0,0,.08);">
                    <div style="font-size:13px; line-height:1.5; color:#111b21; white-space:pre-wrap; word-break:break-word; max-height:360px; overflow-y:auto;">{!! $previewHtml !!}</div>
                    <div style="text-align:right; font-size:10px; color:#667781; margin-top:3px; user-select:none;">
                        {{ $sentLabel }}
                        <span style="color:#53bdeb; letter-spacing:-2px; margin-left:2px;">✓✓</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden source kept verbatim for clipboard / share --}}
        <textarea x-ref="{{ $refName }}" readonly aria-hidden="true" tabindex="-1"
                  style="position:absolute; width:1px; height:1px; padding:0; border:0; opacity:0; pointer-events:none; left:-9999px;">{{ $text }}</textarea>

        {{-- Actions --}}
        <div style="display:flex; gap:8px; padding:14px 18px; border-top:.5px solid var(--line); flex-wrap:wrap; align-items:center;">
            <button type="button" class="btn btn-sm"
                    @click="navigator.clipboard.writeText($refs.{{ $refName }}.value); copied = true; setTimeout(() => copied = false, 2000)"
                    style="display:inline-flex; align-items:center; gap:6px;">
                <span x-show="!copied" style="display:inline-flex;"><x-icon name="receipt" :size="14"/></span>
                <span x-show="copied" style="display:inline-flex; color:var(--ok);"><x-icon name="check" :size="14"/></span>
                <span x-text="copied ? @js(__('Copied!')) : @js(__('Copy text'))"></span>
            </button>
            <a href="https://wa.me/?text={{ rawurlencode($text) }}" target="_blank" rel="noopener"
               class="btn btn-sm"
               style="display:inline-flex; align-items:center; gap:6px; background:#25d366; border-color:#25d366; color:#fff;">
                <x-icon name="message" :size="14"/> {{ __('Share on WhatsApp') }}
            </a>
            <span style="font-size:11px; color:var(--ink-3); margin-left:auto;">{{ __('Opens WhatsApp — pick your group') }}</span>
        </div>
    </div>
</div>
