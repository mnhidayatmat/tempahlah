<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
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

        try {
            $customerId = $this->stripe->ensureCustomer($tenant->loadMissing('owner'), $subscription);

            $session = $this->stripe->createCheckoutSession(
                $tenant,
                $customerId,
                successUrl: route('subscription.stripe.return').'?status=success',
                cancelUrl: route('subscription.stripe.return').'?status=cancel',
            );
        } catch (StripeException $e) {
            Log::error('Stripe checkout failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return redirect()->route('tenant.subscription')
                ->with('error', __('We could not start the subscription. Please try again in a moment.'));
        }

        return redirect()->away($session['url']);
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
