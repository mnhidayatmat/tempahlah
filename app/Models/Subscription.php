<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    public const PLAN_FREE = 'free';
    public const PLAN_PAID = 'paid';

    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'plan', 'status', 'billing_method',
        'monthly_amount', 'currency',
        'trial_ends_at', 'current_period_start', 'current_period_end', 'cancelled_at',
        'meta',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'monthly_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPaid(): bool
    {
        return $this->plan === self::PLAN_PAID
            && in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE]);
    }

    public function isFree(): bool
    {
        return $this->plan === self::PLAN_FREE;
    }

    public function onTrial(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at?->isFuture();
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE
            || ($this->plan === self::PLAN_PAID
                && $this->current_period_end?->isPast()
                && $this->status !== self::STATUS_CANCELLED);
    }
}
