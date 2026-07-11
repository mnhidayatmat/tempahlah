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

    public function price(): float
    {
        return (float) config('homestay.paid_tier_price', 49.00);
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
                'amount' => $this->price(),
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
        return DB::transaction(function () use ($invoice, $bill) {
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
                    'plan' => Subscription::PLAN_PAID,
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
