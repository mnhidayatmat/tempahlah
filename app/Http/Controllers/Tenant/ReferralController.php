<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\AffiliateVisit;
use Illuminate\Http\Request;

/**
 * "Refer & Earn" — every host is an affiliate. The page lazily creates the
 * user's Affiliate row on first visit (code derived from their business name),
 * shows the shareable link + live stats + commission statement, and takes the
 * payout bank details. Available on every tier: free hosts referring paying
 * hosts feeds the funnel.
 */
class ReferralController extends Controller
{
    public function index(Request $request)
    {
        $affiliate = $this->affiliateFor($request);

        $referrals = AffiliateReferral::query()
            ->where('affiliate_id', $affiliate->id)
            ->with('tenant:id,business_name,created_at')
            ->orderByDesc('id')
            ->get();

        $commissions = AffiliateCommission::query()
            ->where('affiliate_id', $affiliate->id)
            ->with('tenant:id,business_name')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $sums = AffiliateCommission::query()
            ->where('affiliate_id', $affiliate->id)
            ->selectRaw('status, SUM(amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('tenant.referrals.index', [
            'affiliate' => $affiliate,
            'referralUrl' => $affiliate->referralUrl(),
            'clicks' => (int) AffiliateVisit::query()->where('affiliate_id', $affiliate->id)->sum('clicks'),
            'referrals' => $referrals,
            'convertedCount' => $referrals->whereNotNull('converted_at')->count(),
            'commissions' => $commissions,
            'pendingTotal' => (float) ($sums[AffiliateCommission::STATUS_PENDING] ?? 0),
            'approvedTotal' => (float) ($sums[AffiliateCommission::STATUS_APPROVED] ?? 0),
            'paidTotal' => (float) ($sums[AffiliateCommission::STATUS_PAID] ?? 0),
            'holdDays' => (int) config('homestay.affiliate.hold_days', 30),
        ]);
    }

    /** Save the payout bank details on the host's own affiliate row. */
    public function updateBank(Request $request)
    {
        $affiliate = $this->affiliateFor($request);

        $validated = $request->validate([
            'bank_name' => 'nullable|string|max:120',
            'bank_account_holder' => 'nullable|string|max:120',
            'bank_account_no' => 'nullable|string|max:60',
        ]);

        $affiliate->update($validated);

        return redirect()
            ->route('tenant.referrals.index')
            ->with('status', __('Payout details saved.'));
    }

    /**
     * Get-or-create the Affiliate row for the signed-in user. Keyed on the
     * USER (not the tenant): the person is the affiliate, and users.id is
     * unique on affiliates, so a re-visit always resolves the same row.
     */
    protected function affiliateFor(Request $request): Affiliate
    {
        $user = $request->user();

        $existing = Affiliate::query()->where('user_id', $user->id)->first();

        if ($existing) {
            return $existing;
        }

        $tenant = app(\App\Support\Tenancy\TenantContext::class)->current();

        return Affiliate::query()->create([
            'user_id' => $user->id,
            'name' => $tenant?->business_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'code' => Affiliate::generateCode($tenant?->business_name ?: $user->name),
            'rate' => (float) config('homestay.affiliate.default_rate', 20),
            'duration_months' => (int) config('homestay.affiliate.duration_months', 12),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
    }
}
