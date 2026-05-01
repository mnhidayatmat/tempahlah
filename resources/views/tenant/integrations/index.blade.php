<x-app-layout :title="__('Integrations')">
    @php
        $integrations = [
            ['key' => 'toyyibpay', 'name' => 'Toyyibpay', 'desc' => __('Accept FPX, cards, and e-wallets. Fees ~1%.'), 'pro' => true,  'connected' => false],
            ['key' => 'gcal',      'name' => 'Google Calendar', 'desc' => __('Two-way iCal/CalDAV sync. Prevents double-bookings.'), 'pro' => true,  'connected' => false],
            ['key' => 'ses',       'name' => 'Amazon SES', 'desc' => __('Send confirmations and reminders from your domain.'),  'pro' => false, 'connected' => true],
            ['key' => 'whatsapp',  'name' => 'WhatsApp Business', 'desc' => __('Auto-send deposit links and reminders.'), 'pro' => true,  'connected' => false],
            ['key' => 'billplz',   'name' => 'Billplz (v2)',   'desc' => __('Recurring subscription billing. Coming soon.'), 'pro' => true,  'connected' => false, 'soon' => true],
        ];
    @endphp
    <div style="display:flex; flex-direction:column; gap: 20px; max-width: 920px;">
        <div>
            <div class="kicker">{{ __('Configure') }}</div>
            <h2 class="display-2" style="margin: 4px 0 0;">{{ __('Integrations') }}</h2>
            <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">
                {{ __('Connect the services that power payments, scheduling, and messaging.') }}
            </p>
        </div>

        <div style="display:flex; flex-direction:column; gap: 10px;">
            @foreach ($integrations as $it)
                <div class="hauz-card" style="padding: 16px; display:flex; align-items:center; gap: 14px;">
                    <div style="width: 40px; height: 40px; border-radius: var(--r-md); background: var(--bg-tint); display:flex; align-items:center; justify-content:center; font-family: var(--font-display); font-size: 20px; color: var(--primary); flex-shrink:0;">
                        {{ strtoupper(substr($it['name'], 0, 1)) }}
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; gap: 8px; margin-bottom: 2px;">
                            <span style="font-weight: 600; font-size: 14px;">{{ $it['name'] }}</span>
                            @if (! empty($it['pro']))<x-pill variant="pro"><x-icon name="sparkle" :size="10"/> Pro</x-pill>@endif
                            @if (! empty($it['soon']))<x-pill>{{ __('Soon') }}</x-pill>@endif
                            @if ($it['connected'])<x-pill variant="ok" :dot="true">{{ __('Connected') }}</x-pill>@endif
                        </div>
                        <div style="font-size: 12.5px; color: var(--ink-3); line-height: 1.45;">{{ $it['desc'] }}</div>
                    </div>
                    <div>
                        @if (! empty($it['soon']))
                            <button class="btn btn-sm" disabled>{{ __('Coming soon') }}</button>
                        @elseif ($it['connected'])
                            <button class="btn btn-sm">{{ __('Manage') }}</button>
                        @else
                            <button class="btn btn-primary btn-sm">{{ __('Connect') }}</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
