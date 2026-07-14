<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;

/**
 * Login-free earnings statement for an affiliate, addressed by their referral
 * CODE (tempahlah.com/affiliate/{code}). This is how an EXTERNAL affiliate — an
 * influencer/agency with no homestay account — sees their own clicks, referrals
 * and commissions, grabs their share link, and submits their payout bank
 * details.
 *
 * NOTE: the code is public (affiliates post /r/{code} in their marketing), so
 * this page is effectively public — by the owner's explicit choice, so an
 * affiliate can reach it with the same short code they already share. The
 * unguessable statement_token still resolves too, for any link shared before
 * the switch to code.
 */
class AffiliateStatementController extends Controller
{
    public function show(string $ident)
    {
        $affiliate = $this->resolve($ident);

        return view('affiliate.statement', array_merge($affiliate->statementData(), [
            'affiliate' => $affiliate,
            'referralUrl' => $affiliate->referralUrl(),
            'statementUrl' => $affiliate->statementUrl(),
            'holdDays' => (int) config('homestay.affiliate.hold_days', 30),
            'saved' => session('status'),
        ]));
    }

    /** Affiliate submits/updates their payout bank details from the statement page. */
    public function updateBank(Request $request, string $ident)
    {
        $affiliate = $this->resolve($ident);

        $validated = $request->validate([
            'bank_name' => 'nullable|string|max:120',
            'bank_account_holder' => 'nullable|string|max:120',
            'bank_account_no' => 'nullable|string|max:60',
        ]);

        $affiliate->update($validated);

        return redirect()
            ->route('affiliate.statement', ['token' => $affiliate->code])
            ->with('status', __('Payout details saved.'));
    }

    /** Resolve by referral code (primary) or the legacy statement token, else 404. */
    protected function resolve(string $ident): Affiliate
    {
        return Affiliate::query()
            ->where('code', $ident)
            ->orWhere('statement_token', $ident)
            ->firstOrFail();
    }
}
