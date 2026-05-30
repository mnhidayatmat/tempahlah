<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_WEEKEND        = 'weekend';
    public const TYPE_HOLIDAY        = 'holiday';
    public const TYPE_SCHOOL_HOLIDAY = 'school_holiday';
    public const TYPE_SEASON         = 'season';
    public const TYPE_CUSTOM         = 'custom';

    public const ADJUSTMENT_PERCENT = 'percent';
    public const ADJUSTMENT_FLAT = 'flat';
    public const ADJUSTMENT_OVERRIDE = 'override';

    protected $fillable = [
        'tenant_id', 'room_id', 'property_id',
        'name', 'rule_type', 'weekday_mask',
        'date_from', 'date_to',
        'adjustment_type', 'adjustment_value',
        'priority', 'active',
    ];

    protected $casts = [
        'weekday_mask' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
        'adjustment_value' => 'decimal:4',
        'active' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function appliesTo(\Carbon\CarbonInterface $date): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->date_from && $date->lt($this->date_from)) {
            return false;
        }
        if ($this->date_to && $date->gt($this->date_to)) {
            return false;
        }

        if (! empty($this->weekday_mask) && ! in_array($date->dayOfWeek, $this->weekday_mask, true)) {
            return false;
        }

        return true;
    }

    public function applyTo(float $price): float
    {
        return match ($this->adjustment_type) {
            self::ADJUSTMENT_PERCENT => round($price * (1 + (float) $this->adjustment_value), 2),
            self::ADJUSTMENT_FLAT => round($price + (float) $this->adjustment_value, 2),
            self::ADJUSTMENT_OVERRIDE => round((float) $this->adjustment_value, 2),
            default => $price,
        };
    }
}
