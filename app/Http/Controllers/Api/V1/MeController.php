<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['tenants' => fn ($q) => $q->wherePivot('status', 'active')]);

        return response()->json(['data' => [
            'public_id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'tenants' => $user->tenants->map(fn ($t) => [
                'public_id' => $t->public_id,
                'business_name' => $t->business_name,
                'role' => $t->pivot->role,
            ]),
        ]]);
    }

    public function switchTenant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_public_id' => ['required', 'ulid'],
        ]);

        $user = $request->user();
        $belongs = $user->tenants()->where('public_id', $data['tenant_public_id'])->wherePivot('status', 'active')->exists();

        if (! $belongs) {
            return response()->json(['errors' => ['tenant' => 'Not a member.']], 403);
        }

        return response()->json(['data' => ['status' => 'switched']]);
    }
}
