<x-app-layout :title="__('Subscription')">
    <div style="max-width: 1080px; margin: 0 auto; display:flex; flex-direction:column; gap: 28px;">

        <div style="text-align:center; padding-top: 12px;">
            <div class="kicker">{{ __('Pricing') }}</div>
            <h2 class="display-1" style="margin: 8px 0 8px;">{{ __('Run your homestay, simply.') }}</h2>
            <p style="margin: 0 auto; max-width: 540px; color: var(--ink-2); font-size: 15px; line-height: 1.5;">
                {{ __('Start free. Upgrade when you need payments, multi-property, or auto-reminders.') }}
            </p>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: stretch;">

            {{-- Free --}}
            <div class="hauz-card" style="padding: 28px; display:flex; flex-direction:column;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom: 14px;">
                    <span class="pill">{{ __('Free') }}</span>
                    @if ($plan === 'free')
                        <x-pill variant="primary" :dot="true">{{ __('Current') }}</x-pill>
                    @endif
                </div>
                <div style="display:flex; align-items:baseline; gap:4px; margin-bottom: 6px;">
                    <span class="mono" style="font-size:14px; color: var(--ink-3);">RM</span>
                    <span style="font-family: var(--font-display); font-size: 56px; line-height: 1;">0</span>
                    <span style="font-size: 14px; color: var(--ink-3);">/{{ __('month') }}</span>
                </div>
                <p style="margin: 0 0 18px; color: var(--ink-3); font-size: 13.5px;">{{ __('To get going.') }}</p>
                <ul style="list-style: none; padding: 0; margin: 0 0 22px; display:flex; flex-direction:column; gap: 8px; font-size: 13.5px;">
                    @foreach ([__('1 property'), __('Manual booking entry'), __('Calendar view'), __('Bahasa + English'), __('Email support')] as $feat)
                        <li style="display:flex; gap:8px; align-items:center;"><x-icon name="check" :size="14" style="color: var(--ok);"/> {{ $feat }}</li>
                    @endforeach
                </ul>
                <div style="margin-top:auto;">
                    @if ($plan === 'free')
                        <button class="btn" style="width:100%;" disabled>{{ __('Your current plan') }}</button>
                    @else
                        <button class="btn" style="width:100%;">{{ __('Downgrade to Free') }}</button>
                    @endif
                </div>
            </div>

            {{-- Pro --}}
            <div class="hauz-card" style="padding: 28px; display:flex; flex-direction:column; position:relative; background: linear-gradient(170deg, var(--bg-elev), oklch(98% 0.02 60));">
                <div style="position:absolute; top: -10px; right: 18px;">
                    <x-pill variant="pro"><x-icon name="sparkle" :size="11"/> {{ __('Recommended') }}</x-pill>
                </div>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom: 14px;">
                    <span class="pill pill-pro"><x-icon name="sparkle" :size="11"/> Pro</span>
                    @if ($plan !== 'free')
                        <x-pill variant="primary" :dot="true">{{ __('Current') }}</x-pill>
                    @endif
                </div>
                <div style="display:flex; align-items:baseline; gap:4px; margin-bottom: 6px;">
                    <span class="mono" style="font-size:14px; color: var(--ink-3);">RM</span>
                    <span style="font-family: var(--font-display); font-size: 56px; line-height: 1;">49</span>
                    <span style="font-size: 14px; color: var(--ink-3);">/{{ __('month') }}</span>
                </div>
                <p style="margin: 0 0 18px; color: var(--ink-2); font-size: 13.5px;">{{ __('Everything in Free, plus:') }}</p>
                <ul style="list-style: none; padding: 0; margin: 0 0 22px; display:flex; flex-direction:column; gap: 8px; font-size: 13.5px;">
                    @foreach ([
                        __('Unlimited properties'),
                        __('Toyyibpay / FPX payment links'),
                        __('Auto WhatsApp + email reminders'),
                        __('Two-way Google Calendar sync'),
                        __('Marketplace listing'),
                        __('Booking insights + reports'),
                        __('Priority support'),
                    ] as $feat)
                        <li style="display:flex; gap:8px; align-items:center;"><x-icon name="check" :size="14" style="color: var(--ok);"/> {{ $feat }}</li>
                    @endforeach
                </ul>
                <div style="margin-top:auto;">
                    @if ($plan === 'free')
                        <a href="#" class="btn btn-primary" style="width:100%; justify-content:center;">{{ __('Upgrade — RM49/mo') }} →</a>
                    @else
                        <button class="btn" style="width:100%;" disabled>{{ __('Your current plan') }}</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="hauz-card" style="padding: 22px;">
            <div class="kicker" style="margin-bottom: 8px;">{{ __('Billing') }}</div>
            <p style="margin:0; font-size: 13px; color: var(--ink-2); line-height:1.5;">
                {{ __('v1 uses manual confirmation via Toyyibpay. v2 will introduce Billplz recurring billing — your subscription will migrate automatically.') }}
            </p>
        </div>
    </div>
</x-app-layout>
