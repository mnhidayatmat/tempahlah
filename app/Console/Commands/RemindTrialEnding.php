<?php

namespace App\Console\Commands;

use App\Mail\TrialEndingMail;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails hosts on a card-free signup trial a "want to keep Pro?" reminder a few
 * days before the trial lapses back to Free. Sent once per trial (guarded by
 * meta.trial_reminder_sent_at), so a re-run — or the trial being extended and
 * re-entering the window — never double-sends for the same trial cycle.
 *
 * Runs regardless of platform-billing configuration: the reminder is a plain
 * transactional email pointing at the in-app subscribe page, not a gateway bill.
 */
class RemindTrialEnding extends Command
{
    protected $signature = 'subscriptions:remind-trial-ending {--dry-run : Report who would be reminded without sending}';

    protected $description = 'Email trialing hosts a "continue with Pro?" reminder before their signup trial ends';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = Carbon::now();
        $reminderDays = (int) config('homestay.signup_trial_reminder_days', 3);
        $horizon = $now->copy()->addDays($reminderDays);

        $subscriptions = Subscription::query()
            ->with('tenant.owner')
            ->whereNull('comped_at')
            ->whereNull('stripe_subscription_id') // Stripe drives its own dunning
            ->whereIn('plan', Subscription::PAID_PLANS)
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', $now)       // not already expired
            ->where('trial_ends_at', '<=', $horizon)  // ends within the window
            ->get();

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            $meta = $subscription->meta ?? [];
            if (! empty($meta['trial_reminder_sent_at'])) {
                continue; // already reminded for this trial
            }

            $tenant = $subscription->tenant;
            if (! $tenant || $tenant->status !== 'active' || $tenant->suspended_at !== null) {
                continue;
            }

            // Tenant has no email column — resolve the owner's, else the
            // business email captured at signup. Suppressed (bounced) addresses
            // are dropped automatically by the global HaltMailToSuppressed listener.
            $email = $tenant->owner?->email ?: $tenant->business_email;
            if (! $email) {
                continue;
            }

            $daysLeft = max(1, (int) ceil($now->diffInDays($subscription->trial_ends_at, false)));
            $endsOn = $subscription->trial_ends_at->format('d M Y');

            $this->line("  tenant {$tenant->id}: trial ends {$endsOn} ({$daysLeft}d) → {$email}");

            if ($dryRun) {
                $sent++;

                continue;
            }

            try {
                Mail::to($email)->queue(new TrialEndingMail(
                    subscription: $subscription,
                    subscribeUrl: route('tenant.subscription'),
                    endsOn: $endsOn,
                    daysLeft: $daysLeft,
                ));

                $meta['trial_reminder_sent_at'] = $now->toDateTimeString();
                $subscription->update(['meta' => $meta]);

                $sent++;
            } catch (\Throwable $e) {
                // Don't stamp the guard on failure so tomorrow's run retries.
                Log::warning('Trial-ending reminder failed', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('Trial-ending reminders: %d %s.', $sent, $dryRun ? 'would be sent' : 'sent'));

        return self::SUCCESS;
    }
}
