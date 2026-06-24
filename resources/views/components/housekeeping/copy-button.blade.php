@props([
    'text',
    'label' => null,
])
{{-- Per-task "Copy text" button. Copies the emoji-rich text verbatim to the
     clipboard (clipboard preserves emoji, unlike a WhatsApp share link). --}}
<div x-data="{ copied: false }" style="display:inline-block;">
    <textarea x-ref="cp" readonly aria-hidden="true" tabindex="-1"
              style="position:absolute; left:-9999px; top:0; width:1px; height:1px; opacity:0; pointer-events:none;">{{ $text }}</textarea>
    <button type="button" class="btn btn-sm"
            @click="navigator.clipboard.writeText($refs.cp.value); copied = true; setTimeout(() => copied = false, 2000)"
            style="display:inline-flex; align-items:center; gap:6px;">
        <span x-show="!copied" style="display:inline-flex;"><x-icon name="receipt" :size="13"/></span>
        <span x-show="copied" style="display:inline-flex; color:var(--ok);"><x-icon name="check" :size="13"/></span>
        <span x-text="copied ? @js(__('Copied!')) : @js($label ?? __('Copy text'))"></span>
    </button>
</div>
