<?php

namespace App\Models;

use App\Observers\SubscriptionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(SubscriptionObserver::class)]
class Subscription extends Model
{
    use HasFactory;

    public const PLAN_FREE = 'free';
    public const PLAN_PAID = 'paid';

    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';

    public const CARD_ACTIVE = 'active';

    protected $fillable = [
        'tenant_id', 'plan', 'status', 'billing_method',
        'monthly_amount', 'currency',
        'trial_ends_at', 'current_period_start', 'current_period_end', 'cancelled_at',
        'comped_at', 'grace_ends_at', 'trial_used_at',
        'auto_renew', 'card_id', 'card_token', 'card_last4', 'card_brand', 'card_status',
        'meta',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'comped_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'trial_used_at' => 'datetime',
        'monthly_amount' => 'decimal:2',
        'auto_renew' => 'boolean',
        // The token charges money — never store it in plaintext.
        'card_token' => 'encrypted',
        'meta' => 'array',
    ];

    /**
     * A usable saved card the daily command can auto-charge: the tenant opted in,
     * and Billplz reported the token active. `card_token` is decrypted lazily.
     */
    public function hasChargeableCard(): bool
    {
        return $this->auto_renew
            && $this->card_status === self::CARD_ACTIVE
            && filled($this->card_id)
            && filled($this->card_token);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function trialDays(): int
    {
        return (int) config('homestay.paid_trial_days', 7);
    }

    public static function graceDays(): int
    {
        return (int) config('homestay.subscription_grace_days', 7);
    }

    /**
     * Comped accounts (staff, demos, early partners) are never billed and never
     * downgraded. This is the only way to hold paid features without paying.
     */
    public function isComped(): bool
    {
        return $this->comped_at !== null;
    }

    /**
     * Grants every paid feature flag — see FeatureServiceProvider, where all
     * flags resolve through Tenant::isPaid() -> here.
     *
     * A lapsed trial is NOT paid. A lapsed paid period IS paid until its grace
     * window closes, so a failed payment doesn't instantly break the tenant's
     * live guest booking flow. The grace window is derived from current_period_end
     * rather than read from grace_ends_at alone, so a tenant is never wrongly
     * cut off just because the daily lifecycle command hasn't run yet.
     */
    public function isPaid(): bool
    {
        if ($this->isComped()) {
            return true;
        }

        if ($this->plan !== self::PLAN_PAID) {
            return false;
        }

        return match ($this->status) {
            self::STATUS_TRIALING => (bool) $this->trial_ends_at?->isFuture(),
            self::STATUS_ACTIVE => $this->current_period_end === null
                || now()->lessThanOrEqualTo($this->current_period_end->copy()->addDays(self::graceDays())),
            self::STATUS_PAST_DUE => (bool) $this->grace_ends_at?->isFuture(),
            default => false,
        };
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

    /**
     * True once the tenant has ever started the free trial. Downgrading clears
     * trial_ends_at, so this is what stops a tenant farming unlimited trials by
     * cycling paid -> free -> paid.
     */
    public function hasUsedTrial(): bool
    {
        return $this->trial_used_at !== null;
    }

    /**
     * Lapsed, still holding its features, being chased for payment.
     */
    public function inGrace(): bool
    {
        return ! $this->isComped()
            && $this->status === self::STATUS_PAST_DUE
            && (bool) $this->grace_ends_at?->isFuture();
    }

    /**
     * Owes money: the paid period ran out, or billing already marked it past_due.
     * Comped accounts never owe.
     */
    public function isOverdue(): bool
    {
        if ($this->isComped() || $this->status === self::STATUS_CANCELLED) {
            return false;
        }

        return $this->status === self::STATUS_PAST_DUE
            || ($this->plan === self::PLAN_PAID && (bool) $this->current_period_end?->isPast());
    }
}
