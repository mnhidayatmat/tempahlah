<x-app-layout :title="__('Platform Admin')" :subtitle="__('Settings')" :breadcrumbs="[__('Platform'), __('Settings')]">
    <div style="display:flex; flex-direction:column; gap: 22px; max-width: 720px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Platform admin') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Settings') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ __('Platform-wide payment keys & marketing tags. Secrets are encrypted at rest.') }}
                </div>
            </div>
            <a href="{{ route('platform.overview') }}" class="btn btn-sm">
                <x-icon name="arrow-left" :size="12"/> {{ __('Back') }}
            </a>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--ok-tint); color: var(--ok); border-color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--err-tint); color: var(--err); border-color: var(--err); font-size: 13px;">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <ul style="margin: 0; padding-left: 18px;">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Stripe --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px; margin-bottom: 4px;">
                <div class="kicker">{{ __('Stripe — recurring subscriptions') }}</div>
                <span class="pill {{ $stripeEnabled ? 'pill-ok' : '' }}" style="height: 20px; font-size: 11px;">
                    <span class="pill-dot"></span>{{ $stripeEnabled ? __('Active') : __('Not configured') }}
                </span>
            </div>
            <div style="font-size: 12.5px; color: var(--ink-3); margin-bottom: 18px;">
                {{ __('Charges tenants RM 49/mo into your own Stripe account. Keys override anything set in .env.') }}
            </div>

            <form method="POST" action="{{ route('platform.settings.update') }}" style="display:flex; flex-direction:column; gap: 16px;">
                @csrf

                {{-- Secret key --}}
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 6px;">{{ __('Secret key') }}</label>
                    <input class="input" type="password" name="stripe_secret_key" autocomplete="off"
                           placeholder="{{ $stripe['secret_key'] ? __('Set (:hint) — leave blank to keep', ['hint' => $stripe['secret_key']]) : 'sk_live_… / sk_test_…' }}">
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('From Stripe → Developers → API keys. Stored encrypted.') }}</div>
                </div>

                {{-- Publishable key --}}
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 6px;">{{ __('Publishable key') }}</label>
                    <input class="input" type="text" name="stripe_publishable_key" value="{{ old('stripe_publishable_key', $stripe['publishable_key']) }}"
                           placeholder="pk_live_… / pk_test_…">
                </div>

                {{-- Webhook secret --}}
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 6px;">{{ __('Webhook signing secret') }}</label>
                    <input class="input" type="password" name="stripe_webhook_secret" autocomplete="off"
                           placeholder="{{ $stripe['webhook_secret'] ? __('Set (:hint) — leave blank to keep', ['hint' => $stripe['webhook_secret']]) : 'whsec_…' }}">
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">
                        {{ __('From the webhook endpoint you add at:') }}
                        <code style="font-family: var(--font-mono); font-size: 11px;">{{ $webhookUrl }}</code>
                    </div>
                </div>

                {{-- Price ids --}}
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 6px;">{{ __('Pro recurring price ID') }}</label>
                    <input class="input" type="text" name="stripe_price_id" value="{{ old('stripe_price_id', $stripe['price_id']) }}"
                           placeholder="price_…">
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('The monthly RM 49 recurring Pro price from your Stripe product.') }}</div>
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 6px;">{{ __('Ultra recurring price ID') }}</label>
                    <input class="input" type="text" name="stripe_price_id_ultra" value="{{ old('stripe_price_id_ultra', $stripe['price_id_ultra']) }}"
                           placeholder="price_…">
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('The monthly RM 89 recurring Ultra price. Leave blank to keep Ultra checkout unavailable.') }}</div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap: 8px; padding-top: 6px;">
                    <button type="submit" class="btn btn-primary">{{ __('Save Stripe settings') }}</button>
                </div>
            </form>

            {{-- Test connection (separate form so it doesn't submit the fields) --}}
            <form method="POST" action="{{ route('platform.settings.test-stripe') }}" style="border-top: .5px solid var(--line); margin-top: 18px; padding-top: 16px;">
                @csrf
                <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
                    <div style="font-size: 12px; color: var(--ink-3);">{{ __('Verify the saved key authenticates + the price is recurring.') }}</div>
                    <button type="submit" class="btn btn-sm">{{ __('Test connection') }}</button>
                </div>
            </form>
        </div>

        {{-- Meta (Facebook) Pixel --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px; margin-bottom: 4px;">
                <div class="kicker">{{ __('Meta (Facebook) Pixel — ad tracking') }}</div>
                <span class="pill {{ $facebookPixelActive ? 'pill-ok' : '' }}" style="height: 20px; font-size: 11px;">
                    <span class="pill-dot"></span>{{ $facebookPixelActive ? __('Active') : __('Not configured') }}
                </span>
            </div>
            <div style="font-size: 12.5px; color: var(--ink-3); margin-bottom: 18px;">
                {{ __('Tracks PageView on the landing + signup pages, and a CompleteRegistration conversion when a host signs up. Leave blank to turn tracking off.') }}
            </div>

            <form method="POST" action="{{ route('platform.settings.marketing') }}" style="display:flex; flex-direction:column; gap: 16px;">
                @csrf
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 6px;">{{ __('Pixel ID') }}</label>
                    <input class="input" type="text" name="facebook_pixel_id" inputmode="numeric" autocomplete="off"
                           value="{{ old('facebook_pixel_id', $facebookPixelId) }}" placeholder="e.g. 1234567890123456">
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">
                        {{ __('From Meta Events Manager → your Pixel (the 15–16 digit ID). Public value — safe to expose. Overrides FACEBOOK_PIXEL_ID in .env.') }}
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap: 8px; padding-top: 6px;">
                    <button type="submit" class="btn btn-primary">{{ __('Save marketing settings') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
