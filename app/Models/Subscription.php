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
    public const PLAN_PRO = 'pro';
    public const PLAN_ULTRA = 'ultra';

    /**
     * @deprecated The 2-tier era's paid plan. Rows were data-migrated to 'pro'
     * (2026_07_13 migration) and the constant now aliases PLAN_PRO so every
     * legacy `plan === PLAN_PAID` comparison and write keeps working. The raw
     * string 'paid' only survives as request input — see normalizePlanKey().
     */
    public const PLAN_PAID = self::PLAN_PRO;

    /** Plan values that hold paid features. */
    public const PAID_PLANS = [self::PLAN_PRO, self::PLAN_ULTRA];

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
        'stripe_customer_id', 'stripe_subscription_id', 'stripe_price_id',
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

    /**
     * Driven by Stripe (auto-charge + dunning happen on Stripe's side). These
     * subs are excluded from the Billplz pay-link cron and the grace/downgrade
     * lifecycle command — their state comes from Stripe webhooks instead.
     */
    public function isStripeManaged(): bool
    {
        return filled($this->stripe_subscription_id);
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
        return $this->tierRank() >= 1;
    }

    /**
     * Normalize any plan input (request params, legacy DB values) to a
     * canonical plan key. The legacy 'paid' value maps to 'pro'; anything
     * unrecognized degrades to 'free' — never accidentally upward.
     */
    public static function normalizePlanKey(?string $plan): string
    {
        return match ($plan) {
            self::PLAN_PRO, 'paid' => self::PLAN_PRO,
            self::PLAN_ULTRA => self::PLAN_ULTRA,
            default => self::PLAN_FREE,
        };
    }

    /**
     * The plan the tenant signed up for (the intent recorded in the plan
     * column) — regardless of whether it is currently lapsed.
     */
    public function planKey(): string
    {
        return self::normalizePlanKey($this->plan);
    }

    /**
     * The plan whose features the tenant actually holds right now.
     *
     * Comped accounts hold everything (ultra). A lapsed trial holds nothing
     * beyond free. A lapsed paid period keeps its tier until its grace window
     * closes, so a failed payment doesn't instantly break the tenant's live
     * guest booking flow. The grace window is derived from current_period_end
     * rather than read from grace_ends_at alone, so a tenant is never wrongly
     * cut off just because the daily lifecycle command hasn't run yet.
     */
    public function effectivePlanKey(): string
    {
        if ($this->isComped()) {
            // A comp holds the tier on the plan column (an admin can grant a
            // Pro comp or an Ultra comp). Legacy comps sitting on the free
            // plan get everything — generous by design.
            $plan = $this->planKey();

            return $plan === self::PLAN_FREE ? self::PLAN_ULTRA : $plan;
        }

        $plan = $this->planKey();

        if ($plan === self::PLAN_FREE) {
            return self::PLAN_FREE;
        }

        $holdsTier = match ($this->status) {
            self::STATUS_TRIALING => (bool) $this->trial_ends_at?->isFuture(),
            self::STATUS_ACTIVE => $this->current_period_end === null
                || now()->lessThanOrEqualTo($this->current_period_end->copy()->addDays(self::graceDays())),
            self::STATUS_PAST_DUE => (bool) $this->grace_ends_at?->isFuture(),
            default => false,
        };

        return $holdsTier ? $plan : self::PLAN_FREE;
    }

    /** 0 = free, 1 = pro, 2 = ultra — for "at least Pro" checks. */
    public function tierRank(): int
    {
        return \App\Support\Billing\Plans::rank($this->effectivePlanKey());
    }

    public function isPro(): bool
    {
        return $this->effectivePlanKey() === self::PLAN_PRO;
    }

    public function isUltra(): bool
    {
        return $this->effectivePlanKey() === self::PLAN_ULTRA;
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
            || (in_array($this->plan, self::PAID_PLANS, true) && (bool) $this->current_period_end?->isPast());
    }
}
