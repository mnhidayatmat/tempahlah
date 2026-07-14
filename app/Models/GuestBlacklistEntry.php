<?php

namespace App\Models;

use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A tenant-filed report against a guest. It stays PENDING until a platform admin
 * verifies it — an APPROVED entry is the platform-wide mark that alerts every
 * other homestay when that guest tries to book.
 */
class GuestBlacklistEntry extends Model
{
    use HasFactory;

    public const SEVERITY_NOTE = 'note';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_BLACKLIST = 'blacklist';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_OVERTURNED = 'overturned';

    /** Severity → human label (BM-friendly copy handled at the view layer). */
    public const SEVERITY_LABELS = [
        self::SEVERITY_NOTE => 'Note',
        self::SEVERITY_WARNING => 'Warning',
        self::SEVERITY_BLACKLIST => 'Blacklist',
    ];

    /** Reason code → label. reason_code is stored; the label is display-only. */
    public const REASON_LABELS = [
        'property_damage' => 'Damaged the property',
        'unpaid' => 'Did not pay / chargeback',
        'rude_behaviour' => 'Rude / disrespectful (kurang ajar)',
        'noise_complaint' => 'Noise / disturbed neighbours',
        'overcapacity' => 'Extra guests / party beyond booking',
        'house_rules' => 'Broke house rules',
        'fraud' => 'Fraud / fake booking',
        'other' => 'Other',
    ];

    protected $fillable = [
        'guest_user_id', 'reported_by_tenant_id', 'booking_id',
        'guest_name', 'guest_phone', 'guest_email',
        'severity', 'reason_code', 'description', 'evidence_paths',
        'review_status', 'reviewed_by_admin_id', 'reviewed_by_user_id', 'reviewed_at', 'admin_notes',
        'appealed', 'appeal_message', 'appealed_at', 'appeal_outcome',
    ];

    protected $casts = [
        'evidence_paths' => 'array',
        'reviewed_at' => 'datetime',
        'appealed_at' => 'datetime',
        'appealed' => 'boolean',
    ];

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'reported_by_tenant_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'reviewed_by_admin_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopePending(Builder $q): Builder
    {
        return $q->where('review_status', self::STATUS_PENDING);
    }

    /** Verified — the platform-wide mark that alerts other homestays. */
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('review_status', self::STATUS_APPROVED);
    }

    /* ── Cross-tenant matching ──────────────────────────────────────────── */

    /**
     * Verified flags matching a guest by user id OR normalized phone OR email.
     * This is what a new tenant is alerted with — a returning guest may be a
     * different User row at a different homestay, so we can't rely on user id
     * alone. Returns entries with the reporting tenant eager-loaded.
     *
     * @return Collection<int, static>
     */
    public static function approvedFlagsFor(?int $userId, ?string $phone, ?string $email): Collection
    {
        $normPhone = PhoneNumber::normalize($phone);
        $email = $email ? mb_strtolower(trim($email)) : null;

        if (! $userId && ! $normPhone && ! $email) {
            return collect();
        }

        return static::query()
            ->approved()
            ->with('tenant:id,business_name,slug')
            ->where(function (Builder $w) use ($userId, $normPhone, $email) {
                if ($userId) {
                    $w->orWhere('guest_user_id', $userId);
                }
                if ($normPhone) {
                    $w->orWhere('guest_phone', $normPhone);
                }
                if ($email) {
                    $w->orWhereRaw('LOWER(guest_email) = ?', [$email]);
                }
            })
            ->orderByDesc('reviewed_at')
            ->get();
    }

    /**
     * The single most-severe verified flag for a guest, or null. Used to badge
     * a bookings row / drive the alert headline.
     */
    public static function topFlagFor(?int $userId, ?string $phone, ?string $email): ?self
    {
        $rank = [self::SEVERITY_BLACKLIST => 3, self::SEVERITY_WARNING => 2, self::SEVERITY_NOTE => 1];

        return static::approvedFlagsFor($userId, $phone, $email)
            ->sortByDesc(fn ($e) => $rank[$e->severity] ?? 0)
            ->first();
    }

    /* ── Display helpers ────────────────────────────────────────────────── */

    public function reasonLabel(): string
    {
        return self::REASON_LABELS[$this->reason_code] ?? ucfirst(str_replace('_', ' ', (string) $this->reason_code));
    }

    public function severityLabel(): string
    {
        return self::SEVERITY_LABELS[$this->severity] ?? ucfirst((string) $this->severity);
    }

    /** Pill variant for the severity chip. */
    public function severityVariant(): string
    {
        return match ($this->severity) {
            self::SEVERITY_BLACKLIST => 'err',
            self::SEVERITY_WARNING => 'warn',
            default => 'default',
        };
    }

    public function statusVariant(): string
    {
        return match ($this->review_status) {
            self::STATUS_APPROVED => 'ok',
            self::STATUS_REJECTED, self::STATUS_OVERTURNED => 'default',
            default => 'warn',
        };
    }

    public function isVerified(): bool
    {
        return $this->review_status === self::STATUS_APPROVED;
    }

    /** The guest's best display name (snapshot first, then linked user). */
    public function displayName(): string
    {
        return $this->guest_name ?: ($this->guest?->name ?? __('Guest'));
    }
}
