<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelIntegration extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const CHANNEL_GOOGLE = 'google';
    public const CHANNEL_AIRBNB = 'airbnb';
    public const CHANNEL_BOOKING = 'booking';

    public const MODE_OAUTH = 'oauth';
    public const MODE_ICAL = 'ical';

    protected $fillable = [
        'tenant_id', 'property_id', 'room_id',
        'channel', 'mode', 'two_way',
        'credentials_encrypted', 'ical_export_url', 'ical_import_url',
        'external_account_id',
        'last_synced_at', 'last_sync_status', 'last_sync_error', 'active',
    ];

    protected $casts = [
        'two_way' => 'boolean',
        'credentials_encrypted' => 'encrypted:array',
        'last_synced_at' => 'datetime',
        'active' => 'boolean',
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
