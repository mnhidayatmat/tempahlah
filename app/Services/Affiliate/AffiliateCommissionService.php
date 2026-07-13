<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Accrues affiliate commissions on REAL subscription money. Called from the
 * two settlement points (Billplz settle(), Stripe invoice.paid) — always
 * inside a try/catch at the call site, so commission accrual can never break
 * a payment. Idempotent: `source` is unique, so webhook replays are no-ops.
 */
class AffiliateCommissionService
{
    /**
     * Record a commission for a subscription payment made by a referred
     * tenant. Returns the commission, or null when nothing accrues (tenant
     * not referred, affiliate suspended, window expired, zero amount, or the
     * payment was already commissioned).
     */
    public function recordSubscriptionPayment(int $tenantId, float $amount, string $source, string $description = ''): ?AffiliateCommission
    {
        if ($amount < 0.01) {
            return null;
        }

        $referral = AffiliateReferral::query()
            ->with('affiliate')
            ->where('tenant_id', $tenantId)
            ->first();

        $affiliate = $referral?->affiliate;

        if (! $referral || ! $affiliate || ! $affiliate->isActive()) {
            return null;
        }

        // The earning window: duration_months from the tenant's FIRST paid
        // conversion. Stamp it on the first commissionable payment.
        if (! $referral->converted_at) {
            $referral->update(['converted_at' => now()]);
            $referral->refresh();
        }

        $months = max(1, (int) $affiliate->duration_months
            ?: (int) config('homestay.affiliate.duration_months', 12));

        if (now()->greaterThan($referral->converted_at->copy()->addMonthsNoOverflow($months))) {
            return null;
        }

        $rate = (float) $affiliate->rate;

        if ($rate <= 0) {
            return null;
        }

        try {
            $commission = AffiliateCommission::query()->firstOrCreate(
                ['source' => $source],
                [
                    'affiliate_id' => $affiliate->id,
                    'tenant_id' => $tenantId,
                    'description' => mb_substr($description, 0, 200),
                    'base_amount' => round($amount, 2),
                    'rate' => $rate,
                    'amount' => round($amount * $rate / 100, 2),
                    'status' => AffiliateCommission::STATUS_PENDING,
                ],
            );
        } catch (QueryException) {
            // Unique violation on source — a concurrent webhook won the race.
            return null;
        }

        if ($commission->wasRecentlyCreated) {
            Log::info('Affiliate commission accrued', [
                'affiliate_id' => $affiliate->id,
                'tenant_id' => $tenantId,
                'source' => $source,
                'amount' => (string) $commission->amount,
            ]);

            return $commission;
        }

        return null;
    }
}
