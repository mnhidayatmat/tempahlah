<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Dispute extends Model
{
    use HasFactory;
    use HasUlidPublicId;

    public const STATUS_OPEN = 'open';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'public_id', 'booking_id', 'tenant_id', 'guest_user_id',
        'opened_by_type', 'opened_by_id',
        'reason', 'description', 'evidence_paths', 'amount_claimed',
        'status', 'assigned_admin_id',
        'resolution', 'resolution_amount', 'resolved_at',
    ];

    protected $casts = [
        'evidence_paths' => 'array',
        'amount_claimed' => 'decimal:2',
        'resolution_amount' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function openedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'assigned_admin_id');
    }
}
