<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Billing\SubscriptionBillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Drives the platform subscription lifecycle once a day:
 *
 *   A. Trial expiry  — a trialing subscription whose trial_ends_at has passed
 *                      becomes past_due and enters its grace window.
 *   B. Period lapse  — an active paid subscription whose current_period_end has
 *                      passed becomes past_due and enters its grace window.
 *   C. Grace expiry  — a past_due subscription whose grace_ends_at has passed is
 *                      downgraded to the free plan.
 *
 * Throughout the grace window the tenant KEEPS its paid features (see
 * Subscription::isPaid) — a lapsed payment must not instantly break the host's
 * live guest booking flow. Comped subscriptions are skipped entirely at every
 * step: they are never billed and never downgraded.
 *
 * Every write here goes through the model, so SubscriptionObserver purges the
 * tenant's cached Pennant flags and the feature change takes effect at once.
 *
 * Until platform billing lands (Phase 2) nothing in here can collect money, so
 * a subscription only ever moves *down*. Dunning notices hook in at B and C.
 */
class ProcessSubscriptionLifecycle extends Command
{
    protected $signature = 'subscriptions:process-lifecycle {--dry-run : Report what would change without writing}';

    protected $description = 'Expire trials, lapse unpaid periods into grace, and downgrade subscriptions whose grace has run out';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $now = Carbon::now();

        if ($this->dryRun) {
            $this->comment('Dry run — no writes.');
        }

        $trialsExpired = $this->expireTrials($now);
        $periodsLapsed = $this->lapseUnpaidPeriods($now);
        $downgraded = $this->downgradeExpiredGrace($now);

        $this->info(sprintf(
            'Subscription lifecycle: %d trial(s) expired, %d period(s) lapsed into grace, %d downgraded to free.',
            $trialsExpired,
            $periodsLapsed,
            $downgraded,
        ));

        return self::SUCCESS;
    }

    /**
     * A. The trial ran out and no payment was ever taken.
     *
     * These are card-free trials (Stripe-managed subs are filtered out and
     * drive their own state), so there is nothing to chase — the tenant drops
     * straight back to Free rather than into a past_due grace window. The
     * "continue to Pro?" nudge already went out before expiry (see
     * subscriptions:remind-trial-ending).
     */
    private function expireTrials(Carbon $now): int
    {
        $subscriptions = Subscription::query()
            ->whereNull('comped_at')
            ->whereNull('stripe_subscription_id') // Stripe drives its own subs via webhooks
            ->whereIn('plan', Subscription::PAID_PLANS)
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->transition($subscription, 'trial expired — downgraded to free', [
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
                'monthly_amount' => 0,
                'billing_method' => 'manual',
                'trial_ends_at' => null,
                'grace_ends_at' => null,
                'cancelled_at' => $now,
                'current_period_start' => $now,
                'current_period_end' => $now->copy()->addYear(),
            ]);

            // Void any open invoice so nobody is chased for a cycle they no
            // longer hold. Pure DB work — safe with billing unconfigured.
            if (! $this->dryRun) {
                app(SubscriptionBillingService::class)->voidOpenInvoices($subscription);
            }
        }

        return $subscriptions->count();
    }

    /**
     * B. A paid period ended without renewal.
     */
    private function lapseUnpaidPeriods(Carbon $now): int
    {
        $subscriptions = Subscription::query()
            ->whereNull('comped_at')
            ->whereNull('stripe_subscription_id') // Stripe drives its own subs via webhooks
            ->whereIn('plan', Subscription::PAID_PLANS)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', $now)
            ->get();

        foreach ($subscriptions as $subscription) {
            // Anchor the grace window to when the period actually ended, not to
            // now — otherwise a command that fails to run for a week silently
            // hands every lapsed tenant a week of extra grace.
            $graceEndsAt = $subscription->current_period_end
                ->copy()
                ->addDays(Subscription::graceDays());

            $this->transition($subscription, 'period lapsed', [
                'status' => Subscription::STATUS_PAST_DUE,
                'grace_ends_at' => $graceEndsAt,
            ]);
        }

        return $subscriptions->count();
    }

    /**
     * C. Grace ran out — drop to free. Paid features go dark here.
     */
    private function downgradeExpiredGrace(Carbon $now): int
    {
        $subscriptions = Subscription::query()
            ->whereNull('comped_at')
            ->whereNull('stripe_subscription_id') // Stripe drives its own subs via webhooks
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->transition($subscription, 'grace expired — downgraded to free', [
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
                'monthly_amount' => 0,
                'billing_method' => 'manual',
                'trial_ends_at' => null,
                'grace_ends_at' => null,
                'cancelled_at' => $now,
                'current_period_start' => $now,
                'current_period_end' => $now->copy()->addYear(),
            ]);

            // Nobody should be chased for a cycle they no longer hold. Pure DB
            // work — no gateway call — so this runs even with billing unconfigured.
            if (! $this->dryRun) {
                app(SubscriptionBillingService::class)->voidOpenInvoices($subscription);
            }
        }

        return $subscriptions->count();
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function transition(Subscription $subscription, string $reason, array $changes): void
    {
        $this->line(sprintf(
            '  tenant %d: %s (%s/%s)',
            $subscription->tenant_id,
            $reason,
            $subscription->plan,
            $subscription->status,
        ));

        if ($this->dryRun) {
            return;
        }

        $subscription->update($changes);

        Log::info('Subscription lifecycle transition', [
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'reason' => $reason,
            'changes' => array_map(
                fn ($value) => $value instanceof Carbon ? $value->toDateTimeString() : $value,
                $changes,
            ),
        ]);
    }
}
