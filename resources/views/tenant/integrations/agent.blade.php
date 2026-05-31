<x-app-layout :title="$meta['name']">
    <div style="max-width: 880px; margin: 0 auto; display:flex; flex-direction:column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.integrations.index') }}"
               style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">
                ← {{ __('Integrations') }}
            </a>
            <div style="margin-top: 6px; display:flex; align-items:center; gap: 10px; flex-wrap: wrap;">
                <div class="kicker">{{ __('Pro feature') }}</div>
                <x-pill variant="pro"><x-icon name="sparkle" :size="10"/> Pro</x-pill>
            </div>
            <h2 class="display-2" style="margin: 4px 0 0;">{{ $meta['name'] }}</h2>
            <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">{{ $meta['description'] }}</p>
        </div>

        @livewire('tenant.agent-connect')

        <div class="hauz-card" style="padding: 16px 20px; background: var(--bg-sunk); font-size: 12px; color: var(--ink-3); line-height: 1.6;">
            <strong style="color: var(--ink-2);">{{ __('How this works') }}</strong>
            <ul style="margin: 8px 0 0; padding-left: 18px;">
                <li>{{ __('The AI replies only after a guest messages your WhatsApp first — same 24-hour window WhatsApp itself uses.') }}</li>
                <li>{{ __('Answers come from your real property data — names, addresses, photos, prices, availability — never made up.') }}</li>
                <li>{{ __('If a guest asks for you, complains, or asks about refunds, the AI hands the conversation back to you automatically.') }}</li>
                <li>{{ __('STOP / BERHENTI / UNSUBSCRIBE replies are honoured — the AI never re-engages an opted-out guest.') }}</li>
                <li>{{ __('Every AI reply is logged on the WhatsApp Recent sends table for audit.') }}</li>
            </ul>
        </div>
    </div>
</x-app-layout>
