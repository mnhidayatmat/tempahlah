<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionInvoiceMail;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Services\Billing\SubscriptionBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Issues and chases the RM 49/mo subscription bill. Runs daily, before
 * `subscriptions:process-lifecycle` gets a chance to downgrade anyone.
 *
 *   A. Issue  — a trial about to end, or a paid period about to end, gets its
 *               next bill minted and emailed `issue_lead_days` in advance.
 *   B. Chase  — a past_due subscription inside its grace window gets one
 *               reminder a day, with the grace deadline spelled out.
 *   C. Tidy   — a subscription that has fallen back to free has its open
 *               invoices voided; nobody should be chased for a cycle they no
 *               longer hold.
 *
 * Comped subscriptions are skipped everywhere. When platform billing has no
 * credentials the whole command is a no-op, so deploying it changes nothing
 * until Tempahlah's own Billplz account is configured.
 */
class ProcessSubscriptionBilling extends Command
{
    protected $signature = 'subscriptions:bill-cycle {--dry-run : Report what would change without writing or sending}';

    protected $description = 'Issue the next RM49 subscription bill, chase unpaid ones, and void invoices for downgraded tenants';

    private bool $dryRun = false;

    public function handle(SubscriptionBillingService $billing): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if (! $billing->configured()) {
            $this->warn('Platform billing is not configured (BILLPLZ_API_KEY / BILLPLZ_COLLECTION_ID) — nothing to do.');

            return self::SUCCESS;
        }

        if ($this->dryRun) {
            $this->comment('Dry run — no writes, no bills, no email.');
        }

        $issued = $this->issueDueBills($billing);
        $chased = $this->chasePastDue($billing);
        $voided = $this->voidInvoicesForDowngraded($billing);

        $this->info(sprintf(
            'Subscription billing: %d bill(s) issued, %d reminder(s) sent, %d invoice(s) voided.',
            $issued,
            $chased,
            $voided,
        ));

        return self::SUCCESS;
    }

    /**
     * A. Mint + email the next cycle's bill, `issue_lead_days` before the
     *    current trial or paid period runs out.
     */
    private function issueDueBills(SubscriptionBillingService $billing): int
    {
        $horizon = now()->addDays($billing->issueLeadDays());
        $count = 0;

        $subscriptions = Subscription::query()
            ->whereNull('comped_at')
            ->whereNull('stripe_subscription_id') // Stripe drives its own subs via webhooks
            ->whereIn('plan', Subscription::PAID_PLANS)
            ->where(function ($q) use ($horizon) {
                $q->where(function ($q) use ($horizon) {
                    $q->where('status', Subscription::STATUS_TRIALING)
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<=', $horizon);
                })->orWhere(function ($q) use ($horizon) {
                    $q->where('status', Subscription::STATUS_ACTIVE)
                        ->whereNotNull('current_period_end')
                        ->where('current_period_end', '<=', $horizon);
                });
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            // Already owes something for the upcoming cycle — don't mint a second.
            if ($billing->openInvoiceFor($subscription)) {
                continue;
            }

            $periodStart = $billing->nextPeriodStart($subscription);
            $autoCharge = $subscription->hasChargeableCard();

            $this->line("  tenant {$subscription->tenant_id}: "
                .($autoCharge ? 'auto-charge card' : 'issue bill')
                ." for cycle starting {$periodStart->toDateString()}");

            if ($this->dryRun) {
                $count++;

                continue;
            }

            try {
                $invoice = $billing->issueInvoice($subscription, $periodStart);

                // Card on file → charge it silently. A decline falls through to
                // the pay-link email below (chargeSavedCard leaves the invoice
                // open), so no one is ever left uncharged AND un-notified.
                if ($autoCharge && $billing->chargeSavedCard($invoice, $subscription)) {
                    $this->line("    charged card for {$invoice->number}");
                    $count++;

                    continue;
                }

                $payUrl = $billing->payUrlFor($invoice);
                $this->email($invoice, $payUrl, dunning: false);
                $count++;
            } catch (\Throwable $e) {
                report($e);
                Log::error('Failed to issue subscription bill', [
                    'tenant_id' => $subscription->tenant_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * B. One reminder a day while the tenant is past_due but still inside grace.
     *    Once grace lapses, process-lifecycle downgrades them and step C tidies up.
     */
    private function chasePastDue(SubscriptionBillingService $billing): int
    {
        $count = 0;

        $subscriptions = Subscription::query()
            ->whereNull('comped_at')
            ->whereNull('stripe_subscription_id') // Stripe drives its own subs via webhooks
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '>', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $invoice = $billing->openInvoiceFor($subscription);

            if (! $invoice) {
                // Past due with nothing to pay — mint the bill they owe.
                $this->line("  tenant {$subscription->tenant_id}: past_due with no open invoice, issuing");

                if ($this->dryRun) {
                    continue;
                }

                try {
                    $invoice = $billing->issueInvoice($subscription, $billing->nextPeriodStart($subscription));
                } catch (\Throwable $e) {
                    report($e);

                    continue;
                }
            }

            // At most one reminder per calendar day.
            if ($invoice->last_reminder_at && $invoice->last_reminder_at->isToday()) {
                continue;
            }

            $this->line("  tenant {$subscription->tenant_id}: reminder for {$invoice->number} (grace ends {$subscription->grace_ends_at->toDateString()})");

            if ($this->dryRun) {
                $count++;

                continue;
            }

            try {
                $payUrl = $billing->payUrlFor($invoice);
                $this->email($invoice, $payUrl, dunning: true, graceEndsOn: $subscription->grace_ends_at->format('d M Y'));

                $invoice->update([
                    'reminders_sent' => $invoice->reminders_sent + 1,
                    'last_reminder_at' => now(),
                ]);
                $count++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $count;
    }

    /**
     * C. A tenant back on the free plan must not keep receiving bills.
     */
    private function voidInvoicesForDowngraded(SubscriptionBillingService $billing): int
    {
        $count = 0;

        $openByFreeTenants = SubscriptionInvoice::query()
            ->whereIn('status', [SubscriptionInvoice::STATUS_PENDING, SubscriptionInvoice::STATUS_FAILED])
            ->whereHas('subscription', fn ($q) => $q->where('plan', Subscription::PLAN_FREE))
            ->get();

        foreach ($openByFreeTenants as $invoice) {
            $this->line("  tenant {$invoice->tenant_id}: voiding {$invoice->number} (back on free plan)");

            if ($this->dryRun) {
                $count++;

                continue;
            }

            $invoice->update(['status' => SubscriptionInvoice::STATUS_VOID]);
            $count++;
        }

        return $count;
    }

    private function email(SubscriptionInvoice $invoice, string $payUrl, bool $dunning, ?string $graceEndsOn = null): void
    {
        $tenant = $invoice->tenant()->with('owner')->first();
        $to = $tenant?->owner?->email ?: $tenant?->email;

        if (! $to) {
            Log::warning('Subscription invoice has no billing email', ['invoice' => $invoice->number]);

            return;
        }

        Mail::to($to)->queue(new SubscriptionInvoiceMail($invoice, $payUrl, $dunning, $graceEndsOn));
    }
}
