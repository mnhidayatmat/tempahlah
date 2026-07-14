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
        'user_id', 'name', 'email', 'phone', 'code', 'statement_token', 'rate', 'duration_months',
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
     * Unguessable statement token — kept as a legacy fallback identifier so any
     * statement link shared before the switch to code-based URLs still resolves.
     * Lazily generated so pre-existing rows self-heal on first access.
     */
    public function ensureStatementToken(): string
    {
        if (empty($this->statement_token)) {
            $this->forceFill(['statement_token' => (string) Str::ulid().Str::lower(Str::random(12))])->save();
        }

        return $this->statement_token;
    }

    /**
     * URL of the earnings statement page (tempahlah.com/affiliate/{code}). Uses
     * the referral code so it's the same short handle the affiliate already
     * shares — see AffiliateStatementController for the (owner-accepted) tradeoff.
     */
    public function statementUrl(): string
    {
        return route('affiliate.statement', ['token' => $this->code]);
    }

    /**
     * The read-only stats shown on both the host "Refer & Earn" dashboard and
     * the external affiliate's private statement page — one source of truth so
     * the two surfaces can never disagree.
     *
     * @return array{clicks:int, referrals:\Illuminate\Support\Collection, convertedCount:int, commissions:\Illuminate\Support\Collection, pendingTotal:float, approvedTotal:float, paidTotal:float}
     */
    public function statementData(int $commissionLimit = 100): array
    {
        $referrals = $this->referrals()
            ->with('tenant:id,business_name,created_at')
            ->orderByDesc('id')
            ->get();

        $commissions = $this->commissions()
            ->with('tenant:id,business_name')
            ->orderByDesc('id')
            ->limit($commissionLimit)
            ->get();

        $sums = $this->commissions()
            ->selectRaw('status, SUM(amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'clicks' => (int) $this->visits()->sum('clicks'),
            'referrals' => $referrals,
            'convertedCount' => $referrals->whereNotNull('converted_at')->count(),
            'commissions' => $commissions,
            'pendingTotal' => (float) ($sums[AffiliateCommission::STATUS_PENDING] ?? 0),
            'approvedTotal' => (float) ($sums[AffiliateCommission::STATUS_APPROVED] ?? 0),
            'paidTotal' => (float) ($sums[AffiliateCommission::STATUS_PAID] ?? 0),
        ];
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
