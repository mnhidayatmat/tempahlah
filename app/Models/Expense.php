<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'property_id', 'category', 'title',
        'description', 'amount', 'paid_to', 'incurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'incurred_at' => 'date',
    ];

    /**
     * Spend categories. Keys are stored; labels are display-only (translated in
     * the view). Keep 'other' last as the catch-all default.
     */
    public const CATEGORIES = [
        'renovation' => 'Renovation',
        'upgrade' => 'Upgrade (pool, aircond, etc.)',
        'furniture' => 'Furniture & appliances',
        'supplies' => 'House supplies (soap, detergent)',
        'toilet' => 'Toilet & bathroom items',
        'repair' => 'Repair & parts',
        'utility' => 'Utilities & bills',
        'other' => 'Other',
    ];

    public static function categoryLabel(?string $key): string
    {
        return self::CATEGORIES[$key] ?? self::CATEGORIES['other'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
