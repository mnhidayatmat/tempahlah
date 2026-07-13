<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A referred tenant → affiliate attribution. One row per tenant, ever (the
 * tenant_id unique index) — the first attribution is permanent. Platform-level:
 * NOT tenant-scoped.
 */
class AffiliateReferral extends Model
{
    protected $fillable = ['affiliate_id', 'tenant_id', 'converted_at'];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
