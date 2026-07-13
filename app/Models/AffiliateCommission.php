<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One commission per REAL subscription payment by a referred tenant.
 * `source` is the idempotency key (`subinv:{id}` / `stripe:{invoice_id}`), so
 * webhook replays can never double-accrue. Lifecycle: pending (refund hold) →
 * approved (payable) → paid; admin can void. Platform-level: NOT tenant-scoped.
 */
class AffiliateCommission extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'affiliate_id', 'tenant_id', 'source', 'description',
        'base_amount', 'rate', 'amount', 'status',
        'approved_at', 'paid_at', 'payout_ref',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_PAID => __('Paid'),
            self::STATUS_VOID => __('Void'),
            default => ucfirst($this->status),
        };
    }
}
