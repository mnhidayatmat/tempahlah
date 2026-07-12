<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Billing\StripeBilling;
use App\Services\Billing\StripeException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe recurring subscription checkout + management.
 *
 * Bills the TENANT into Tempahlah's OWN Stripe account. The tenant's own
 * guest-payment gateways are never touched here. The webhook
 * (StripeWebhookController) is the authoritative source of subscription state;
 * these actions just start the hosted flows.
 */
class StripeCheckoutController extends Controller
{
    public function __construct(
        protected StripeBilling $stripe,
    ) {}

    /**
     * POST /dashboard/subscription/stripe/checkout
     * Ensure a Stripe Customer, open a subscription Checkout Session, redirect.
     */
    public function checkout(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $subscription = $tenant->subscription;
        abort_unless($subscription, 404, 'No subscription record');

        if ($subscription->isComped()) {
            return redirect()->route('tenant.subscription')
                ->with('status', __('Your account has complimentary Pro access — there is nothing to pay.'));
        }

        if (! $this->stripe->enabled()) {
            return redirect()->route('tenant.subscription')
                ->with('error', __('Card subscriptions are not available yet.'));
        }

        // A tenant who has never trialed gets the 7-day card-required trial; a
        // returning one who already used it subscribes and is charged immediately.
        // A fresh tenant can also opt to skip the trial and pay today ("buy now")
        // via skip_trial — e.g. they'd rather start their paid month right away.
        $skipTrial = $request->boolean('skip_trial');
        $trialDays = ($subscription->hasUsedTrial() || $skipTrial) ? null : Subscription::trialDays();

        try {
            $customerId = $this->stripe->ensureCustomer($tenant->loadMissing('owner'), $subscription);

            $session = $this->stripe->createCheckoutSession(
                $tenant,
                $customerId,
                successUrl: route('subscription.stripe.return').'?status=success',
                cancelUrl: route('subscription.stripe.return').'?status=cancel',
                trialDays: $trialDays,
            );
        } catch (StripeException $e) {
            Log::error('Stripe checkout failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return redirect()->route('tenant.subscription')
                ->with('error', __('We could not start the subscription. Please try again in a moment.'));
        }

        return redirect()->away($session['url']);
    }

    /**
     * POST /dashboard/subscription/stripe/cancel
     * Schedule cancellation at period end. During the trial this stops the
     * upcoming first charge; Pro stays on until the trial / paid period ends.
     */
    public function cancel(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $subscription = $tenant->subscription;
        abort_unless($subscription, 404, 'No subscription record');

        if ($subscription->isComped()) {
            return redirect()->route('tenant.subscription')
                ->with('status', __('Your account has complimentary Pro access — there is nothing to cancel.'));
        }

        if (! $this->stripe->enabled() || blank($subscription->stripe_subscription_id)) {
            return redirect()->route('tenant.subscription')
                ->with('error', __('No active card subscription to cancel.'));
        }

        try {
            $this->stripe->cancelSubscription((string) $subscription->stripe_subscription_id);
        } catch (StripeException $e) {
            Log::error('Stripe cancel failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return redirect()->route('tenant.subscription')
                ->with('error', __('We could not cancel the subscription. Please try again in a moment.'));
        }

        // Optimistic local flag so the page reflects it immediately; the webhook
        // confirms the same state moments later.
        $subscription->update([
            'meta' => array_merge($subscription->meta ?? [], ['stripe_cancel_at_period_end' => true]),
        ]);

        $endsOn = ($subscription->onTrial() ? $subscription->trial_ends_at : $subscription->current_period_end)?->format('d M Y');

        return redirect()->route('tenant.subscription')->with('status', $endsOn
            ? __('Subscription cancelled. You keep Pro until :date and won\'t be charged again.', ['date' => $endsOn])
            : __('Subscription cancelled. You won\'t be charged again.'));
    }

    /**
     * POST /dashboard/subscription/stripe/resume
     * Undo a scheduled cancellation while the subscription is still live.
     */
    public function resume(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $subscription = $tenant->subscription;
        abort_unless($subscription, 404, 'No subscription record');

        if (! $this->stripe->enabled() || blank($subscription->stripe_subscription_id)) {
            return redirect()->route('tenant.subscription')
                ->with('error', __('No subscription to resume.'));
        }

        try {
            $this->stripe->resumeSubscription((string) $subscription->stripe_subscription_id);
        } catch (StripeException $e) {
            Log::error('Stripe resume failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return redirect()->route('tenant.subscription')
                ->with('error', __('We could not resume the subscription. Please try again in a moment.'));
        }

        $subscription->update([
            'meta' => array_merge($subscription->meta ?? [], ['stripe_cancel_at_period_end' => false]),
        ]);

        return redirect()->route('tenant.subscription')
            ->with('status', __('Subscription resumed — it will keep auto-renewing.'));
    }

    /**
     * POST /dashboard/subscription/stripe/portal
     * Send the tenant to the Stripe Customer Portal (update card / cancel).
     */
    public function portal(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);

        $subscription = $tenant->subscription;
        abort_unless($subscription, 404);

        if (! $this->stripe->enabled() || blank($subscription->stripe_customer_id)) {
            return redirect()->route('tenant.subscription')
                ->with('error', __('No Stripe subscription to manage yet.'));
        }

        try {
            $portal = $this->stripe->createPortalSession(
                (string) $subscription->stripe_customer_id,
                route('tenant.subscription'),
            );
        } catch (StripeException $e) {
            Log::error('Stripe portal failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return redirect()->route('tenant.subscription')
                ->with('error', __('We could not open subscription management. Please try again.'));
        }

        return redirect()->away($portal['url']);
    }

    /**
     * GET /subscription/stripe/return
     * Where Stripe Checkout sends the tenant back. Informational only — the
     * webhook does the real work, so we just flash a friendly message.
     */
    public function return(Request $request)
    {
        $cancelled = $request->query('status') === 'cancel';

        return redirect()->route('tenant.subscription')->with(
            $cancelled ? 'error' : 'status',
            $cancelled
                ? __('Subscription setup cancelled. You can try again any time.')
                : __('Thanks! Your subscription is being activated — this can take a few seconds.'),
        );
    }
}
