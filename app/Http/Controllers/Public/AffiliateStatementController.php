<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;

/**
 * Private, login-free earnings statement for an affiliate, addressed by an
 * unguessable token (tempahlah.com/affiliate/{token}). This is how an EXTERNAL
 * affiliate — an influencer/agency with no homestay account — sees their own
 * clicks, referrals and commissions, grabs their share link, and submits their
 * payout bank details. Same trust model as the iCal export token + guest
 * magic-links: the token IS the credential.
 */
class AffiliateStatementController extends Controller
{
    public function show(string $token)
    {
        $affiliate = $this->resolve($token);

        return view('affiliate.statement', array_merge($affiliate->statementData(), [
            'affiliate' => $affiliate,
            'referralUrl' => $affiliate->referralUrl(),
            'statementUrl' => $affiliate->statementUrl(),
            'holdDays' => (int) config('homestay.affiliate.hold_days', 30),
            'saved' => session('status'),
        ]));
    }

    /** Affiliate submits/updates their payout bank details from the statement page. */
    public function updateBank(Request $request, string $token)
    {
        $affiliate = $this->resolve($token);

        $validated = $request->validate([
            'bank_name' => 'nullable|string|max:120',
            'bank_account_holder' => 'nullable|string|max:120',
            'bank_account_no' => 'nullable|string|max:60',
        ]);

        $affiliate->update($validated);

        return redirect()
            ->route('affiliate.statement', ['token' => $affiliate->statement_token])
            ->with('status', __('Payout details saved.'));
    }

    /** Resolve the affiliate by token, or 404 (unguessable → not enumerable). */
    protected function resolve(string $token): Affiliate
    {
        return Affiliate::query()->where('statement_token', $token)->firstOrFail();
    }
}
