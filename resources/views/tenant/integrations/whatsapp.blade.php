<x-app-layout :title="$meta['name']">
    <div style="max-width: 760px; margin: 0 auto; display:flex; flex-direction:column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.integrations.index') }}"
               style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">
                ← {{ __('Integrations') }}
            </a>
            <div style="margin-top: 6px;">
                <div class="kicker">{{ __('Provider') }}</div>
                <h2 class="display-2" style="margin: 4px 0 0;">{{ $meta['name'] }}</h2>
                <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">{{ $meta['description'] }}</p>
            </div>
        </div>

        @livewire('tenant.whatsapp-connect')

        <div class="hauz-card" style="padding: 16px 20px; background: var(--bg-sunk); font-size: 12px; color: var(--ink-3); line-height: 1.6;">
            <strong style="color: var(--ink-2);">{{ __('How this works') }}</strong>
            <ul style="margin: 8px 0 0; padding-left: 18px;">
                <li>{{ __('The system stores your session as long as the phone stays online. You only re-scan if you tap Disconnect or WhatsApp logs you out.') }}</li>
                <li>{{ __('Outbound messages are only sent to phone numbers that are already in your bookings.') }}</li>
                <li>{{ __('Guests can reply STOP or BERHENTI to opt out. We honour it automatically.') }}</li>
                <li>{{ __('Use a dedicated business number. Mass-sending from a personal WhatsApp risks being flagged.') }}</li>
            </ul>
        </div>
    </div>
</x-app-layout>
