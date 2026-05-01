<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'property_id',
        'name', 'unit', 'current_qty', 'reorder_level', 'alert_enabled',
    ];

    protected $casts = [
        'current_qty' => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'alert_enabled' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function isLow(): bool
    {
        return $this->alert_enabled && $this->current_qty <= $this->reorder_level;
    }
}
