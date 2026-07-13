<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One step of the automated onboarding drip (Platform Admin → Email marketing
 * → Onboarding series). Sent to each new host `day_offset` days after signup
 * by the daily marketing:send-onboarding command. NOT tenant-scoped.
 */
class OnboardingEmail extends Model
{
    /**
     * A step is only sent while the tenant's signup age sits inside
     * [day_offset, day_offset + window]. This is what keeps long-existing
     * tenants from being dogpiled with the whole series the day this ships —
     * their signup age is already past every window.
     */
    public const SEND_WINDOW_DAYS = 2;

    protected $fillable = [
        'step_no', 'day_offset', 'subject', 'body_md', 'skip_if_paid', 'enabled',
    ];

    protected $casts = [
        'skip_if_paid' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function sends(): HasMany
    {
        return $this->hasMany(OnboardingEmailSend::class);
    }
}
