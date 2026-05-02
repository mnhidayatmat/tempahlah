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
        $plan = $tenant?->subscription?->plan ?? Subscription::PLAN_FREE;

        return view('tenant.subscription.index', compact('plan', 'tenant'));
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

        if ($validated['plan'] === Subscription::PLAN_PAID && $subscription->plan === Subscription::PLAN_FREE) {
            $subscription->update([
                'plan' => Subscription::PLAN_PAID,
                'status' => Subscription::STATUS_TRIALING,
                'billing_method' => 'manual',
                'monthly_amount' => $validated['billing'] === 'yearly' ? 39 : 49,
                'currency' => 'MYR',
                'trial_ends_at' => now()->addDays(14),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(14),
                'cancelled_at' => null,
            ]);

            return redirect()
                ->route('tenant.subscription')
                ->with('status', __('Welcome to Pro! Your 14-day trial has started — full access until :date.', [
                    'date' => $subscription->trial_ends_at->format('d M Y'),
                ]));
        }

        if ($validated['plan'] === Subscription::PLAN_FREE && $subscription->plan === Subscription::PLAN_PAID) {
            $subscription->update([
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
                'monthly_amount' => 0,
                'cancelled_at' => now(),
                'trial_ends_at' => null,
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
            ]);

            return redirect()
                ->route('tenant.subscription')
                ->with('status', __('Switched back to Starter. Your data stays — extra properties become read-only.'));
        }

        return redirect()
            ->route('tenant.subscription')
            ->with('status', __('No change — you\'re already on that plan.'));
    }
}
