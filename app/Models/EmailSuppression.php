<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * An email address SES told us to stop mailing — a permanent bounce or a spam
 * complaint. Deliberately NOT BelongsToTenant: the key is a person's mailbox,
 * shared across every tenant they've ever booked with.
 *
 * @property string $email
 * @property string $reason
 */
class EmailSuppression extends Model
{
    public const REASON_BOUNCE = 'bounce';

    public const REASON_COMPLAINT = 'complaint';

    protected $fillable = [
        'email', 'reason', 'subtype', 'diagnostic', 'source', 'suppressed_at',
    ];

    protected $casts = [
        'suppressed_at' => 'datetime',
    ];

    /** Normalise an address the way we store + look it up: trimmed + lowercased. */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function isSuppressed(string $email): bool
    {
        $email = self::normalize($email);
        if ($email === '') {
            return false;
        }

        // Hot path — checked on every outbound message. Cache the boolean for a
        // short window; suppress() busts it so a fresh bounce takes effect fast.
        return Cache::remember(
            self::cacheKey($email),
            now()->addMinutes(10),
            fn () => self::query()->where('email', $email)->exists(),
        );
    }

    /**
     * Record (or refresh) a suppression. Idempotent — SNS can deliver the same
     * notification more than once, and a mailbox can bounce repeatedly.
     */
    public static function suppress(
        string $email,
        string $reason,
        ?string $subtype = null,
        ?string $diagnostic = null,
        string $source = 'ses',
    ): self {
        $email = self::normalize($email);

        $row = self::query()->updateOrCreate(
            ['email' => $email],
            [
                'reason'         => $reason,
                'subtype'        => $subtype,
                'diagnostic'     => $diagnostic ? mb_substr($diagnostic, 0, 1000) : null,
                'source'         => $source,
                'suppressed_at'  => now(),
            ],
        );

        Cache::forget(self::cacheKey($email));

        return $row;
    }

    protected static function cacheKey(string $normalisedEmail): string
    {
        return 'email_suppressed:'.sha1($normalisedEmail);
    }
}
