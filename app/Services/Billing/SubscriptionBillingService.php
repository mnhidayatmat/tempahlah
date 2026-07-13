<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns the money side of a subscription: issue the bill for a cycle, hand the
 * tenant a pay link, and — when Billplz says it's paid — advance the period.
 *
 * State transitions live here and nowhere else, so the checkout page, the
 * webhook, the payment-return page and the daily command can't drift apart.
 * Settlement is idempotent: the webhook and the return page routinely race.
 */
class SubscriptionBillingService
{
    public function __construct(
        protected PlatformBillplz $billplz,
    ) {}

    public function configured(): bool
    {
        return $this->billplz->configured();
    }

    /**
     * Whether Billplz card auto-renew (Tokenization) is live. Passthrough so
     * callers holding the service (the subscription page, checkout) don't need a
     * separate PlatformBillplz dependency — mirrors configured().
     */
    public function tokenizationEnabled(): bool
    {
        return $this->billplz->tokenizationEnabled();
    }

    public function price(?Subscription $subscription = null): float
    {
        $plan = $subscription && in_array($subscription->planKey(), Subscription::PAID_PLANS, true)
            ? $subscription->planKey()
            : Subscription::PLAN_PRO;

        return \App\Support\Billing\Plans::price($plan);
    }

    public function invoiceDueDays(): int
    {
        return (int) config('homestay.platform_billing.invoice_due_days', 14);
    }

    public function issueLeadDays(): int
    {
        return (int) config('homestay.platform_billing.issue_lead_days', 3);
    }

    /**
     * The invoice a tenant currently owes, if any. Reused rather than re-minted,
     * so a tenant who reloads checkout doesn't accumulate bills.
     */
    public function openInvoiceFor(Subscription $subscription): ?SubscriptionInvoice
    {
        return SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', [SubscriptionInvoice::STATUS_PENDING, SubscriptionInvoice::STATUS_FAILED])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get-or-create the invoice for the cycle beginning at $periodStart.
     *
     * Comped subscriptions are never billed. The (subscription_id, period_start)
     * unique index is the real guard against a double-mint under concurrency —
     * catch the violation and return the winner rather than failing the request.
     */
    public function issueInvoice(Subscription $subscription, CarbonInterface $periodStart): SubscriptionInvoice
    {
        if ($subscription->isComped()) {
            throw new PlatformBillingException('Refusing to bill a comped subscription.');
        }

        $start = Carbon::parse($periodStart)->startOfDay();
        $end = $start->copy()->addMonthNoOverflow();

        $existing = SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('period_start', $start)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => SubscriptionInvoice::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'number' => SubscriptionInvoice::nextNumber(),
                'status' => SubscriptionInvoice::STATUS_PENDING,
                'amount' => $this->price($subscription),
                'currency' => 'MYR',
                'period_start' => $start,
                'period_end' => $end,
                'due_at' => now()->addDays($this->invoiceDueDays()),
            ]));
        } catch (QueryException $e) {
            // Unique violation on (subscription_id, period_start) or number —
            // another request won the race. Return whatever it created.
            $winner = SubscriptionInvoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereDate('period_start', $start)
                ->first();

            if ($winner) {
                return $winner;
            }

            throw $e;
        }
    }

    /**
     * Ensure the invoice has a live Billplz bill and return its pay URL.
     * Reuses the existing bill so the tenant always sees the same link.
     */
    public function payUrlFor(SubscriptionInvoice $invoice): string
    {
        if ($invoice->isPaid()) {
            throw new PlatformBillingException("Invoice {$invoice->number} is already paid.");
        }

        if (filled($invoice->payment_url) && filled($invoice->gateway_bill_id)) {
            return $invoice->payment_url;
        }

        $tenant = $invoice->tenant()->with('owner')->firstOrFail();

        $bill = $this->billplz->createBill(
            $invoice,
            $tenant,
            redirectUrl: route('subscription.billing.return'),
            callbackUrl: route('webhooks.subscription-billing'),
        );

        $invoice->update([
            'status' => SubscriptionInvoice::STATUS_PENDING,
            'gateway_provider' => 'billplz',
            'gateway_bill_id' => $bill['bill_id'],
            'payment_url' => $bill['payment_url'],
        ]);

        return $bill['payment_url'];
    }

    /**
     * Mark the invoice paid and advance the subscription into the cycle it
     * bought. Idempotent and race-safe — the webhook and the return page both
     * call this, often within the same second.
     *
     * Returns true only on the transition, so callers fire side effects once.
     */
    public function settle(SubscriptionInvoice $invoice, array $bill = []): bool
    {
        $settled = DB::transaction(function () use ($invoice, $bill) {
            /** @var SubscriptionInvoice $fresh */
            $fresh = SubscriptionInvoice::query()->lockForUpdate()->find($invoice->id);

            if (! $fresh || $fresh->isPaid()) {
                return false;
            }

            $fresh->update([
                'status' => SubscriptionInvoice::STATUS_PAID,
                'paid_at' => now(),
                'meta' => array_merge($fresh->meta ?? [], $bill ? ['bill' => $bill] : []),
            ]);

            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()->lockForUpdate()->find($fresh->subscription_id);

            if (! $subscription) {
                Log::warning('Subscription invoice settled with no subscription', ['invoice' => $fresh->number]);

                return true;
            }

            // A comped account should never have been billed; if one somehow was,
            // take the money but leave the comp alone.
            if (! $subscription->isComped()) {
                $subscription->update([
                    // Keep the tier the tenant is on — an Ultra tenant settling a
                    // pay-link invoice must not be demoted to Pro by the settle.
                    'plan' => in_array($subscription->planKey(), Subscription::PAID_PLANS, true)
                        ? $subscription->planKey()
                        : Subscription::PLAN_PRO,
                    'status' => Subscription::STATUS_ACTIVE,
                    'billing_method' => 'billplz',
                    'monthly_amount' => $fresh->amount,
                    'currency' => $fresh->currency,
                    'current_period_start' => $fresh->period_start,
                    'current_period_end' => $fresh->period_end,
                    'grace_ends_at' => null,
                    'cancelled_at' => null,
                    // A paid subscription is no longer trialing.
                    'trial_ends_at' => null,
                ]);
            }

            Log::info('Subscription invoice settled', [
                'invoice' => $fresh->number,
                'tenant_id' => $fresh->tenant_id,
                'period_end' => $fresh->period_end->toDateString(),
            ]);

            return true;
        });

        // Affiliate commission on the real money that just arrived. settle()
        // returns true exactly once per invoice, and the service is idempotent
        // on its source key — so a webhook/return-page race can't double-pay.
        // Best-effort: a commission failure must never break a settlement.
        if ($settled) {
            try {
                app(\App\Services\Affiliate\AffiliateCommissionService::class)->recordSubscriptionPayment(
                    (int) $invoice->tenant_id,
                    (float) $invoice->amount,
                    'subinv:'.$invoice->id,
                    'Subscription invoice '.$invoice->number,
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $settled;
    }

    /**
     * Persist a tokenized card on the subscription after a checksum-VERIFIED
     * enrollment callback. Never call this on an unverified callback — the token
     * is the thing that charges money.
     *
     * @param  array{id?: string, token?: string, card_number?: string, provider?: string, status?: string}  $card
     */
    public function storeCard(Subscription $subscription, array $card): void
    {
        $subscription->update([
            'card_id' => (string) ($card['id'] ?? ''),
            'card_token' => (string) ($card['token'] ?? ''),
            // Billplz sends the masked PAN; keep only the last 4 for display.
            'card_last4' => substr(preg_replace('/\D/', '', (string) ($card['card_number'] ?? '')) ?: '', -4) ?: null,
            'card_brand' => $card['provider'] ?? null,
            'card_status' => (string) ($card['status'] ?? Subscription::CARD_ACTIVE),
            'auto_renew' => true,
        ]);
    }

    /**
     * Charge an invoice against the subscription's saved card, then settle.
     *
     * Reuses the whole existing money path: payUrlFor() mints/reuses the bill,
     * settle() is the sole owner of advancing the period. We charge, then
     * reconcile server-side (getBill) rather than trusting the synchronous
     * charge body, so a "success" that didn't actually clear can't advance a
     * period. A declined charge returns false and leaves the invoice open — the
     * daily command's dunning path then emails the pay-link as a fallback.
     */
    public function chargeSavedCard(SubscriptionInvoice $invoice, Subscription $subscription): bool
    {
        if ($invoice->isPaid()) {
            return false;
        }

        if (! $subscription->hasChargeableCard()) {
            throw new PlatformBillingException('Subscription has no chargeable card on file.');
        }

        // Ensure a live bill exists for this invoice (mints or reuses one).
        $this->payUrlFor($invoice);
        $invoice->refresh();

        $charge = $this->billplz->chargeCard(
            (string) $invoice->gateway_bill_id,
            (string) $subscription->card_id,
            (string) $subscription->card_token,
        );

        if (! $charge['success']) {
            Log::warning('Subscription card charge declined', [
                'invoice' => $invoice->number,
                'tenant_id' => $invoice->tenant_id,
                'status' => $charge['status'],
                'http' => $charge['http_status'],
            ]);

            $invoice->update(['status' => SubscriptionInvoice::STATUS_FAILED]);

            return false;
        }

        // Trust the gateway, not the charge body — confirm the bill really paid.
        return $this->reconcile($invoice);
    }

    /**
     * Reconcile straight from Billplz. The trustworthy path — used by the return
     * page, and by any callback whose signature we could not verify.
     */
    public function reconcile(SubscriptionInvoice $invoice): bool
    {
        if ($invoice->isPaid()) {
            return false;
        }

        if (blank($invoice->gateway_bill_id)) {
            return false;
        }

        $result = $this->billplz->getBill($invoice->gateway_bill_id);

        if (! $result['paid']) {
            return false;
        }

        return $this->settle($invoice, $result['bill']);
    }

    /**
     * Apply a Stripe subscription object to the local subscription — the single
     * owner of Stripe→local state, so the webhook events can't drift.
     *
     * Resolves the local row by stripe_subscription_id, then by the
     * metadata.tenant_id we set at checkout (the first event, before we've stored
     * the sub id). Idempotent, comped-safe.
     *
     * Stripe status map:
     *   active | trialing            -> paid + active (auto-charge is on)
     *   past_due | unpaid            -> past_due + grace window (isPaid stays true
     *                                   inside grace so features don't cut off
     *                                   mid-retry)
     *   canceled | incomplete_expired-> downgrade to free
     *   incomplete                   -> ignore (checkout not completed / unpaid)
     *
     * @param  array  $stripeSub  A Stripe Subscription object.
     */
    public function applyStripeSubscription(array $stripeSub): bool
    {
        $stripeId = (string) ($stripeSub['id'] ?? '');
        if ($stripeId === '') {
            return false;
        }

        $status = (string) ($stripeSub['status'] ?? '');
        $tenantId = $stripeSub['metadata']['tenant_id'] ?? null;

        return DB::transaction(function () use ($stripeSub, $stripeId, $status, $tenantId) {
            $query = Subscription::query()->lockForUpdate();
            $subscription = (clone $query)->where('stripe_subscription_id', $stripeId)->first();

            if (! $subscription && $tenantId) {
                $subscription = (clone $query)->where('tenant_id', $tenantId)->first();
            }

            if (! $subscription) {
                Log::warning('Stripe event for unknown subscription', ['stripe_sub' => $stripeId, 'tenant_id' => $tenantId]);

                return false;
            }

            // Checkout not completed yet — don't grant anything, but do remember
            // the ids so the next event resolves directly.
            if ($status === 'incomplete' || $status === '') {
                $subscription->update([
                    'stripe_subscription_id' => $stripeId,
                    'stripe_customer_id' => $subscription->stripe_customer_id ?: ($stripeSub['customer'] ?? null),
                ]);

                return false;
            }

            $updates = [
                'stripe_subscription_id' => $stripeId,
                'stripe_customer_id' => $subscription->stripe_customer_id ?: ($stripeSub['customer'] ?? null),
                'stripe_price_id' => $stripeSub['items']['data'][0]['price']['id'] ?? $subscription->stripe_price_id,
                'billing_method' => 'stripe',
            ];

            // Which local tier this Stripe subscription buys — the price id is
            // the source of truth (pro RM49 price vs ultra RM89 price).
            $paidPlan = app(StripeBilling::class)->planForPriceId($updates['stripe_price_id']);
            $paidAmount = \App\Support\Billing\Plans::price($paidPlan);

            // current_period_end moved from the Subscription object to its line
            // items in Stripe API 2025-03-31+ (this account defaults to a newer
            // version). Read whichever is present so renewal/trial dates are
            // correct regardless of the API version the webhook/retrieve returns.
            $periodEndTs = $stripeSub['current_period_end']
                ?? ($stripeSub['items']['data'][0]['current_period_end'] ?? null);
            $periodEnd = $periodEndTs
                ? Carbon::createFromTimestamp((int) $periodEndTs)
                : null;

            // Trial end is still a subscription-level field; prefer it for the
            // trial branch (falls back to the period end when not trialing).
            $trialEnd = isset($stripeSub['trial_end'])
                ? Carbon::createFromTimestamp((int) $stripeSub['trial_end'])
                : $periodEnd;

            // Whether the tenant has scheduled a cancel-at-period-end. Surfaced on
            // the subscription page so they see "cancels on <date>" + a Resume.
            $meta = array_merge($subscription->meta ?? [], [
                'stripe_cancel_at_period_end' => (bool) ($stripeSub['cancel_at_period_end'] ?? false),
            ]);

            if ($status === 'trialing') {
                // A live Stripe trial: keep it as a trial locally so the UI shows
                // the countdown, and stamp trial_used_at so it can't be repeated.
                $updates = array_merge($updates, [
                    'plan' => $paidPlan,
                    'monthly_amount' => $paidAmount,
                    'status' => Subscription::STATUS_TRIALING,
                    'trial_ends_at' => $trialEnd ?? $subscription->trial_ends_at,
                    'current_period_end' => $periodEnd ?? $subscription->current_period_end,
                    'grace_ends_at' => null,
                    'cancelled_at' => null,
                    'trial_used_at' => $subscription->trial_used_at ?? now(),
                    'meta' => $meta,
                ]);
            } elseif ($status === 'active') {
                $updates = array_merge($updates, [
                    'plan' => $paidPlan,
                    'monthly_amount' => $paidAmount,
                    'status' => Subscription::STATUS_ACTIVE,
                    'current_period_end' => $periodEnd ?? $subscription->current_period_end,
                    'grace_ends_at' => null,
                    'cancelled_at' => null,
                    'trial_ends_at' => null,
                    'trial_used_at' => $subscription->trial_used_at ?? now(),
                    'meta' => $meta,
                ]);
            } elseif (in_array($status, ['past_due', 'unpaid'], true)) {
                $updates = array_merge($updates, [
                    'plan' => $paidPlan,
                    'status' => Subscription::STATUS_PAST_DUE,
                    // Keep features alive while Stripe retries the card.
                    'grace_ends_at' => $subscription->grace_ends_at
                        ?? now()->addDays(Subscription::graceDays()),
                ]);
            } elseif (in_array($status, ['canceled', 'incomplete_expired'], true)) {
                $updates = array_merge($updates, [
                    'plan' => Subscription::PLAN_FREE,
                    'status' => Subscription::STATUS_ACTIVE,
                    'monthly_amount' => 0,
                    'cancelled_at' => now(),
                    'grace_ends_at' => null,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addYear(),
                    // The subscription is gone at Stripe — clear it so this row is
                    // no longer Stripe-managed and can re-subscribe cleanly.
                    'stripe_subscription_id' => null,
                    // Cancellation carried out — drop the "cancels on <date>" flag.
                    'meta' => array_merge($subscription->meta ?? [], ['stripe_cancel_at_period_end' => false]),
                ]);
            }

            // A comped account is an admin grant: record the Stripe ids but never
            // flip the plan/status/comp.
            if ($subscription->isComped()) {
                $subscription->update([
                    'stripe_subscription_id' => $status === 'canceled' || $status === 'incomplete_expired' ? null : $stripeId,
                    'stripe_customer_id' => $subscription->stripe_customer_id ?: ($stripeSub['customer'] ?? null),
                ]);

                return true;
            }

            $subscription->update($updates);

            Log::info('Stripe subscription applied', [
                'tenant_id' => $subscription->tenant_id,
                'stripe_status' => $status,
                'local_status' => $subscription->status,
                'plan' => $subscription->plan,
            ]);

            return true;
        });
    }

    /**
     * Called when a subscription drops to free — an unpaid bill for a cycle the
     * tenant no longer has must not sit there being chased.
     */
    public function voidOpenInvoices(Subscription $subscription): int
    {
        return SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', [SubscriptionInvoice::STATUS_PENDING, SubscriptionInvoice::STATUS_FAILED])
            ->update(['status' => SubscriptionInvoice::STATUS_VOID]);
    }

    /**
     * Where the next cycle should begin for this subscription: the day the
     * current one runs out, never in the past (a tenant returning after a lapse
     * buys a month from today, not a month that already elapsed).
     */
    public function nextPeriodStart(Subscription $subscription): Carbon
    {
        $anchor = $subscription->status === Subscription::STATUS_TRIALING
            ? $subscription->trial_ends_at
            : $subscription->current_period_end;

        $start = $anchor ? Carbon::parse($anchor)->startOfDay() : now()->startOfDay();

        return $start->isPast() ? now()->startOfDay() : $start;
    }

    public function tenantFor(SubscriptionInvoice $invoice): ?Tenant
    {
        return Tenant::find($invoice->tenant_id);
    }
}
