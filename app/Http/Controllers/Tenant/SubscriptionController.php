<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Billing\StripeBilling;
use App\Services\Billing\SubscriptionBillingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(SubscriptionBillingService $billing, StripeBilling $stripe)
    {
        $tenant = app(TenantContext::class)->current();
        $subscription = $tenant?->subscription;
        $plan = $subscription?->plan ?? Subscription::PLAN_FREE;

        return view('tenant.subscription.index', [
            'plan' => $plan,
            // The tier whose features the tenant actually holds right now
            // (free|pro|ultra — comped/trial/grace resolved).
            'planKey' => $subscription?->effectivePlanKey() ?? Subscription::PLAN_FREE,
            'tenant' => $tenant,
            'subscription' => $subscription,
            'trialDays' => Subscription::trialDays(),
            'canStartTrial' => $subscription !== null
                && $subscription->isFree()
                && ! $subscription->hasUsedTrial(),
            // Checkout only appears once Tempahlah's own Billplz account is
            // configured; until then the page tells the tenant to contact us.
            'billingConfigured' => $billing->configured(),
            // Card auto-renew UI only shows when Billplz Tokenization is switched
            // on for the platform; otherwise the page is exactly as before.
            'tokenizationEnabled' => $billing->tokenizationEnabled(),
            // Stripe recurring billing — the primary path when configured.
            'stripeEnabled' => $stripe->enabled(),
            // Per-tier checkout availability. Ultra needs its own recurring Stripe
            // Price on top of the base keys; until it's set, the Ultra card must
            // show an honest "opening soon" state instead of a button that bounces.
            'stripePlanAvailable' => [
                Subscription::PLAN_PRO => $stripe->planAvailable(Subscription::PLAN_PRO),
                Subscription::PLAN_ULTRA => $stripe->planAvailable(Subscription::PLAN_ULTRA),
            ],
            'openInvoice' => $subscription && ! $subscription->isComped()
                ? $billing->openInvoiceFor($subscription)
                : null,
        ]);
    }

    public function change(Request $request)
    {
        $validated = $request->validate([
            // 'paid' is the legacy 2-tier value some cached forms still post —
            // normalizePlanKey() maps it to 'pro'.
            'plan' => 'required|in:free,pro,ultra,paid',
            'billing' => 'nullable|in:monthly,yearly',
        ]);

        $targetPlan = Subscription::normalizePlanKey($validated['plan']);

        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $subscription = $tenant->subscription;
        abort_unless($subscription, 404, 'No subscription record');

        // Comped accounts are an admin grant. Self-downgrading would leave the
        // row on the free plan while comped_at still forces isPaid() true.
        if ($subscription->isComped()) {
            return redirect()
                ->route('tenant.subscription')
                ->with('status', __('Your account has complimentary access — there is nothing to change.'));
        }

        if (in_array($targetPlan, Subscription::PAID_PLANS, true) && $subscription->isFree()) {
            return $this->startTrial($subscription, $validated['billing'] ?? 'monthly', $targetPlan);
        }

        if ($targetPlan === Subscription::PLAN_FREE && ! $subscription->isFree()) {
            return $this->downgrade($subscription);
        }

        // Pro ⇄ Ultra switching changes what we charge, so it happens through
        // checkout (Stripe), not this card-less path.
        if (in_array($targetPlan, Subscription::PAID_PLANS, true)
            && ! $subscription->isFree()
            && $subscription->planKey() !== $targetPlan) {
            return redirect()
                ->route('tenant.subscription')
                ->with('error', __('Plan switching is handled through checkout — please use the upgrade button, or contact us.'));
        }

        return redirect()
            ->route('tenant.subscription')
            ->with('status', __('No change — you\'re already on that plan.'));
    }

    /**
     * Starts the one-and-only free trial of the paid plan.
     *
     * This is NOT a way to become a paying customer — no money changes hands and
     * ProcessSubscriptionLifecycle will expire the trial, grace it, then drop the
     * tenant back to free. Real checkout arrives with platform billing; until it
     * exists, a tenant who has already used their trial cannot reach the paid
     * plan from here at all.
     */
    private function startTrial(Subscription $subscription, string $billing, string $plan = Subscription::PLAN_PRO)
    {
        // When Stripe is live, a trial requires a card up front and can only be
        // started via Stripe Checkout — never this card-less path. The UI hides
        // the card-less button in that case; this blocks a crafted request too.
        if (app(StripeBilling::class)->enabled()) {
            return redirect()
                ->route('tenant.subscription')
                ->with('error', __('Please start your free trial with the card button on this page.'));
        }

        if ($subscription->hasUsedTrial()) {
            return redirect()
                ->route('tenant.subscription')
                ->with('error', __('You have already used your free trial. Paid billing is opening soon — please contact us to upgrade.'));
        }

        $trialEndsAt = now()->addDays(\App\Support\Billing\Plans::trialDays($plan) ?: Subscription::trialDays());

        $subscription->update([
            'plan' => $plan,
            'status' => Subscription::STATUS_TRIALING,
            'billing_method' => 'manual',
            // The cadence is only decided at checkout, so record the tenant's
            // intent and price the subscription at the plan's standard rate.
            'monthly_amount' => \App\Support\Billing\Plans::price($plan),
            'currency' => 'MYR',
            'trial_ends_at' => $trialEndsAt,
            'trial_used_at' => now(),
            'grace_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => $trialEndsAt,
            'cancelled_at' => null,
            'meta' => array_merge($subscription->meta ?? [], ['billing_preference' => $billing]),
        ]);

        return redirect()
            ->route('tenant.subscription')
            ->with('status', __('Welcome to :plan! Your :days-day trial has started — full access until :date.', [
                'plan' => \App\Support\Billing\Plans::name($plan),
                'days' => \App\Support\Billing\Plans::trialDays($plan) ?: Subscription::trialDays(),
                'date' => $trialEndsAt->format('d M Y'),
            ]));
    }

    /**
     * trial_used_at is deliberately preserved — clearing it would let a tenant
     * cycle paid → free → paid and farm an unlimited run of free trials.
     */
    private function downgrade(Subscription $subscription)
    {
        $subscription->update([
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
            'monthly_amount' => 0,
            'cancelled_at' => now(),
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addYear(),
        ]);

        return redirect()
            ->route('tenant.subscription')
            ->with('status', __('Switched back to Starter. Your data stays — extra properties become read-only.'));
    }
}
