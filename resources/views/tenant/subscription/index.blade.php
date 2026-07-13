<x-app-layout :title="__('Pricing')">
    @php
        // Monthly only — no yearly option exists on any plan.
        $proPrice = \App\Support\Billing\Plans::price(\App\Support\Billing\Plans::PRO);
        $ultraPrice = \App\Support\Billing\Plans::price(\App\Support\Billing\Plans::ULTRA);

        $freeFeatures = [
            ['ok' => true, 'text' => __('1 active homestay')],
            ['ok' => true, 'text' => __('Up to 4 rooms')],
            ['ok' => true, 'text' => __('Up to 20 bookings / month')],
            ['ok' => true, 'text' => __('Public booking page (tempahlah.com/your-name)')],
            ['ok' => true, 'text' => __('Manual payment (bank transfer / cash) with instructions')],
            ['ok' => true, 'text' => __('Marketplace listing (standard placement)')],
            ['ok' => true, 'text' => __('Click-to-WhatsApp guest links')],
            ['ok' => true, 'text' => __('Reviews & guest blacklist')],
            ['ok' => true, 'text' => __('Mobile app (iOS, Android, PWA)')],
            ['ok' => false, 'text' => __('Online payment gateway (FPX / cards)')],
            ['ok' => false, 'text' => __('AI WhatsApp assistant')],
            ['ok' => false, 'text' => __('Calendar sync (Google, Airbnb, Booking.com)')],
            ['ok' => false, 'text' => __('Invoice & receipt PDFs')],
            ['ok' => false, 'text' => __('Reports & analytics')],
        ];

        $proFeatures = [
            ['ok' => true, 'text' => __('Up to 3 homestays · unlimited rooms & bookings'), 'strong' => true],
            ['ok' => true, 'text' => __('Online payment gateway — SecurePay, Toyyibpay, Billplz'), 'strong' => true],
            ['ok' => true, 'text' => __('AI WhatsApp assistant answers & quotes guests 24/7'), 'strong' => true],
            ['ok' => true, 'text' => __('Your own subdomain (your-name.tempahlah.com)')],
            ['ok' => true, 'text' => __('Auto WhatsApp reminders before check-out')],
            ['ok' => true, 'text' => __('Branded invoice & receipt PDFs (auto-emailed + WhatsApp)')],
            ['ok' => true, 'text' => __('Custom brand colours & logo on booking page + invoices')],
            ['ok' => true, 'text' => __('Google Calendar 2-way sync')],
            ['ok' => true, 'text' => __('Airbnb & Booking.com 2-way calendar sync — no double-bookings'), 'strong' => true],
            ['ok' => true, 'text' => __('Dynamic pricing (weekend, season, holiday)')],
            ['ok' => true, 'text' => __('Reports & analytics + CSV / PDF export')],
            ['ok' => true, 'text' => __('Auto-schedule cleaning & laundry from bookings')],
            ['ok' => true, 'text' => __('Priority marketplace placement')],
            ['ok' => true, 'text' => __('Up to 3 staff accounts')],
            ['ok' => true, 'text' => __('Priority support (WhatsApp, BM/EN)')],
        ];

        $ultraFeatures = [
            ['ok' => true, 'text' => __('Unlimited homestays & staff accounts'), 'strong' => true],
            ['ok' => true, 'text' => __('White-label — no "Powered by Tempahlah" on your pages & invoices'), 'strong' => true],
            ['ok' => true, 'text' => __('Featured (top) marketplace placement'), 'strong' => true],
            ['ok' => true, 'text' => __('Advanced multi-property reports')],
            ['ok' => true, 'text' => __('Dedicated support')],
        ];

        $compareSections = [
            ['title' => __('Properties & rooms'), 'rows' => [
                [__('Active homestays'), '1', '3', __('Unlimited')],
                [__('Rooms per homestay'), '4', __('Unlimited'), __('Unlimited')],
                [__('Bookings per month'), '20', __('Unlimited'), __('Unlimited')],
                [__('Staff accounts'), '1', '3', __('Unlimited')],
                [__('Booking page'), 'tempahlah.com/name', 'name.tempahlah.com', 'name.tempahlah.com'],
            ]],
            ['title' => __('Marketplace'), 'rows' => [
                [__('Listing on tempahlah.com'), __('Standard'), __('Priority'), __('Featured (top)')],
                [__('Marketplace commission'), '0%', '0%', '0%'],
            ]],
            ['title' => __('Payments'), 'rows' => [
                [__('Manual payment (bank transfer / cash)'), true, true, true],
                [__('Online gateway (SecurePay, Toyyibpay, Billplz)'), false, true, true],
                [__('Invoice & receipt'), __('Simple email'), __('Branded PDF, auto-emailed'), __('Branded PDF, auto-emailed')],
                [__('Deposit + balance link automation'), false, true, true],
                [__('Platform transaction fee'), '0%', '0%', '0%'],
            ]],
            ['title' => __('Communications'), 'rows' => [
                [__('Booking confirmation email'), true, true, true],
                [__('Click-to-WhatsApp guest links'), true, true, true],
                [__('Auto check-out reminders'), false, true, true],
                [__('AI WhatsApp assistant (answers + quotes)'), false, true, true],
                [__('WhatsApp Business auto-send'), false, true, true],
            ]],
            ['title' => __('Calendar & pricing'), 'rows' => [
                [__('Google Calendar 2-way sync'), false, true, true],
                [__('Airbnb & Booking.com sync'), false, __('2-way'), __('2-way')],
                [__('Drag-to-block date ranges'), true, true, true],
                [__('Dynamic pricing rules'), false, true, true],
            ]],
            ['title' => __('Operations & branding'), 'rows' => [
                [__('Cleaning / laundry scheduling'), __('Manual'), __('Auto from bookings'), __('Auto from bookings')],
                [__('Maintenance & expense tracking'), true, true, true],
                [__('Custom brand colours & logo'), false, true, true],
                [__('White-label (no "Powered by Tempahlah")'), false, false, true],
            ]],
            ['title' => __('Analytics & support'), 'rows' => [
                [__('Reports & analytics'), false, true, __('Advanced (multi-property)')],
                [__('Export to CSV / PDF'), false, true, true],
                [__('Support'), __('Community'), __('Priority'), __('Dedicated')],
                [__('Free trial'), '—', __('7 days'), __('7 days')],
            ]],
        ];

        $faqs = [
            ['q' => __('Can I switch back to Free?'), 'a' => __('Yes, anytime. Your data stays. Properties beyond your 1 free slot become read-only, and paid features (online payments, invoices, AI assistant) simply switch off — no bookings are lost.')],
            ['q' => __('How do online payments work on Pro & Ultra?'), 'a' => __('Connect your own gateway — SecurePay, Toyyibpay or Billplz. Guests pay you directly (FPX, cards, e-wallets). The gateway\'s own fee applies; Tempahlah takes 0% commission on every booking.')],
            ['q' => __('What is the AI WhatsApp assistant?'), 'a' => __('When a guest messages your connected WhatsApp, the assistant replies instantly with real availability, prices, photos and location — grounded in your live data, in BM or EN — and hands off to you for anything sensitive.')],
            ['q' => __('What does Ultra add over Pro?'), 'a' => __('Unlimited homestays and staff, white-label public pages and invoices (no "Powered by Tempahlah"), featured top placement on the marketplace, advanced multi-property reports, and dedicated support.')],
        ];
    @endphp

    <style>
        /* minmax(0,1fr) lets the plan cards shrink (a bare 1fr refuses below its
           content min-width and pushes the page sideways on phones). 3-up on
           desktop, stacked below 900px. Same treatment for the FAQ pair. */
        .sub-3col { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .sub-2col { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        /* The 4-column comparison rows can't fit a phone — scroll them together as
           one block so the header and rows stay aligned. */
        .sub-compare { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .sub-compare > div { min-width: 640px; }
        @media (max-width: 900px) {
            .sub-3col { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .sub-2col { grid-template-columns: 1fr; }
        }
    </style>

    <div style="max-width: 1100px; margin: 0 auto; display:flex; flex-direction:column; gap: 24px;">

        {{-- Flash messages are rendered globally by layouts/app.blade.php. --}}

        {{-- Past due: paid features are still on, but only until grace runs out. --}}
        @if ($subscription?->inGrace())
            <div class="hauz-card" style="padding: 16px 18px; border-color: var(--warn); background: var(--warn-tint);">
                <div style="font-weight: 600; margin-bottom: 4px;">{{ __('Your subscription is unpaid') }}</div>
                <div style="font-size: 13px; color: var(--ink-2);">
                    {{ __('Your paid features stay on until :date. After that your account moves to the free plan — your data stays, but online payments, invoices and receipts switch off.', [
                        'date' => $subscription->grace_ends_at->format('d M Y'),
                    ]) }}
                </div>
                @if ($billingConfigured)
                    <form method="POST" action="{{ route('tenant.subscription.checkout') }}" style="margin-top: 12px;">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            {{ __('Pay now') }} — RM {{ number_format($openInvoice?->amount ?? \App\Support\Billing\Plans::price($subscription->planKey()), 2) }}
                        </button>
                    </form>
                @endif
            </div>
        @elseif ($openInvoice && $billingConfigured)
            <div class="hauz-card" style="padding: 16px 18px;">
                <div style="font-weight: 600; margin-bottom: 4px;">
                    {{ __('Invoice :num is waiting', ['num' => $openInvoice->number]) }}
                </div>
                <div style="font-size: 13px; color: var(--ink-2);">
                    {{ __('RM :amount for :start → :end.', [
                        'amount' => number_format($openInvoice->amount, 2),
                        'start' => $openInvoice->period_start->format('d M Y'),
                        'end' => $openInvoice->period_end->format('d M Y'),
                    ]) }}
                </div>
                <form method="POST" action="{{ route('tenant.subscription.checkout') }}" style="margin-top: 12px;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Pay now') }}</button>
                </form>
            </div>
        @endif

        {{-- Header --}}
        <div style="text-align:center; padding-top: 8px;">
            <div class="kicker" style="margin-bottom: 12px;">{{ __('Pricing · Made in Malaysia') }} 🇲🇾</div>
            <h1 style="margin: 0; font-family: var(--font-display); font-size: 48px; line-height: 1.05; font-weight: 600; letter-spacing: -.025em;">
                {!! __('Run your homestay <em>like a hotel</em>,', ['em-start' => '', 'em-end' => '']) !!}<br>
                {{ __("for less than a night's stay.") }}
            </h1>
            <div style="font-size: 15px; color: var(--ink-3); margin: 14px auto 0; max-width: 560px;">
                {{ __("Start free with one homestay. Upgrade when you're ready for payment gateways, the AI assistant, and more properties.") }}
            </div>
            <div style="font-size: 12.5px; color: var(--ink-3); margin-top: 12px;">
                {{ __('Monthly billing · 0% commission · no contracts') }}
            </div>
        </div>

        {{-- Plan cards --}}
        <div class="sub-3col" style="margin-top: 12px;">

            {{-- Free --}}
            <div class="hauz-card" style="padding: 24px; position: relative;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 18px;">
                    <div>
                        <div style="font-size: 14px; font-weight: 600; margin-bottom: 2px;">{{ __('Free') }}</div>
                        <div style="font-size: 12px; color: var(--ink-3);">{{ __('For solo owners testing the waters') }}</div>
                    </div>
                    @if ($planKey === 'free')
                        <span class="pill pill-primary"><span class="pill-dot"></span> {{ __('Current') }}</span>
                    @endif
                </div>
                <div style="display:flex; align-items:baseline; gap: 6px; margin-bottom: 24px;">
                    <span style="font-family: var(--font-display); font-size: 44px; line-height: 1; font-weight: 600;">RM 0</span>
                    <span style="font-size: 13px; color: var(--ink-3);">/{{ __('forever') }}</span>
                </div>
                @if ($planKey === 'free')
                    <button type="button" class="btn" style="width:100%; justify-content:center; margin-bottom: 22px; opacity: 0.5;" disabled>
                        {{ __("You're on Free") }}
                    </button>
                @else
                    <form method="POST" action="{{ route('tenant.subscription.change') }}" style="margin-bottom: 22px;">
                        @csrf
                        <input type="hidden" name="plan" value="free">
                        <button type="submit" class="btn" style="width:100%; justify-content:center;">
                            {{ __('Switch to Free') }}
                        </button>
                    </form>
                @endif
                <div class="kicker" style="margin-bottom: 10px;">{{ __('Includes') }}</div>
                <div style="display:flex; flex-direction:column; gap: 9px;">
                    @foreach ($freeFeatures as $it)
                        <div style="display:flex; gap: 10px; align-items:flex-start; font-size: 12.5px;">
                            <div style="width: 16px; height: 16px; border-radius: 999px; flex-shrink: 0;
                                        background: {{ $it['ok'] ? 'var(--primary-tint)' : 'var(--bg-sunk)' }};
                                        color: {{ $it['ok'] ? 'var(--primary)' : 'var(--ink-4)' }};
                                        display:flex; align-items:center; justify-content:center; margin-top: 2px;">
                                <x-icon :name="$it['ok'] ? 'check' : 'x'" :size="10"/>
                            </div>
                            <span style="color: {{ $it['ok'] ? 'var(--ink)' : 'var(--ink-4)' }};
                                         {{ $it['ok'] ? '' : 'text-decoration: line-through;' }}">
                                {{ $it['text'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Pro --}}
            <div class="hauz-card" style="padding: 24px;
                background: linear-gradient(155deg, var(--primary-soft), var(--primary-tint));
                border: .5px solid var(--primary-edge);
                box-shadow: 0 24px 64px -24px color-mix(in srgb, var(--primary) 30%, transparent);
                position: relative;">
                <div style="position: absolute; top: -10px; right: 24px;
                            background: var(--ink); color: var(--bg);
                            padding: 4px 10px; border-radius: 999px;
                            font-size: 10.5px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase;">
                    {{ __('Most popular') }}
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 18px;">
                    <div>
                        <div style="display:flex; align-items:center; gap: 6px; margin-bottom: 2px;">
                            <span style="font-size: 14px; font-weight: 600;">Pro</span>
                            <x-icon name="sparkle" :size="13" style="color: var(--pro);"/>
                        </div>
                        <div style="font-size: 12px; color: var(--ink-3);">{{ __('For serious operators') }}</div>
                    </div>
                    @if ($planKey === 'pro')
                        <span class="pill pill-pro"><span class="pill-dot"></span> {{ __('Current') }}</span>
                    @endif
                </div>
                <div style="display:flex; align-items:baseline; gap: 6px; margin-bottom: 4px;">
                    <span class="mono" style="font-size: 14px; color: var(--ink-3);">RM</span>
                    <span style="font-family: var(--font-display); font-size: 44px; line-height: 1; font-weight: 600;">{{ number_format($proPrice, 0) }}</span>
                    <span style="font-size: 13px; color: var(--ink-3);">/{{ __('month') }}</span>
                </div>
                <div style="font-size: 11.5px; color: var(--ink-3); margin-bottom: 18px;">
                    {{ __('7-day free trial · 0% commission · cancel anytime') }}
                </div>

                @include('tenant.subscription.partials.plan-cta', ['tier' => 'pro', 'tierPrice' => $proPrice])

                <div class="kicker" style="margin-bottom: 10px; color: var(--pro);">{{ __('Everything in Free, plus') }}</div>
                <div style="display:flex; flex-direction:column; gap: 9px;">
                    @foreach ($proFeatures as $it)
                        <div style="display:flex; gap: 10px; align-items:flex-start; font-size: 12.5px;">
                            <div style="width: 16px; height: 16px; border-radius: 999px; flex-shrink: 0;
                                        background: var(--primary-tint); color: var(--primary);
                                        display:flex; align-items:center; justify-content:center; margin-top: 2px;">
                                <x-icon name="check" :size="10"/>
                            </div>
                            <span style="font-weight: {{ ($it['strong'] ?? false) ? 600 : 400 }};">{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Ultra --}}
            <div class="hauz-card" style="padding: 24px; position: relative; border: .5px solid var(--ink-4);">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 18px;">
                    <div>
                        <div style="display:flex; align-items:center; gap: 6px; margin-bottom: 2px;">
                            <span style="font-size: 14px; font-weight: 600;">Ultra</span>
                        </div>
                        <div style="font-size: 12px; color: var(--ink-3);">{{ __('For multi-property brands') }}</div>
                    </div>
                    @if ($planKey === 'ultra')
                        <span class="pill pill-pro"><span class="pill-dot"></span> {{ __('Current') }}</span>
                    @endif
                </div>
                <div style="display:flex; align-items:baseline; gap: 6px; margin-bottom: 4px;">
                    <span class="mono" style="font-size: 14px; color: var(--ink-3);">RM</span>
                    <span style="font-family: var(--font-display); font-size: 44px; line-height: 1; font-weight: 600;">{{ number_format($ultraPrice, 0) }}</span>
                    <span style="font-size: 13px; color: var(--ink-3);">/{{ __('month') }}</span>
                </div>
                <div style="font-size: 11.5px; color: var(--ink-3); margin-bottom: 18px;">
                    {{ __('7-day free trial · 0% commission · cancel anytime') }}
                </div>

                @include('tenant.subscription.partials.plan-cta', ['tier' => 'ultra', 'tierPrice' => $ultraPrice])

                <div class="kicker" style="margin-bottom: 10px;">{{ __('Everything in Pro, plus') }}</div>
                <div style="display:flex; flex-direction:column; gap: 9px;">
                    @foreach ($ultraFeatures as $it)
                        <div style="display:flex; gap: 10px; align-items:flex-start; font-size: 12.5px;">
                            <div style="width: 16px; height: 16px; border-radius: 999px; flex-shrink: 0;
                                        background: var(--primary-tint); color: var(--primary);
                                        display:flex; align-items:center; justify-content:center; margin-top: 2px;">
                                <x-icon name="check" :size="10"/>
                            </div>
                            <span style="font-weight: {{ ($it['strong'] ?? false) ? 600 : 400 }};">{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Comparison table --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: .5px solid var(--line);">
                <div class="kicker" style="margin-bottom: 4px;">{{ __('Compare in detail') }}</div>
                <h3 style="margin: 0; font-family: var(--font-display); font-size: 24px; font-weight: 600;">{{ __('What you actually get') }}</h3>
            </div>
            <div class="sub-compare">
                <div style="display:grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; padding: 12px 24px; background: var(--bg-sunk); font-size: 11px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .08em;">
                    <span>{{ __('Feature') }}</span>
                    <span style="text-align:center;">{{ __('Free') }}</span>
                    <span style="text-align:center; color: var(--pro); font-weight: 600;">Pro</span>
                    <span style="text-align:center; font-weight: 600;">Ultra</span>
                </div>
                @foreach ($compareSections as $sec)
                    <div style="padding: 14px 24px 6px; font-size: 11px; font-weight: 600; color: var(--ink-2); text-transform: uppercase; letter-spacing: .06em; border-top: .5px solid var(--line);">
                        {{ $sec['title'] }}
                    </div>
                    @foreach ($sec['rows'] as $r)
                        <div style="display:grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; padding: 10px 24px; font-size: 12.5px; align-items:center; border-top: .5px solid var(--line);">
                            <span>{{ $r[0] }}</span>
                            @foreach ([1, 2, 3] as $i)
                                <span style="text-align:center;">
                                    @if ($r[$i] === true)
                                        <x-icon name="check" :size="15" style="color: var(--primary);"/>
                                    @elseif ($r[$i] === false)
                                        <x-icon name="x" :size="15" style="color: var(--ink-4);"/>
                                    @else
                                        <span style="font-size: 12.5px; color: var(--ink-2);">{{ $r[$i] }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- FAQ --}}
        <div class="sub-2col">
            @foreach ($faqs as $f)
                <div class="hauz-card" style="padding: 18px;">
                    <div style="font-size: 13px; font-weight: 600; margin-bottom: 6px;">{{ $f['q'] }}</div>
                    <div style="font-size: 12.5px; color: var(--ink-2); line-height: 1.5;">{{ $f['a'] }}</div>
                </div>
            @endforeach
        </div>

        <div style="text-align:center; font-size: 12px; color: var(--ink-3); padding: 24px 0;">
            {{ __('Monthly billing only. No contracts, no commission, cancel anytime.') }}
        </div>
    </div>
</x-app-layout>
