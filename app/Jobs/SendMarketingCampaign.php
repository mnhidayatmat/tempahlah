<?php

namespace App\Jobs;

use App\Mail\MarketingCampaignMail;
use App\Models\EmailSuppression;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Works through a campaign's PENDING recipients one by one. Each send is
 * isolated — one dead mailbox never aborts the run — and only pending rows are
 * touched, so a retried/re-dispatched job can't double-send. Cancelling the
 * campaign (status = cancelled) stops the loop at the next recipient.
 */
class SendMarketingCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $campaignId)
    {
        $this->onQueue('email');
    }

    public function handle(): void
    {
        $campaign = MarketingCampaign::find($this->campaignId);

        if (! $campaign || ! in_array($campaign->status, [MarketingCampaign::STATUS_QUEUED, MarketingCampaign::STATUS_SENDING], true)) {
            return;
        }

        $campaign->update(['status' => MarketingCampaign::STATUS_SENDING]);

        $campaign->recipients()
            ->where('status', MarketingCampaignRecipient::STATUS_PENDING)
            ->orderBy('id')
            ->chunkById(50, function ($recipients) use ($campaign) {
                foreach ($recipients as $recipient) {
                    // A cancel mid-run stops before the next email goes out.
                    if ($campaign->fresh()->status === MarketingCampaign::STATUS_CANCELLED) {
                        return false;
                    }

                    $this->sendOne($campaign, $recipient);
                }
            });

        $campaign = $campaign->fresh();
        if ($campaign->status === MarketingCampaign::STATUS_SENDING) {
            $campaign->update(['status' => MarketingCampaign::STATUS_SENT, 'sent_at' => now()]);
        }
    }

    protected function sendOne(MarketingCampaign $campaign, MarketingCampaignRecipient $recipient): void
    {
        // Bounced / complained mailboxes are skipped outright (the global
        // MessageSending listener would silently halt them anyway — recording
        // "skipped" here keeps the delivery log honest).
        if (EmailSuppression::isSuppressed($recipient->email)) {
            $recipient->update(['status' => MarketingCampaignRecipient::STATUS_SKIPPED, 'error' => 'suppressed (bounce/complaint)']);
            $campaign->increment('skipped_count');

            return;
        }

        // Late opt-out between queueing and sending still wins.
        if ($recipient->tenant?->marketing_opt_out_at !== null) {
            $recipient->update(['status' => MarketingCampaignRecipient::STATUS_SKIPPED, 'error' => 'opted out']);
            $campaign->increment('skipped_count');

            return;
        }

        try {
            Mail::to($recipient->email)->send(new MarketingCampaignMail(
                campaign: $campaign,
                recipientName: $recipient->name ?: ($recipient->tenant?->business_name ?? ''),
                businessName: $recipient->tenant?->business_name ?? '',
                tenantId: $recipient->tenant_id,
            ));

            $recipient->update(['status' => MarketingCampaignRecipient::STATUS_SENT, 'sent_at' => now(), 'error' => null]);
            $campaign->increment('sent_count');
        } catch (\Throwable $e) {
            $recipient->update([
                'status' => MarketingCampaignRecipient::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 480),
            ]);
            $campaign->increment('failed_count');

            Log::warning('Marketing campaign send failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
