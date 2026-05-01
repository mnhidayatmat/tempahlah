<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlidPublicId;

    public const REVIEWER_GUEST = 'guest';
    public const REVIEWER_TENANT = 'tenant';

    protected $fillable = [
        'tenant_id', 'public_id', 'booking_id',
        'reviewer_type', 'subject_type', 'subject_id',
        'rating_overall', 'rating_breakdown',
        'comment', 'public_reply', 'replied_at', 'is_published',
    ];

    protected $casts = [
        'rating_breakdown' => 'array',
        'replied_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
