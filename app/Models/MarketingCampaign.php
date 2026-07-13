<?php

namespace App\Models;

use App\Support\Billing\Plans;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A platform → hosts email campaign (Platform Admin → Email marketing).
 * NOT BelongsToTenant — Tempahlah mailing its own customers.
 */
class MarketingCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';

    /** Audience keys → labels. Plan filters use the EFFECTIVE tier. */
    public const AUDIENCES = [
        'free' => 'Free tenants',
        'pro' => 'Pro tenants',
        'ultra' => 'Ultra tenants',
        'paid' => 'All paid (Pro + Ultra)',
        'all' => 'Every active tenant',
    ];

    protected $fillable = [
        'subject', 'body_md', 'audience', 'status',
        'recipients_total', 'sent_count', 'failed_count', 'skipped_count',
        'test_sent_at', 'queued_at', 'sent_at', 'created_by',
    ];

    protected $casts = [
        'test_sent_at' => 'datetime',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(MarketingCampaignRecipient::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_SENDING], true);
    }

    public function audienceLabel(): string
    {
        return __(self::AUDIENCES[$this->audience] ?? $this->audience);
    }

    /**
     * The tenants this campaign's audience currently resolves to: active, not
     * suspended, not marketing-opted-out, matching the plan filter by their
     * EFFECTIVE tier (comped/trial/grace resolved). Returned as a base query;
     * the plan filter runs in PHP because tier resolution is model logic.
     *
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    public function resolveAudience(): \Illuminate\Support\Collection
    {
        $tenants = Tenant::query()
            ->with(['subscription', 'owner:id,name,email'])
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereNull('marketing_opt_out_at')
            ->get();

        return $tenants->filter(function (Tenant $t) {
            $tier = $t->planKey();

            return match ($this->audience) {
                'free' => $tier === Plans::FREE,
                'pro' => $tier === Plans::PRO,
                'ultra' => $tier === Plans::ULTRA,
                'paid' => in_array($tier, [Plans::PRO, Plans::ULTRA], true),
                default => true,
            };
        })->values();
    }

    /**
     * Best reachable email for a tenant. Tenant has NO `email` column — the
     * owner's login email is the primary contact, business_email the fallback.
     */
    public static function emailFor(Tenant $tenant): ?string
    {
        $email = $tenant->owner?->email ?: $tenant->business_email;

        return filled($email) ? strtolower(trim($email)) : null;
    }

    public function scopeLatestFirst(Builder $q): Builder
    {
        return $q->orderByDesc('created_at');
    }
}
