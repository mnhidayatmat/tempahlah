<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarBlock extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const REASON_MANUAL = 'manual';
    public const REASON_MAINTENANCE = 'maintenance';
    public const REASON_OWNER_STAY = 'owner_stay';
    public const REASON_BOOKING = 'booking';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_GOOGLE = 'google';
    public const SOURCE_AIRBNB = 'airbnb';
    public const SOURCE_BOOKING = 'booking';

    protected $fillable = [
        'tenant_id', 'property_id', 'room_id',
        'starts_on', 'ends_on',
        'reason', 'source', 'source_uid', 'notes',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
