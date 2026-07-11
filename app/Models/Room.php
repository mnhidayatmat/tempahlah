<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Room extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlidPublicId;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'public_id', 'property_id',
        'name', 'room_type',
        'max_adults', 'max_children', 'beds', 'bed_type',
        'base_price', 'currency', 'sst_applicable',
        'amenities', 'description_bm', 'description_en', 'status',
        'ical_export_token',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'sst_applicable' => 'boolean',
        'amenities' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    public function calendarBlocks(): HasMany
    {
        return $this->hasMany(CalendarBlock::class);
    }

    public function channelIntegrations(): HasMany
    {
        return $this->hasMany(ChannelIntegration::class);
    }

    /**
     * Get (creating if absent) this room's public iCal export token. Used to
     * address the room's busy-feed at /calendar/{token}.ics. Rotatable via
     * rotateIcalToken() if the URL leaks.
     */
    public function icalExportToken(): string
    {
        if (empty($this->ical_export_token)) {
            $this->forceFill(['ical_export_token' => (string) Str::ulid().Str::lower(Str::random(8))])->save();
        }

        return $this->ical_export_token;
    }

    /** Regenerate the export token, invalidating the old feed URL. */
    public function rotateIcalToken(): string
    {
        $this->forceFill(['ical_export_token' => (string) Str::ulid().Str::lower(Str::random(8))])->save();

        return $this->ical_export_token;
    }

    /** The absolute public URL of this room's iCal busy-feed. */
    public function icalExportUrl(): string
    {
        return url('/calendar/'.$this->icalExportToken().'.ics');
    }
}
