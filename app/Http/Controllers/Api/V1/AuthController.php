<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $token = $user->createToken($data['device_name'], ['*'], now()->addDays(30))->plainTextToken;

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return response()->json(['data' => [
            'token' => $token,
            'user' => $user->only(['public_id', 'name', 'email', 'phone', 'locale']),
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['data' => ['status' => 'ok']]);
    }

    public function registerFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:ios,android'],
        ]);

        $user = $request->user();
        $tokens = $user->fcm_tokens ?? [];
        $tokens[$data['platform']][$data['token']] = ['registered_at' => now()->toIso8601String()];
        $user->forceFill(['fcm_tokens' => $tokens])->save();

        return response()->json(['data' => ['status' => 'registered']]);
    }
}
