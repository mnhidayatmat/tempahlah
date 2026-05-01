<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentReport extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'booking_id', 'property_id',
        'reported_by_user_id', 'guest_user_id',
        'category', 'severity', 'description',
        'evidence_paths', 'damage_estimate', 'police_report_number',
        'escalate_to_blacklist', 'blacklist_entry_id',
    ];

    protected $casts = [
        'evidence_paths' => 'array',
        'damage_estimate' => 'decimal:2',
        'escalate_to_blacklist' => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function blacklistEntry(): BelongsTo
    {
        return $this->belongsTo(GuestBlacklistEntry::class);
    }
}
