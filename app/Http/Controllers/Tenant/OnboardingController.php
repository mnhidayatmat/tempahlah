<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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
}
