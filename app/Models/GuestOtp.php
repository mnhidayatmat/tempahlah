<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestOtp extends Model
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PHONE = 'phone';

    protected $fillable = [
        'identifier', 'channel', 'code_hash',
        'attempts', 'expires_at', 'verified_at', 'ip',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public static function issue(string $identifier, string $channel, ?string $ip = null): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        self::create([
            'identifier' => $identifier,
            'channel' => $channel,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'ip' => $ip,
        ]);

        return $code;
    }

    public static function verify(string $identifier, string $channel, string $code): bool
    {
        $otp = self::where('identifier', $identifier)
            ->where('channel', $channel)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->increment('attempts');

        if ($otp->attempts > 5) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }
}
