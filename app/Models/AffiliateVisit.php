<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily click counter per affiliate (one row per affiliate per day) — cheap
 * link stats without a row per click. Platform-level: NOT tenant-scoped.
 */
class AffiliateVisit extends Model
{
    protected $fillable = ['affiliate_id', 'date', 'clicks'];

    protected $casts = [
        // Deliberately NO 'date' cast: the cast writes 'Y-m-d H:i:s' on SQLite
        // while lookups compare 'Y-m-d' (the documented AgentUsageDaily quirk),
        // which would break the daily firstOrCreate. Plain 'Y-m-d' strings
        // round-trip identically on MySQL and SQLite.
        'clicks' => 'integer',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
