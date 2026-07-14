<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateVisit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform Admin → Affiliates. Manage the referral program: create external
 * affiliates (influencers/agencies with no homestay account), tune each
 * affiliate's rate/status, review their referrals + commissions, void a bad
 * commission, and record manual payouts (bulk mark-paid with a reference).
 * Reached only through the platform.admin middleware.
 */
class PlatformAffiliateController extends Controller
{
    public function index()
    {
        $affiliates = Affiliate::query()
            ->withCount('referrals')
            ->orderByDesc('id')
            ->get();

        // One grouped aggregate for every affiliate's commission totals.
        $totals = AffiliateCommission::query()
            ->selectRaw('affiliate_id, status, SUM(amount) as total')
            ->groupBy('affiliate_id', 'status')
            ->get()
            ->groupBy('affiliate_id');

        $clicks = AffiliateVisit::query()
            ->selectRaw('affiliate_id, SUM(clicks) as total')
            ->groupBy('affiliate_id')
            ->pluck('total', 'affiliate_id');

        return view('platform.affiliates.index', [
            'affiliates' => $affiliates,
            'totals' => $totals,
            'clicks' => $clicks,
            'defaultRate' => (float) config('homestay.affiliate.default_rate', 20),
            'defaultMonths' => (int) config('homestay.affiliate.duration_months', 12),
        ]);
    }

    /** Create an EXTERNAL affiliate (no user account required). */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:40',
            'code' => ['nullable', 'string', 'min:1', 'max:24', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('affiliates', 'code')],
            'rate' => 'required|numeric|min:0|max:50',
            'duration_months' => 'required|integer|min:1|max:60',
        ]);

        $affiliate = Affiliate::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'code' => strtoupper($validated['code'] ?? '') ?: Affiliate::generateCode($validated['name']),
            'rate' => $validated['rate'],
            'duration_months' => $validated['duration_months'],
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('platform.affiliates.show', $affiliate)
            ->with('status', __('Affiliate created — share their link: :url', ['url' => $affiliate->referralUrl()]));
    }

    public function show(Affiliate $affiliate)
    {
        $affiliate->loadCount('referrals');

        return view('platform.affiliates.show', [
            'affiliate' => $affiliate,
            'referrals' => $affiliate->referrals()->with('tenant:id,business_name,created_at')->orderByDesc('id')->get(),
            'commissions' => $affiliate->commissions()->with('tenant:id,business_name')->orderByDesc('id')->limit(200)->get(),
            'clicks' => (int) AffiliateVisit::query()->where('affiliate_id', $affiliate->id)->sum('clicks'),
            'sums' => AffiliateCommission::query()
                ->where('affiliate_id', $affiliate->id)
                ->selectRaw('status, SUM(amount) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    /** Tune code / rate / duration / status / contact details. */
    public function update(Request $request, Affiliate $affiliate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:40',
            'code' => ['required', 'string', 'min:1', 'max:24', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('affiliates', 'code')->ignore($affiliate->id)],
            'rate' => 'required|numeric|min:0|max:50',
            'duration_months' => 'required|integer|min:1|max:60',
            'status' => ['required', Rule::in([Affiliate::STATUS_ACTIVE, Affiliate::STATUS_SUSPENDED])],
            'notes' => 'nullable|string|max:500',
        ]);

        // Changing the code breaks any link already shared under the old one —
        // the admin is warned in the UI; store it upper-cased for consistency.
        $validated['code'] = strtoupper($validated['code']);

        $affiliate->update($validated);

        return redirect()
            ->route('platform.affiliates.show', $affiliate)
            ->with('status', __('Affiliate updated.'));
    }

    /**
     * Permanently remove an affiliate along with their referral attributions,
     * daily click counters and any not-yet-owed (pending/void) commissions.
     * Guarded so real financial history can't be vaporised: an affiliate with
     * PAID or PAYABLE (approved) commissions must be settled/voided — or simply
     * suspended — before deletion.
     */
    public function destroy(Affiliate $affiliate)
    {
        $locked = $affiliate->commissions()
            ->whereIn('status', [AffiliateCommission::STATUS_APPROVED, AffiliateCommission::STATUS_PAID])
            ->count();

        if ($locked > 0) {
            return redirect()
                ->route('platform.affiliates.show', $affiliate)
                ->with('error', __('Can’t delete — this affiliate has paid or payable commissions. Suspend them instead, or settle/void those commissions first.'));
        }

        $name = $affiliate->name;
        // Child rows (referrals, pending commissions, visits) cascade on delete.
        $affiliate->delete();

        return redirect()
            ->route('platform.affiliates.index')
            ->with('status', __('Affiliate “:name” deleted.', ['name' => $name]));
    }

    /**
     * Record a manual payout: every APPROVED commission for this affiliate is
     * marked paid under one payout reference (the bank-transfer/DuitNow ref).
     */
    public function markPaid(Request $request, Affiliate $affiliate)
    {
        $validated = $request->validate([
            'payout_ref' => 'required|string|max:120',
        ]);

        $count = $affiliate->commissions()
            ->where('status', AffiliateCommission::STATUS_APPROVED)
            ->update([
                'status' => AffiliateCommission::STATUS_PAID,
                'paid_at' => now(),
                'payout_ref' => $validated['payout_ref'],
            ]);

        return redirect()
            ->route('platform.affiliates.show', $affiliate)
            ->with('status', $count
                ? __(':count commission(s) marked paid.', ['count' => $count])
                : __('Nothing to pay — no approved commissions.'));
    }

    /** Void a commission (refund / chargeback / fraud). Paid ones stay paid. */
    public function voidCommission(Affiliate $affiliate, int $commission)
    {
        $row = $affiliate->commissions()->whereKey($commission)->firstOrFail();

        if ($row->status === AffiliateCommission::STATUS_PAID) {
            return redirect()
                ->route('platform.affiliates.show', $affiliate)
                ->with('error', __('Already paid out — settle it outside the system.'));
        }

        $row->update(['status' => AffiliateCommission::STATUS_VOID]);

        return redirect()
            ->route('platform.affiliates.show', $affiliate)
            ->with('status', __('Commission voided.'));
    }
}
