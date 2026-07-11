<x-app-layout :title="__('Pricing')">
    @php
        $billing = request()->query('billing', 'monthly');
        if (! in_array($billing, ['monthly', 'yearly'], true)) $billing = 'monthly';
        $proPrice = $billing === 'monthly' ? 49 : 39;

        $starterFeatures = [
            ['ok' => true, 'text' => __('1 active homestay')],
            ['ok' => true, 'text' => __('Unlimited rooms & bookings')],
            ['ok' => true, 'text' => __('Public booking page (yourname.tempahlah.com)')],
            ['ok' => true, 'text' => __('Google Calendar 2-way sync')],
            ['ok' => true, 'text' => __('Manual payment tracking (cash, FPX, transfer)')],
            ['ok' => true, 'text' => __('Basic statistics dashboard')],
            ['ok' => true, 'text' => __('5 guest emails / day (via AWS SES shared)')],
            ['ok' => true, 'text' => __('Mobile app (iOS, Android, PWA)')],
            ['ok' => false, 'text' => __('Toyyibpay payment gateway')],
            ['ok' => false, 'text' => __('Auto-reminders (T-7, T-3, T-1)')],
            ['ok' => false, 'text' => __('WhatsApp Business templates')],
            ['ok' => false, 'text' => __('Multi-property switching')],
        ];

        $proFeatures = [
            ['ok' => true, 'text' => __('Unlimited active homestays'), 'strong' => true],
            ['ok' => true, 'text' => __('Toyyibpay gateway (FPX, e-wallet, cards)'), 'strong' => true],
            ['ok' => true, 'text' => __('Auto-reminders 7 / 3 / 1 days before check-in'), 'strong' => true],
            ['ok' => true, 'text' => __('Smart deposit collection + balance links')],
            ['ok' => true, 'text' => __('WhatsApp Business templates')],
            ['ok' => true, 'text' => __('Dedicated booking page + custom domain')],
            ['ok' => true, 'text' => __('Dynamic pricing (weekend, season, holiday)')],
            ['ok' => true, 'text' => __('Channel manager (Airbnb iCal, Booking.com)')],
            ['ok' => true, 'text' => __('Advanced analytics & exportable reports')],
            ['ok' => true, 'text' => __('Unlimited guest emails (AWS SES dedicated IP)')],
            ['ok' => true, 'text' => __('Staff accounts (housekeeping, manager roles)')],
            ['ok' => true, 'text' => __('Priority support (1-hr response, BM/EN)')],
        ];

        $compareSections = [
            ['title' => __('Properties & rooms'), 'rows' => [
                [__('Active homestays'), '1', __('Unlimited')],
                [__('Rooms per homestay'), __('Unlimited'), __('Unlimited')],
                [__('Photos per room'), '10', __('Unlimited')],
                [__('Custom booking page'), '{slug}.tempahlah.com', __('Custom domain')],
            ]],
            ['title' => __('Payments'), 'rows' => [
                [__('Manual payment tracking'), true, true],
                [__('Toyyibpay payment gateway'), false, true],
                [__('Auto-issued receipts (PDF)'), false, true],
                [__('Deposit + balance link automation'), false, true],
                [__('Platform transaction fee'), '—', '0%'],
            ]],
            ['title' => __('Communications'), 'rows' => [
                [__('Confirmation email (AWS SES)'), true, true],
                [__('Auto-reminder T-7 / T-3 / T-1'), false, true],
                [__('WhatsApp Business templates'), false, true],
                [__('Custom email templates'), __('1 default'), __('Unlimited')],
                [__('Daily email send limit'), '5', __('Unlimited')],
            ]],
            ['title' => __('Calendar & channels'), 'rows' => [
                [__('Google Calendar 2-way sync'), true, true],
                [__('Airbnb / Booking.com iCal'), false, true],
                [__('Drag-to-block date ranges'), false, true],
                [__('Dynamic pricing rules'), false, true],
            ]],
            ['title' => __('Analytics'), 'rows' => [
                [__('Basic dashboard'), true, true],
                [__('Property-level reports'), false, true],
                [__('Export to CSV / Excel'), false, true],
                [__('Tax-ready monthly summary'), false, true],
            ]],
            ['title' => __('Team & support'), 'rows' => [
                [__('Staff accounts'), __('Owner only'), __('Unlimited (with roles)')],
                [__('Mobile app (iOS / Android / PWA)'), true, true],
                [__('Support response time'), __('Email · 48h'), __('Email + WhatsApp · 1h')],
            ]],
        ];

        $faqs = [
            ['q' => __('Can I switch back to Free?'), 'a' => __('Yes, anytime. Your data stays. Properties beyond your 1 free slot become read-only — no bookings are lost.')],
            ['q' => __('How does Toyyibpay work with Pro?'), 'a' => __('We connect your Toyyibpay merchant account. Guests pay directly to you (FPX, cards, e-wallets). Toyyibpay\'s own fee applies; the platform takes 0% transaction fee.')],
            ['q' => __('Will guests\' emails come from my domain?'), 'a' => __('On Pro, yes — connect your domain and we route through AWS SES with verified DKIM/SPF, so emails land in inbox, not spam.')],
            ['q' => __('Mobile app vs PWA?'), 'a' => __('All three (iOS, Android, PWA) talk to the same Laravel API. Same login. Same data. Pick whichever you and your housekeepers prefer.')],
        ];
    @endphp

    <style>
        /* `1fr` has an automatic minimum of the content's min-width, so the two
           plan cards refused to shrink (238px + 196px = 435px) and pushed the page
           sideways on phones. minmax(0,1fr) lets them shrink; below 640px they
           stack. Same for the FAQ pair. */
        .sub-2col { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        /* The 3-column comparison rows can't fit a phone — scroll them together as
           one block so the header and rows stay aligned. */
        .sub-compare { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .sub-compare > div { min-width: 520px; }
        @media (max-width: 640px) {
            .sub-2col { grid-template-columns: 1fr; }
        }
    </style>

    <div style="max-width: 1100px; margin: 0 auto; display:flex; flex-direction:column; gap: 24px;">

        @if (session('status'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--ok); color: var(--ok);">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); color: var(--err);">
                {{ session('error') }}
            </div>
        @endif

        {{-- Past due: Pro is still on, but only until grace runs out. --}}
        @if ($subscription?->inGrace())
            <div class="hauz-card" style="padding: 16px 18px; border-color: var(--warn); background: var(--warn-tint);">
                <div style="font-weight: 600; margin-bottom: 4px;">{{ __('Your subscription is unpaid') }}</div>
                <div style="font-size: 13px; color: var(--ink-2);">
                    {{ __('Your Pro features stay on until :date. After that your account moves to the free plan — your data stays, but online payments, invoices and receipts switch off.', [
                        'date' => $subscription->grace_ends_at->format('d M Y'),
                    ]) }}
                </div>
                @if ($billingConfigured)
                    <form method="POST" action="{{ route('tenant.subscription.checkout') }}" style="margin-top: 12px;">
                        @csrf
                        <x-btn-submit class="btn btn-primary btn-sm">
                            {{ __('Pay now') }} — RM {{ number_format($openInvoice?->amount ?? config('homestay.paid_tier_price'), 2) }}
                        </x-btn-submit>
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
                    <x-btn-submit class="btn btn-primary btn-sm">{{ __('Pay now') }}</x-btn-submit>
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
                {{ __("Start free with one homestay. Upgrade when you're ready for payment gateways, auto-reminders, and unlimited properties.") }}
            </div>

            {{-- Billing toggle --}}
            <div style="display:inline-flex; margin-top: 24px; padding: 4px; gap: 4px; background: var(--bg-sunk); border: .5px solid var(--line); border-radius: 999px;">
                @foreach (['monthly' => __('Monthly'), 'yearly' => __('Yearly')] as $key => $label)
                    @php $active = $billing === $key; @endphp
                    <a href="{{ route('tenant.subscription', ['billing' => $key]) }}"
                       class="btn btn-sm"
                       style="border:0; background: {{ $active ? 'var(--bg-elev)' : 'transparent' }};
                              box-shadow: {{ $active ? 'var(--sh-1)' : 'none' }};
                              font-weight: 500; padding: 0 16px; text-decoration:none;">
                        {{ $label }}
                        @if ($key === 'yearly')
                            <span class="pill pill-ok" style="margin-left: 6px; height: 16px; font-size: 9.5px;">{{ __('Save 20%') }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Plan cards --}}
        <div class="sub-2col" style="margin-top: 12px;">

            {{-- Starter (Free) --}}
            <div class="hauz-card" style="padding: 28px; position: relative;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 18px;">
                    <div>
                        <div style="font-size: 14px; font-weight: 600; margin-bottom: 2px;">{{ __('Starter') }}</div>
                        <div style="font-size: 12px; color: var(--ink-3);">{{ __('For solo owners testing the waters') }}</div>
                    </div>
                    @if ($plan === 'free')
                        <span class="pill pill-primary"><span class="pill-dot"></span> {{ __('Current') }}</span>
                    @endif
                </div>
                <div style="display:flex; align-items:baseline; gap: 6px; margin-bottom: 24px;">
                    <span style="font-family: var(--font-display); font-size: 56px; line-height: 1; font-weight: 600;">RM 0</span>
                    <span style="font-size: 13px; color: var(--ink-3);">/{{ __('forever') }}</span>
                </div>
                @if ($plan === 'free')
                    <button type="button" class="btn" style="width:100%; justify-content:center; margin-bottom: 22px; opacity: 0.5;" disabled>
                        {{ __("You're on Starter") }}
                    </button>
                @else
                    <form method="POST" action="{{ route('tenant.subscription.change') }}" style="margin-bottom: 22px;">
                        @csrf
                        <input type="hidden" name="plan" value="free">
                        <button type="submit" class="btn" style="width:100%; justify-content:center;">
                            {{ __('Switch to Starter') }}
                        </button>
                    </form>
                @endif
                <div class="kicker" style="margin-bottom: 10px;">{{ __('Includes') }}</div>
                <div style="display:flex; flex-direction:column; gap: 9px;">
                    @foreach ($starterFeatures as $it)
                        <div style="display:flex; gap: 10px; align-items:flex-start; font-size: 12.5px;">
                            <div style="width: 16px; height: 16px; border-radius: 999px; flex-shrink: 0;
                                        background: {{ $it['ok'] ? 'var(--primary-tint)' : 'var(--bg-sunk)' }};
                                        color: {{ $it['ok'] ? 'var(--primary)' : 'var(--ink-4)' }};
                                        display:flex; align-items:center; justify-content:center; margin-top: 2px;">
                                <x-icon :name="$it['ok'] ? 'check' : 'x'" :size="10"/>
                            </div>
                            <span style="color: {{ $it['ok'] ? 'var(--ink)' : 'var(--ink-4)' }};
                                         {{ $it['ok'] ? '' : 'text-decoration: line-through;' }}
                                         font-weight: {{ ($it['strong'] ?? false) ? 600 : 400 }};">
                                {{ $it['text'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Pro --}}
            <div class="hauz-card" style="padding: 28px;
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
                        <div style="font-size: 12px; color: var(--ink-3);">{{ __('For serious operators & multi-property hosts') }}</div>
                    </div>
                    @if ($plan !== 'free')
                        <span class="pill pill-pro"><span class="pill-dot"></span> {{ __('Current') }}</span>
                    @endif
                </div>
                <div style="display:flex; align-items:baseline; gap: 6px; margin-bottom: 4px;">
                    <span class="mono" style="font-size: 14px; color: var(--ink-3);">RM</span>
                    <span style="font-family: var(--font-display); font-size: 56px; line-height: 1; font-weight: 600;">{{ $proPrice }}</span>
                    <span style="font-size: 13px; color: var(--ink-3);">/{{ __('month') }}</span>
                    @if ($billing === 'yearly')
                        <span style="font-size: 12px; color: var(--pro); margin-left: 6px;">{{ __('billed RM 468/yr') }}</span>
                    @endif
                </div>
                <div style="font-size: 11.5px; color: var(--ink-3); margin-bottom: 18px;">
                    {{ __('≈ Less than one Garden Room booking. Cancel anytime.') }}
                </div>
                @if ($plan !== 'free')
                    <button type="button" class="btn" style="width:100%; justify-content:center; margin-bottom: 14px;
                        background: var(--ink); color: var(--bg); border-color: transparent; opacity: 0.5;"
                        disabled>
                        {{ $subscription?->isComped() ? __('Complimentary Pro') : __("You're on Pro") }}
                    </button>
                    {{-- Card on file / auto-renew panel. Only when Tokenization is
                         live AND this isn't a comped account (comped never pays). --}}
                    @if ($tokenizationEnabled && ! $subscription?->isComped())
                        @if ($subscription?->card_status === \App\Models\Subscription::CARD_ACTIVE && $subscription?->card_id)
                            <div style="border: 1px solid var(--line); border-radius: var(--r-md); padding: 12px 14px; margin-bottom: 22px;">
                                <div style="display:flex; align-items:center; justify-content:space-between; gap: 10px;">
                                    <div style="font-size: 12.5px; color: var(--ink);">
                                        <x-icon name="card" :size="13"/>
                                        {{ $subscription->card_brand ? ucfirst($subscription->card_brand) : __('Card') }}
                                        •••• {{ $subscription->card_last4 ?: '····' }}
                                    </div>
                                    <span class="pill {{ $subscription->auto_renew ? 'pill-ok' : '' }}" style="height: 18px; font-size: 10.5px;">
                                        <span class="pill-dot"></span>{{ $subscription->auto_renew ? __('Auto-renew on') : __('Auto-renew off') }}
                                    </span>
                                </div>
                                <form method="POST" action="{{ route('tenant.subscription.card.toggle') }}" style="margin-top: 10px;">
                                    @csrf
                                    <input type="hidden" name="auto_renew" value="{{ $subscription->auto_renew ? '0' : '1' }}">
                                    <button type="submit" class="btn btn-sm" style="width:100%; justify-content:center;">
                                        {{ $subscription->auto_renew ? __('Turn auto-renew off') : __('Turn auto-renew on') }}
                                    </button>
                                </form>
                            </div>
                        @else
                            {{-- Pro but no card yet (e.g. on trial) — offer to add one. --}}
                            <form method="POST" action="{{ route('tenant.subscription.card.enroll') }}" style="margin-bottom: 22px;">
                                @csrf
                                <x-btn-submit class="btn btn-sm" style="width:100%; justify-content:center;">
                                    <x-icon name="card" :size="13"/> {{ __('Add a card for auto-renew') }}
                                </x-btn-submit>
                                <div style="font-size: 11px; color: var(--ink-3); text-align:center; margin-top: 6px;">
                                    {{ __('Visa / Mastercard. Charged automatically each month.') }}
                                </div>
                            </form>
                        @endif
                    @endif
                @elseif ($canStartTrial)
                    <form method="POST" action="{{ route('tenant.subscription.change') }}" style="margin-bottom: 22px;">
                        @csrf
                        <input type="hidden" name="plan" value="paid">
                        <input type="hidden" name="billing" value="{{ $billing }}">
                        <button type="submit" class="btn" style="width:100%; justify-content:center;
                            background: var(--ink); color: var(--bg); border-color: transparent;">
                            {{ __('Start :days-day free trial', ['days' => $trialDays]) }}
                        </button>
                    </form>
                @elseif ($tokenizationEnabled)
                    {{-- Card-first: auto-renew is the primary path, one manual FPX
                         payment stays available for bank users who can't tokenize. --}}
                    <form method="POST" action="{{ route('tenant.subscription.card.enroll') }}" style="margin-bottom: 8px;">
                        @csrf
                        <x-btn-submit class="btn" style="width:100%; justify-content:center;
                            background: var(--ink); color: var(--bg); border-color: transparent;">
                            <x-icon name="card" :size="14"/>
                            {{ __('Subscribe with auto-renew') }} — RM {{ number_format((float) config('homestay.paid_tier_price'), 2) }}/{{ __('mo') }}
                        </x-btn-submit>
                    </form>
                    <div style="font-size: 11px; color: var(--ink-3); text-align:center; margin-bottom: 10px;">
                        {{ __('Visa / Mastercard — charged automatically each month.') }}
                    </div>
                    <form method="POST" action="{{ route('tenant.subscription.checkout') }}" style="margin-bottom: 22px; text-align:center;">
                        @csrf
                        <button type="submit" style="background:none; border:none; padding:0; cursor:pointer;
                            font-size: 12px; color: var(--ink-3); text-decoration: underline;">
                            {{ __('or pay once by FPX / online banking') }}
                        </button>
                    </form>
                @elseif ($billingConfigured)
                    {{-- Trial used (or declined) — pay for real (no card auto-renew). --}}
                    <form method="POST" action="{{ route('tenant.subscription.checkout') }}" style="margin-bottom: 22px;">
                        @csrf
                        <x-btn-submit class="btn" style="width:100%; justify-content:center;
                            background: var(--ink); color: var(--bg); border-color: transparent;">
                            {{ __('Upgrade to Pro') }} — RM {{ number_format((float) config('homestay.paid_tier_price'), 2) }}/{{ __('mo') }}
                        </x-btn-submit>
                    </form>
                @else
                    {{-- Trial used, and Tempahlah's own Billplz account isn't configured yet. --}}
                    <button type="button" class="btn" style="width:100%; justify-content:center; margin-bottom: 8px;
                        background: var(--ink); color: var(--bg); border-color: transparent; opacity: 0.5;"
                        disabled>
                        {{ __('Free trial already used') }}
                    </button>
                    <div style="font-size: 12px; color: var(--ink-3); text-align:center; margin-bottom: 22px;">
                        {{ __('Paid billing is opening soon — contact us to upgrade.') }}
                    </div>
                @endif
                <div class="kicker" style="margin-bottom: 10px; color: var(--pro);">{{ __('Everything in Starter, plus') }}</div>
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
        </div>

        {{-- Comparison table --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: .5px solid var(--line);">
                <div class="kicker" style="margin-bottom: 4px;">{{ __('Compare in detail') }}</div>
                <h3 style="margin: 0; font-family: var(--font-display); font-size: 24px; font-weight: 600;">{{ __('What you actually get') }}</h3>
            </div>
            <div class="sub-compare">
                <div style="display:grid; grid-template-columns: 1.4fr 1fr 1fr; padding: 12px 24px; background: var(--bg-sunk); font-size: 11px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .08em;">
                    <span>{{ __('Feature') }}</span>
                    <span style="text-align:center;">{{ __('Starter') }}</span>
                    <span style="text-align:center; color: var(--pro); font-weight: 600;">Pro</span>
                </div>
                @foreach ($compareSections as $sec)
                    <div style="padding: 14px 24px 6px; font-size: 11px; font-weight: 600; color: var(--ink-2); text-transform: uppercase; letter-spacing: .06em; border-top: .5px solid var(--line);">
                        {{ $sec['title'] }}
                    </div>
                    @foreach ($sec['rows'] as $r)
                        <div style="display:grid; grid-template-columns: 1.4fr 1fr 1fr; padding: 10px 24px; font-size: 12.5px; align-items:center; border-top: .5px solid var(--line);">
                            <span>{{ $r[0] }}</span>
                            <span style="text-align:center;">
                                @if ($r[1] === true)
                                    <x-icon name="check" :size="15" style="color: var(--primary);"/>
                                @elseif ($r[1] === false)
                                    <x-icon name="x" :size="15" style="color: var(--ink-4);"/>
                                @else
                                    <span style="font-size: 12.5px; color: var(--ink-2);">{{ $r[1] }}</span>
                                @endif
                            </span>
                            <span style="text-align:center;">
                                @if ($r[2] === true)
                                    <x-icon name="check" :size="15" style="color: var(--primary);"/>
                                @elseif ($r[2] === false)
                                    <x-icon name="x" :size="15" style="color: var(--ink-4);"/>
                                @else
                                    <span style="font-size: 12.5px; color: var(--ink-2);">{{ $r[2] }}</span>
                                @endif
                            </span>
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
            {{ __('v1 uses manual confirmation. v2 will introduce Billplz recurring billing — your subscription will migrate automatically.') }}
        </div>
    </div>
</x-app-layout>
