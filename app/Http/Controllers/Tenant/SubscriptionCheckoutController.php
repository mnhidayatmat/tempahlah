<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Services\Billing\PlatformBillingException;
use App\Services\Billing\SubscriptionBillingService;
use App\Services\Payments\Billplz\BillplzException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paying Tempahlah the RM 49/mo subscription.
 *
 * Note the direction of the money: this bills the TENANT into Tempahlah's own
 * Billplz account. The tenant's own gateway (used to charge their guests) is a
 * completely separate credential set and is never touched here.
 */
class SubscriptionCheckoutController extends Controller
{
    public function __construct(
        protected SubscriptionBillingService $billing,
    ) {}

    /**
     * POST /dashboard/subscription/checkout
     * Mint (or reuse) the bill for the next cycle and send the tenant to Billplz.
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

        if (! $this->billing->configured()) {
            return redirect()->route('tenant.subscription')
                ->with('error', __('Online subscription payment is not available yet. Please contact us to upgrade.'));
        }

        try {
            // Reuse whatever the tenant already owes; otherwise open the next cycle.
            $invoice = $this->billing->openInvoiceFor($subscription)
                ?? $this->billing->issueInvoice($subscription, $this->billing->nextPeriodStart($subscription));

            $payUrl = $this->billing->payUrlFor($invoice);
        } catch (BillplzException|PlatformBillingException $e) {
            Log::error('Subscription checkout failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('tenant.subscription')
                ->with('error', __('We could not start the payment. Please try again in a moment.'));
        }

        return redirect()->away($payUrl);
    }

    /**
     * GET /subscription/billing/return
     *
     * Where Billplz sends the tenant back. Purely a convenience: the callback is
     * the authoritative path, but it can be late or lost, so reconcile here too
     * rather than show a paying tenant a "still unpaid" page.
     *
     * Deliberately does NOT trust the redirect's `paid` flag — it re-asks Billplz.
     */
    public function paymentReturn(Request $request)
    {
        $billplz = $request->query('billplz', []);
        $billId = is_array($billplz) ? ($billplz['id'] ?? null) : null;

        if (! $billId) {
            return redirect()->route('tenant.subscription')
                ->with('error', __('We could not read the payment result. If you paid, it will confirm shortly.'));
        }

        $invoice = SubscriptionInvoice::query()->where('gateway_bill_id', $billId)->first();

        if (! $invoice) {
            return redirect()->route('tenant.subscription')
                ->with('error', __('We could not match that payment. If you paid, it will confirm shortly.'));
        }

        try {
            $this->billing->reconcile($invoice);
        } catch (\Throwable $e) {
            // A failed lookup must not show an error to a tenant who did pay —
            // the callback will still settle it.
            report($e);
        }

        $invoice->refresh();

        return redirect()->route('tenant.subscription')->with(
            $invoice->isPaid() ? 'status' : 'error',
            $invoice->isPaid()
                ? __('Payment received — you\'re on Pro until :date.', ['date' => $invoice->period_end->format('d M Y')])
                : __('We haven\'t seen your payment yet. If you completed it, it will confirm within a few minutes.'),
        );
    }
}
