<?php

namespace App\Console\Commands;

use App\Models\AffiliateCommission;
use Illuminate\Console\Command;

/**
 * Daily affiliate-commission lifecycle: a commission accrues as `pending` and
 * sits in a refund-protection hold; once it is older than hold_days it becomes
 * `approved` (payable). Admin can void a pending commission before then
 * (refund/chargeback/fraud), and marks approved ones `paid` after the manual
 * bank transfer.
 */
class ProcessAffiliateCommissions extends Command
{
    protected $signature = 'affiliates:process {--dry-run : Report what would change without writing}';

    protected $description = 'Approve pending affiliate commissions that have cleared the refund-protection hold';

    public function handle(): int
    {
        $holdDays = max(0, (int) config('homestay.affiliate.hold_days', 30));
        $dry = (bool) $this->option('dry-run');

        $due = AffiliateCommission::query()
            ->where('status', AffiliateCommission::STATUS_PENDING)
            ->where('created_at', '<=', now()->subDays($holdDays))
            ->get();

        if ($due->isEmpty()) {
            $this->info('No pending commissions past the hold window.');

            return self::SUCCESS;
        }

        foreach ($due as $commission) {
            if ($dry) {
                $this->line(sprintf(
                    '[dry-run] would approve #%d (affiliate %d, RM %s, %s)',
                    $commission->id,
                    $commission->affiliate_id,
                    $commission->amount,
                    $commission->source,
                ));

                continue;
            }

            $commission->update([
                'status' => AffiliateCommission::STATUS_APPROVED,
                'approved_at' => now(),
            ]);
        }

        $this->info(($dry ? 'Would approve ' : 'Approved ').$due->count().' commission(s).');

        return self::SUCCESS;
    }
}
