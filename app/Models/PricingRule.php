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

        if (! empty($this->weekday_mask)) {
            // weekday_mask is a JSON array. Form submissions arrive as strings
            // ("0","6") but $date->dayOfWeek is an int — strict in_array would
            // miss every weekend. Normalize both sides to int.
            $maskInts = array_map('intval', (array) $this->weekday_mask);
            if (! in_array((int) $date->dayOfWeek, $maskInts, true)) {
                return false;
            }
        }

        return true;
    }

    public function applyTo(float $price): float
    {
        return match ($this->adjustment_type) {
            // adjustment_value for percent rules is stored as a whole-number
            // percentage (e.g. 20 = +20%, -15 = 15% discount) — that's what
            // the dashboard form labels as "% to add (use - for discount)".
            // Divide by 100 to get the multiplier.
            self::ADJUSTMENT_PERCENT => round($price * (1 + ((float) $this->adjustment_value / 100)), 2),
            self::ADJUSTMENT_FLAT => round($price + (float) $this->adjustment_value, 2),
            self::ADJUSTMENT_OVERRIDE => round((float) $this->adjustment_value, 2),
            default => $price,
        };
    }
}
