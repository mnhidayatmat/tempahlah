{{-- CTA block for one paid plan card (pro | ultra).
     Expects: $tier, $tierPrice, $planKey, $subscription, $stripeEnabled,
     $billingConfigured, $tokenizationEnabled, $canStartTrial, $trialDays.
     The three billing rails (Stripe recurring, Billplz tokenization, Billplz
     pay-link) predate the 3-tier model — Stripe is plan-aware (hidden plan
     field), the Billplz rails only ever bill the CURRENT subscription's plan,
     so they are offered on the tenant's own tier only. --}}
@php
    $tierName = \App\Support\Billing\Plans::name($tier);
    $isCurrent = $planKey === $tier;
    $onOtherPaidTier = $planKey !== 'free' && ! $isCurrent;
@endphp

@if ($isCurrent)
    <button type="button" class="btn" style="width:100%; justify-content:center; margin-bottom: 14px;
        background: var(--ink); color: var(--bg); border-color: transparent; opacity: 0.5;"
        disabled>
        {{ $subscription?->isComped() ? __('Complimentary :plan', ['plan' => $tierName]) : __("You're on :plan", ['plan' => $tierName]) }}
    </button>

    {{-- Stripe-managed subscription → in-app cancel / resume + Customer Portal. --}}
    @if ($stripeEnabled && $subscription?->isStripeManaged() && ! $subscription?->isComped())
        @php
            $cancelScheduled = (bool) data_get($subscription->meta, 'stripe_cancel_at_period_end', false);
            $endsOn = ($subscription->onTrial() ? $subscription->trial_ends_at : $subscription->current_period_end);
        @endphp
        <div style="border: 1px solid var(--line); border-radius: var(--r-md); padding: 12px 14px; margin-bottom: 22px;">
            @if ($subscription->onTrial())
                <div style="display:flex; align-items:center; gap: 8px; font-size: 12.5px; color: var(--ink); margin-bottom: 8px;">
                    <x-icon name="sparkle" :size="13" style="color: var(--pro);"/>
                    <span>{{ __('Free trial — RM :price/mo starts :date', ['price' => number_format($tierPrice, 0), 'date' => $endsOn?->format('d M Y')]) }}</span>
                </div>
            @else
                <div style="display:flex; align-items:center; gap: 8px; font-size: 12.5px; color: var(--ink); margin-bottom: 8px;">
                    <x-icon name="card" :size="13"/>
                    <span>{{ __('Auto-renewing via Stripe · renews :date', ['date' => $endsOn?->format('d M Y')]) }}</span>
                </div>
            @endif

            @if ($cancelScheduled)
                <div style="font-size: 12px; color: var(--warn); margin-bottom: 10px;">
                    {{ __('Set to cancel on :date — you won\'t be charged again.', ['date' => $endsOn?->format('d M Y')]) }}
                </div>
                <form method="POST" action="{{ route('tenant.subscription.stripe.resume') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="width:100%; justify-content:center;">
                        {{ __('Resume subscription') }}
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('tenant.subscription.stripe.cancel') }}"
                      onsubmit="return confirm('{{ __('Cancel your subscription? You keep :plan until the date shown, then move to Free.', ['plan' => $tierName]) }}');">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="width:100%; justify-content:center;">
                        {{ $subscription->onTrial() ? __('Cancel trial') : __('Cancel subscription') }}
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('tenant.subscription.stripe.portal') }}" style="margin-top: 8px;">
                @csrf
                <button type="submit" class="btn btn-sm" style="width:100%; justify-content:center; background: transparent;">
                    {{ __('Update card / manage on Stripe') }}
                </button>
            </form>
        </div>
    {{-- Billplz card-on-file / auto-renew panel (dormant unless Tokenization is live). --}}
    @elseif ($tokenizationEnabled && ! $subscription?->isComped())
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
            <form method="POST" action="{{ route('tenant.subscription.card.enroll') }}" style="margin-bottom: 22px;">
                @csrf
                <button type="submit" class="btn btn-sm" style="width:100%; justify-content:center;">
                    <x-icon name="card" :size="13"/> {{ __('Add a card for auto-renew') }}
                </button>
                <div style="font-size: 11px; color: var(--ink-3); text-align:center; margin-top: 6px;">
                    {{ __('Visa / Mastercard. Charged automatically each month.') }}
                </div>
            </form>
        @endif
    @endif
@elseif ($onOtherPaidTier)
    {{-- Pro ⇄ Ultra switch changes the charge — with a live Stripe sub that is
         a price swap we don't do in-app yet. Keep it honest. --}}
    @if ($subscription?->isStripeManaged())
        <div style="font-size: 12px; color: var(--ink-3); text-align:center; margin-bottom: 22px; border: 1px dashed var(--line); border-radius: var(--r-md); padding: 12px;">
            {{ __('You have an active subscription — contact us to switch to :plan.', ['plan' => $tierName]) }}
        </div>
    @elseif ($stripeEnabled)
        <form method="POST" action="{{ route('tenant.subscription.stripe.checkout') }}" style="margin-bottom: 22px;">
            @csrf
            <input type="hidden" name="plan" value="{{ $tier }}">
            <button type="submit" class="btn" style="width:100%; justify-content:center;">
                <x-icon name="card" :size="14"/>
                {{ __('Switch to :plan', ['plan' => $tierName]) }} — RM {{ number_format($tierPrice, 0) }}/{{ __('mo') }}
            </button>
        </form>
    @else
        <div style="font-size: 12px; color: var(--ink-3); text-align:center; margin-bottom: 22px;">
            {{ __('Contact us to switch to :plan.', ['plan' => $tierName]) }}
        </div>
    @endif
@elseif ($stripeEnabled)
    {{-- Stripe is the primary path. A tenant who has never trialed gets the
         card-required 7-day trial; a returning one is charged now. --}}
    <form method="POST" action="{{ route('tenant.subscription.stripe.checkout') }}" style="margin-bottom: 8px;">
        @csrf
        <input type="hidden" name="plan" value="{{ $tier }}">
        <button type="submit" class="btn" style="width:100%; justify-content:center;
            background: var(--ink); color: var(--bg); border-color: transparent;">
            <x-icon name="card" :size="14"/>
            @if ($canStartTrial)
                {{ __('Start :days-day free trial', ['days' => $trialDays]) }}
            @else
                {{ __('Subscribe with auto-renew') }} — RM {{ number_format($tierPrice, 2) }}/{{ __('mo') }}
            @endif
        </button>
    </form>
    <div style="font-size: 11px; color: var(--ink-3); text-align:center; margin-bottom: 10px;">
        @if ($canStartTrial)
            {{ __('Card required — no charge for :days days, then RM :price/mo. Cancel any time before then.', ['days' => $trialDays, 'price' => number_format($tierPrice, 0)]) }}
        @else
            {{ __('Card auto-renews monthly. Cancel any time.') }}
        @endif
    </div>
    @if ($canStartTrial)
        <form method="POST" action="{{ route('tenant.subscription.stripe.checkout') }}" style="margin-bottom: 22px; text-align:center;">
            @csrf
            <input type="hidden" name="plan" value="{{ $tier }}">
            <input type="hidden" name="skip_trial" value="1">
            <button type="submit" style="background:none; border:none; padding:0; cursor:pointer;
                font-size: 12px; color: var(--ink-3); text-decoration: underline;">
                {{ __('or subscribe now and pay today — skip the free trial') }}
            </button>
        </form>
    @endif
    @if ($billingConfigured && $tier === \App\Models\Subscription::PLAN_PRO)
        <form method="POST" action="{{ route('tenant.subscription.checkout') }}" style="margin-bottom: 22px; text-align:center;">
            @csrf
            <button type="submit" style="background:none; border:none; padding:0; cursor:pointer;
                font-size: 12px; color: var(--ink-3); text-decoration: underline;">
                {{ __('or pay once by FPX / online banking') }}
            </button>
        </form>
    @endif
@elseif ($canStartTrial)
    {{-- Fallback only while Stripe isn't configured: the legacy card-less trial. --}}
    <form method="POST" action="{{ route('tenant.subscription.change') }}" style="margin-bottom: 22px;">
        @csrf
        <input type="hidden" name="plan" value="{{ $tier }}">
        <button type="submit" class="btn" style="width:100%; justify-content:center;
            background: var(--ink); color: var(--bg); border-color: transparent;">
            {{ __('Start :days-day free trial', ['days' => $trialDays]) }}
        </button>
    </form>
@elseif ($tokenizationEnabled && $tier === \App\Models\Subscription::PLAN_PRO)
    <form method="POST" action="{{ route('tenant.subscription.card.enroll') }}" style="margin-bottom: 8px;">
        @csrf
        <button type="submit" class="btn" style="width:100%; justify-content:center;
            background: var(--ink); color: var(--bg); border-color: transparent;">
            <x-icon name="card" :size="14"/>
            {{ __('Subscribe with auto-renew') }} — RM {{ number_format($tierPrice, 2) }}/{{ __('mo') }}
        </button>
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
@elseif ($billingConfigured && $tier === \App\Models\Subscription::PLAN_PRO)
    <form method="POST" action="{{ route('tenant.subscription.checkout') }}" style="margin-bottom: 22px;">
        @csrf
        <button type="submit" class="btn" style="width:100%; justify-content:center;
            background: var(--ink); color: var(--bg); border-color: transparent;">
            {{ __('Upgrade to :plan', ['plan' => $tierName]) }} — RM {{ number_format($tierPrice, 2) }}/{{ __('mo') }}
        </button>
    </form>
@else
    <button type="button" class="btn" style="width:100%; justify-content:center; margin-bottom: 8px;
        background: var(--ink); color: var(--bg); border-color: transparent; opacity: 0.5;"
        disabled>
        {{ __('Free trial already used') }}
    </button>
    <div style="font-size: 12px; color: var(--ink-3); text-align:center; margin-bottom: 22px;">
        {{ __('Paid billing is opening soon — contact us to upgrade.') }}
    </div>
@endif
