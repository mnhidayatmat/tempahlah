<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An affiliate in the referral program — either a host (user_id set, created
 * lazily by the "Refer & Earn" page) or an external partner (admin-created,
 * user_id null). Platform-level: NOT tenant-scoped.
 */
class Affiliate extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id', 'name', 'email', 'phone', 'code', 'rate', 'duration_months',
        'status', 'bank_name', 'bank_account_holder', 'bank_account_no', 'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'duration_months' => 'integer',
        // Payout account number encrypted at rest (platform security baseline).
        'bank_account_no' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(AffiliateVisit::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** The shareable short link, e.g. https://tempahlah.com/r/WAFA7K2M */
    public function referralUrl(): string
    {
        return route('affiliate.visit', ['code' => $this->code]);
    }

    /**
     * Generate a unique, human-friendly referral code from a name — a short
     * alpha prefix + random suffix, e.g. "WAFA7K2M". Ambiguous chars excluded.
     */
    public static function generateCode(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($name)) ?: 'TL', 0, 4));

        do {
            $code = $prefix.strtoupper(Str::random(4));
            $code = strtr($code, ['O' => 'P', 'I' => 'J', '0' => '7', '1' => '8']);
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
