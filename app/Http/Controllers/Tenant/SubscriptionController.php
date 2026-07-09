<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        $subscription = $tenant?->subscription;
        $plan = $subscription?->plan ?? Subscription::PLAN_FREE;

        return view('tenant.subscription.index', [
            'plan' => $plan,
            'tenant' => $tenant,
            'subscription' => $subscription,
            'trialDays' => Subscription::trialDays(),
            'canStartTrial' => $subscription !== null
                && $subscription->isFree()
                && ! $subscription->hasUsedTrial(),
        ]);
    }

    public function change(Request $request)
    {
        $validated = $request->validate([
            'plan' => 'required|in:free,paid',
            'billing' => 'nullable|in:monthly,yearly',
        ]);

        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $subscription = $tenant->subscription;
        abort_unless($subscription, 404, 'No subscription record');

        // Comped accounts are an admin grant. Self-downgrading would leave the
        // row on the free plan while comped_at still forces isPaid() true.
        if ($subscription->isComped()) {
            return redirect()
                ->route('tenant.subscription')
                ->with('status', __('Your account has complimentary Pro access — there is nothing to change.'));
        }

        if ($validated['plan'] === Subscription::PLAN_PAID && $subscription->isFree()) {
            return $this->startTrial($subscription, $validated['billing'] ?? 'monthly');
        }

        if ($validated['plan'] === Subscription::PLAN_FREE && ! $subscription->isFree()) {
            return $this->downgrade($subscription);
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
    private function startTrial(Subscription $subscription, string $billing)
    {
        if ($subscription->hasUsedTrial()) {
            return redirect()
                ->route('tenant.subscription')
                ->with('error', __('You have already used your free trial. Paid billing is opening soon — please contact us to upgrade.'));
        }

        $trialEndsAt = now()->addDays(Subscription::trialDays());

        $subscription->update([
            'plan' => Subscription::PLAN_PAID,
            'status' => Subscription::STATUS_TRIALING,
            'billing_method' => 'manual',
            // The monthly/yearly cadence is only decided at checkout, so record
            // the tenant's intent and price the subscription at the standard rate.
            'monthly_amount' => (float) config('homestay.paid_tier_price', 49.00),
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
            ->with('status', __('Welcome to Pro! Your :days-day trial has started — full access until :date.', [
                'days' => Subscription::trialDays(),
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
