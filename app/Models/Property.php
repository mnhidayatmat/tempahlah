<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlidPublicId;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const PRICING_WHOLE_HOUSE = 'whole_house';
    public const PRICING_PER_ROOM    = 'per_room';

    protected $fillable = [
        'tenant_id', 'public_id', 'name', 'slug',
        'description_bm', 'description_en',
        'property_type', 'star_rating', 'bathrooms', 'toilets', 'pricing_mode',
        'default_guests',
        'address_line1', 'address_line2', 'city', 'state', 'postcode', 'country',
        'map_url',
        'booking_fee_amount', 'booking_fee_label',
        'lat', 'lng',
        'check_in_time', 'check_out_time',
        'house_rules', 'cancellation_policy',
        'hero_photo_path', 'status', 'marketplace_enabled', 'marketplace_published_at',
        'custom_domain', 'meta',
    ];

    protected $casts = [
        'marketplace_enabled' => 'boolean',
        'marketplace_published_at' => 'datetime',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'booking_fee_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class)->orderBy('sort_order');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenity');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    public function calendarBlocks(): HasMany
    {
        return $this->hasMany(CalendarBlock::class);
    }

    public function isOnMarketplace(): bool
    {
        return $this->marketplace_enabled
            && $this->status === self::STATUS_ACTIVE
            && $this->marketplace_published_at !== null;
    }

    public function isWholeHousePricing(): bool
    {
        return ($this->pricing_mode ?? self::PRICING_WHOLE_HOUSE) === self::PRICING_WHOLE_HOUSE;
    }

    /**
     * Starting nightly rate to show on cards / search results.
     * Whole-house properties: the single price.
     * Per-room properties:   the cheapest room (so guests see "from RM X").
     */
    public function startingNightlyRate(): float
    {
        return (float) ($this->rooms->min('base_price') ?? 0);
    }

    /**
     * Default value the public booking page should pre-fill into the
     * "tetamu / guests" stepper. Tenant can set this explicitly per
     * property in /dashboard/properties/{id}/edit; if they don't, fall
     * back to half of the property's total sleeping capacity (rounded
     * down, never less than 1) — sensible "comfortable party size"
     * default that's neither solo nor max-occupancy.
     *
     * Requires `rooms` relation to be loaded.
     */
    public function effectiveDefaultGuests(): int
    {
        if ($this->default_guests !== null && (int) $this->default_guests > 0) {
            return (int) $this->default_guests;
        }
        $sleeps = (int) $this->rooms->sum(
            fn ($r) => (int) $r->max_adults + (int) $r->max_children
        );
        return max(1, (int) floor($sleeps / 2));
    }
}
