<x-app-layout :title="__('Integrations')">
    @php
        $catalog = [
            ['key' => 'toyyibpay',       'name' => 'Toyyibpay',          'desc' => __('Accept FPX, cards, and e-wallets. Fees ~1%.'),                'pro' => true],
            ['key' => 'google_calendar', 'name' => 'Google Calendar',    'desc' => __('Two-way iCal/CalDAV sync. Prevents double-bookings.'),         'pro' => true],
            ['key' => 'ses',             'name' => 'Amazon SES',         'desc' => __('Send confirmations and reminders from your domain.'),         'pro' => false],
            ['key' => 'whatsapp',        'name' => 'WhatsApp Business',  'desc' => __('Auto-send deposit links and reminders.'),                     'pro' => true],
            ['key' => 'agent',           'name' => 'AI Agent',           'desc' => __('Let an AI assistant reply to WhatsApp enquiries 24/7 — availability, photos, prices, location.'), 'pro' => true],
            ['key' => 'billplz',         'name' => 'Billplz (v2)',       'desc' => __('Recurring subscription billing.'),                            'pro' => true, 'soon' => true],
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

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--ok); background: var(--ok-tint); color: var(--ok); font-size: 13px;">
                {{ session('status') }}
            </div>
        @endif

        <div style="display:flex; flex-direction:column; gap: 10px;">
            @foreach ($catalog as $it)
                @php
                    $rec = $records[$it['key']] ?? null;
                    $connected = $rec && $rec->enabled;
                @endphp
                <div class="hauz-card" style="padding: 16px; display:flex; align-items:center; gap: 14px;">
                    <div style="width: 40px; height: 40px; border-radius: var(--r-md); background: var(--bg-tint); display:flex; align-items:center; justify-content:center; font-family: var(--font-display); font-size: 20px; color: var(--primary); flex-shrink:0;">
                        {{ strtoupper(substr($it['name'], 0, 1)) }}
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; gap: 8px; margin-bottom: 2px; flex-wrap: wrap;">
                            <span style="font-weight: 600; font-size: 14px;">{{ $it['name'] }}</span>
                            @if (! empty($it['pro']))<x-pill variant="pro"><x-icon name="sparkle" :size="10"/> Pro</x-pill>@endif
                            @if (! empty($it['soon']))<x-pill>{{ __('Soon') }}</x-pill>@endif
                            @if ($connected)
                                <x-pill variant="ok" :dot="true">{{ __('Connected') }}</x-pill>
                                @if ($rec->connected_at)
                                    <span style="font-size: 11px; color: var(--ink-3);">
                                        {{ __('since :when', ['when' => $rec->connected_at->diffForHumans()]) }}
                                    </span>
                                @endif
                            @endif
                        </div>
                        <div style="font-size: 12.5px; color: var(--ink-3); line-height: 1.45;">{{ $it['desc'] }}</div>
                    </div>
                    <div style="display:flex; gap: 6px;">
                        @if (! empty($it['soon']))
                            <button type="button" class="btn btn-sm" disabled>{{ __('Coming soon') }}</button>
                        @elseif ($connected)
                            <a href="{{ route('tenant.integrations.show', $it['key']) }}" class="btn btn-sm">{{ __('Manage') }}</a>
                        @else
                            <a href="{{ route('tenant.integrations.show', $it['key']) }}" class="btn btn-primary btn-sm">{{ __('Connect') }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
