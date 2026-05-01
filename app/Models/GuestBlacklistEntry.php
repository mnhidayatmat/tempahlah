<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestBlacklistEntry extends Model
{
    use HasFactory;

    public const SEVERITY_NOTE = 'note';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_BLACKLIST = 'blacklist';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_OVERTURNED = 'overturned';

    protected $fillable = [
        'guest_user_id', 'reported_by_tenant_id', 'booking_id',
        'severity', 'reason_code', 'description', 'evidence_paths',
        'review_status', 'reviewed_by_admin_id', 'reviewed_at', 'admin_notes',
        'appealed', 'appeal_message', 'appealed_at', 'appeal_outcome',
    ];

    protected $casts = [
        'evidence_paths' => 'array',
        'reviewed_at' => 'datetime',
        'appealed_at' => 'datetime',
        'appealed' => 'boolean',
    ];

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'reported_by_tenant_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'reviewed_by_admin_id');
    }
}
