<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
