<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tenant × one onboarding step — the unique row is the idempotency guard
 * that makes the daily sender safe to re-run.
 */
class OnboardingEmailSend extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'onboarding_email_id', 'tenant_id', 'email', 'status', 'error', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(OnboardingEmail::class, 'onboarding_email_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
