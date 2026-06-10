<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaundryTask extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'property_id', 'booking_id',
        'assigned_to_user_id', 'vendor_name',
        'status', 'cost', 'pickup_at', 'picked_up_at',
        'expected_return_at', 'returned_at',
        'item_count', 'items', 'notes',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'pickup_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'returned_at' => 'datetime',
        'items' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
