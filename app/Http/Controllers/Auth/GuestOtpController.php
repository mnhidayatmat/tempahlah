<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GuestOtpRequest;
use App\Models\GuestOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GuestOtpController extends Controller
{
    public function send(GuestOtpRequest $request): JsonResponse
    {
        $code = GuestOtp::issue(
            $request->input('identifier'),
            $request->input('channel'),
            $request->ip(),
        );

        if ($request->input('channel') === GuestOtp::CHANNEL_EMAIL) {
            Mail::raw(__('Your HomestayMY verification code is :code. Expires in 10 minutes.', ['code' => $code]), function ($m) use ($request) {
                $m->to($request->input('identifier'))->subject(__('Your verification code'));
            });
        } else {
            Log::info('OTP would be sent via SMS/WhatsApp', [
                'phone' => $request->input('identifier'),
                'code' => $code,
            ]);
        }

        return response()->json(['data' => ['status' => 'sent']]);
    }

    public function verify(GuestOtpRequest $request): JsonResponse
    {
        $verified = GuestOtp::verify(
            $request->input('identifier'),
            $request->input('channel'),
            $request->input('code'),
        );

        if (! $verified) {
            return response()->json(['errors' => ['code' => __('Invalid or expired code.')]], 422);
        }

        $field = $request->input('channel') === GuestOtp::CHANNEL_EMAIL ? 'email' : 'phone';
        $user = User::firstOrCreate(
            [$field => $request->input('identifier')],
            [
                'name' => $request->input('name', 'Guest'),
                'user_type' => User::TYPE_GUEST,
                $field.'_verified_at' => now(),
            ],
        );

        if (! $user->{$field.'_verified_at'}) {
            $user->forceFill([$field.'_verified_at' => now()])->save();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => ['user' => $user->only(['public_id', 'name', 'email', 'phone'])]]);
    }
}
