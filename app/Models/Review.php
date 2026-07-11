<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Builder;
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
        'comment', 'guest_name', 'public_reply', 'replied_at', 'is_published',
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

    /**
     * A guest testimonial about a property (the only kind we render publicly).
     */
    public function scopeGuestTestimonials(Builder $query): Builder
    {
        return $query->where('reviewer_type', self::REVIEWER_GUEST)
            ->where('subject_type', Property::class);
    }

    /** Only testimonials a super admin hasn't hidden. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * The name to show on the public card: the guest's typed name, falling back
     * to the booking's lead-guest name. Never empty.
     */
    public function displayName(): string
    {
        $name = trim((string) $this->guest_name);
        if ($name !== '') {
            return $name;
        }

        return $this->booking?->guestName() ?: __('Guest');
    }

    /** e.g. "Stayed Jul 2026" — derived from the booking, no stored column. */
    public function stayLabel(): ?string
    {
        $when = $this->booking?->check_out ?? $this->created_at;

        return $when?->translatedFormat('M Y');
    }
}
