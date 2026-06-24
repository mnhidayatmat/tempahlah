<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleaningTask extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_FULL = 'full';
    public const TYPE_LIGHT = 'light';
    public const TYPE_DEEP = 'deep';
    public const TYPE_POOL = 'pool';
    public const TYPE_POST_EVENT = 'post_event';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'tenant_id', 'property_id', 'room_id', 'booking_id',
        'assigned_to_user_id', 'cleaner_id',
        'type', 'status', 'cost', 'scheduled_at', 'started_at', 'completed_at',
        'photo_paths', 'notes', 'issues',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'photo_paths' => 'array',
        'issues' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function cleaner(): BelongsTo
    {
        return $this->belongsTo(Cleaner::class);
    }
}
