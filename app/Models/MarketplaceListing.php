<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceListing extends Model
{
    use HasFactory;
    use HasUlidPublicId;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'public_id', 'tenant_id', 'property_id',
        'slug', 'title_bm', 'title_en', 'hero_photo_path', 'search_keywords',
        'city', 'state', 'country', 'lat', 'lng',
        'house_type', 'rooms_count', 'max_guests',
        'base_price_min', 'base_price_max',
        'rating_avg', 'review_count',
        'is_featured', 'featured_until', 'published_at', 'status', 'showcase_rank',
    ];

    protected $casts = [
        'search_keywords' => 'array',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'base_price_min' => 'decimal:2',
        'base_price_max' => 'decimal:2',
        'rating_avg' => 'decimal:2',
        'is_featured' => 'boolean',
        'featured_until' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('published_at')
            // Hide listings whose owning homestay is no longer live. A tenant
            // deleted in Platform Admin is only SOFT-deleted (the listing row
            // stays status=active), so without this a removed homestay lingered
            // in the marketplace. whereHas('tenant') applies Tenant's SoftDeletes
            // global scope (deleted_at is null), and the status/suspended checks
            // mirror Tenant::isActive() + the subdomain resolver — so suspended
            // homestays drop out too, and a restored tenant reappears
            // automatically (no listing mutation on delete).
            ->whereHas('tenant', fn (Builder $t) => $t->where('status', 'active')->whereNull('suspended_at'));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
