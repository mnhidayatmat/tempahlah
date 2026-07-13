<?php

namespace App\Console\Commands;

use App\Mail\OnboardingEmailMail;
use App\Models\EmailSuppression;
use App\Models\MarketingCampaign;
use App\Models\OnboardingEmail;
use App\Models\OnboardingEmailSend;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Daily onboarding drip: for each active tenant, find the earliest enabled
 * series step whose day_offset has been reached, and send it — at most ONE
 * step per tenant per run, so a tenant is never dogpiled.
 *
 * The very first step (day_offset = 0) is normally sent IMMEDIATELY at
 * signup by SendOnboardingWelcomeEmail (dispatched from
 * CreateTenantAndOwner), not by this daily batch — so a new host doesn't
 * wait until the next scheduled run for their welcome email. This command
 * still owns day 0 as a fallback (nothing recorded yet for that tenant if
 * the immediate send failed) and owns every subsequent step regardless.
 *

 * Guards:
 *  - a unique (step, tenant) send row = never sent twice
 *  - the step must sit inside [day_offset, day_offset + SEND_WINDOW_DAYS] of
 *    the tenant's signup age → tenants who signed up long before this feature
 *    existed are already past every window and get nothing
 *  - marketing opt-out + bounce/complaint suppression are honoured
 *  - skip_if_paid steps (the Pro pitches) are skipped for paid tenants
 */
class SendOnboardingEmails extends Command
{
    protected $signature = 'marketing:send-onboarding {--dry-run : Report what would be sent without sending}';

    protected $description = 'Send the next due onboarding-series email to each new host';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $steps = OnboardingEmail::query()->where('enabled', true)->orderBy('day_offset')->get();
        if ($steps->isEmpty()) {
            $this->info('No enabled onboarding steps.');

            return self::SUCCESS;
        }

        $tenants = Tenant::query()
            ->with(['subscription', 'owner:id,name,email'])
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereNull('marketing_opt_out_at')
            // The oldest window that can still fire bounds the signup range —
            // anything older can never match a step window again.
            ->where('created_at', '>=', now()->subDays($steps->max('day_offset') + OnboardingEmail::SEND_WINDOW_DAYS + 1))
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            $ageDays = (int) $tenant->created_at->startOfDay()->diffInDays(now()->startOfDay());

            // Hard "one email per tenant per calendar day" — so re-running the
            // command (manual run after the scheduled one, deploy hiccup, etc.)
            // never advances a tenant through the drip faster than daily.
            $touchedToday = OnboardingEmailSend::where('tenant_id', $tenant->id)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
            if ($touchedToday) {
                continue;
            }

            $alreadySent = OnboardingEmailSend::where('tenant_id', $tenant->id)->pluck('onboarding_email_id');

            foreach ($steps as $step) {
                if ($alreadySent->contains($step->id)) {
                    continue;
                }
                if ($ageDays < $step->day_offset || $ageDays > $step->day_offset + OnboardingEmail::SEND_WINDOW_DAYS) {
                    continue;
                }

                $email = MarketingCampaign::emailFor($tenant);

                if (! $email) {
                    break; // unreachable tenant — try again next run in case an email appears
                }

                if ($step->skip_if_paid && $tenant->isPaid()) {
                    $this->record($step, $tenant, $email, OnboardingEmailSend::STATUS_SKIPPED, 'already paid', $dry);
                    $skipped++;

                    continue; // a later non-pitch step may still apply
                }

                if (EmailSuppression::isSuppressed($email)) {
                    $this->record($step, $tenant, $email, OnboardingEmailSend::STATUS_SKIPPED, 'suppressed (bounce/complaint)', $dry);
                    $skipped++;

                    break; // suppressed mailbox — nothing else will deliver either
                }

                if ($dry) {
                    $this->line("  would send step {$step->step_no} ({$step->subject}) → {$email} (tenant {$tenant->id}, day {$ageDays})");
                    $sent++;

                    break;
                }

                try {
                    Mail::to($email)->send(new OnboardingEmailMail(
                        step: $step,
                        tenant: $tenant,
                        recipientName: $tenant->owner?->name ?: $tenant->business_name,
                    ));
                    $this->record($step, $tenant, $email, OnboardingEmailSend::STATUS_SENT, null, false);
                    $sent++;
                } catch (\Throwable $e) {
                    $this->record($step, $tenant, $email, OnboardingEmailSend::STATUS_FAILED, mb_substr($e->getMessage(), 0, 480), false);
                    Log::warning('Onboarding email failed', [
                        'tenant_id' => $tenant->id, 'step' => $step->step_no, 'error' => $e->getMessage(),
                    ]);
                }

                break; // at most one step per tenant per day
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."Onboarding: {$sent} sent, {$skipped} skipped.");

        return self::SUCCESS;
    }

    protected function record(OnboardingEmail $step, Tenant $tenant, string $email, string $status, ?string $error, bool $dry): void
    {
        if ($dry) {
            return;
        }

        OnboardingEmailSend::firstOrCreate(
            ['onboarding_email_id' => $step->id, 'tenant_id' => $tenant->id],
            ['email' => $email, 'status' => $status, 'error' => $error, 'sent_at' => $status === OnboardingEmailSend::STATUS_SENT ? now() : null],
        );
    }
}
