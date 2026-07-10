<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Stamp tour_completed_at so the welcome walkthrough never reappears
     * for this user. Idempotent — re-POSTing is harmless.
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->tour_completed_at === null) {
            $user->forceFill(['tour_completed_at' => now()])->save();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Clear tour_completed_at so the walkthrough plays again on the next
     * dashboard load. The tour's own final step has always told hosts they
     * "can always replay this tour from Settings" — until now nothing ever
     * cleared the flag, so that was a promise the app couldn't keep.
     *
     * Per-user, not per-tenant: replaying is a personal preference, and a
     * co-owner shouldn't have the tour forced back on them.
     */
    public function replay(Request $request): RedirectResponse
    {
        $request->user()?->forceFill(['tour_completed_at' => null])->save();

        return redirect()
            ->route('tenant.dashboard')
            ->with('status', __('Walkthrough restarted — here it is.'));
    }
}
