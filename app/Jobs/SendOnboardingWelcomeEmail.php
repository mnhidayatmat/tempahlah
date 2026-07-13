<?php

namespace App\Jobs;

use App\Mail\OnboardingEmailMail;
use App\Models\EmailSuppression;
use App\Models\MarketingCampaign;
use App\Models\OnboardingEmail;
use App\Models\OnboardingEmailSend;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the day-0 onboarding email (step with day_offset = 0) the moment a
 * host registers — dispatched from CreateTenantAndOwner right after signup,
 * instead of waiting for the next `marketing:send-onboarding` daily batch
 * (01:30 UTC / 09:30 MYT). The daily command remains the fallback: if this
 * job fails, it records nothing, so tomorrow's batch picks the tenant up and
 * sends step 1 normally (age 0 or 1 is still inside the send window).
 */
class SendOnboardingWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $tenantId)
    {
        $this->onQueue('email');
    }

    public function handle(): void
    {
        $tenant = Tenant::with('owner')->find($this->tenantId);

        if (! $tenant || $tenant->status !== 'active' || $tenant->suspended_at !== null || $tenant->marketing_opt_out_at !== null) {
            return;
        }

        $step = OnboardingEmail::query()
            ->where('day_offset', 0)
            ->where('enabled', true)
            ->orderBy('step_no')
            ->first();

        if (! $step) {
            return;
        }

        // Idempotency guard — the unique (step, tenant) index also enforces
        // this at the DB level, but check first to skip a needless send if
        // the daily batch somehow already reached this tenant first (race).
        if (OnboardingEmailSend::where('onboarding_email_id', $step->id)->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $email = MarketingCampaign::emailFor($tenant);
        if (! $email) {
            return; // no owner/business email on file — the daily batch will retry
        }

        if (EmailSuppression::isSuppressed($email)) {
            OnboardingEmailSend::firstOrCreate(
                ['onboarding_email_id' => $step->id, 'tenant_id' => $tenant->id],
                ['email' => $email, 'status' => OnboardingEmailSend::STATUS_SKIPPED, 'error' => 'suppressed (bounce/complaint)'],
            );

            return;
        }

        try {
            Mail::to($email)->send(new OnboardingEmailMail(
                step: $step,
                tenant: $tenant,
                recipientName: $tenant->owner?->name ?: $tenant->business_name,
            ));

            OnboardingEmailSend::firstOrCreate(
                ['onboarding_email_id' => $step->id, 'tenant_id' => $tenant->id],
                ['email' => $email, 'status' => OnboardingEmailSend::STATUS_SENT, 'sent_at' => now()],
            );
        } catch (\Throwable $e) {
            // Deliberately NOT recording a 'failed' row: the daily command's
            // "already handled" check is a blanket exists() over this tenant's
            // sends, so a permanent failure row would silently block step 1
            // forever. Leaving no row means tomorrow's batch retries it.
            Log::warning('Immediate onboarding welcome email failed — will fall back to the daily batch', [
                'tenant_id' => $tenant->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
