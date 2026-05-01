<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Booking extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlidPublicId;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const CHANNEL_DIRECT = 'direct';
    public const CHANNEL_MARKETPLACE = 'marketplace';
    public const CHANNEL_AIRBNB = 'airbnb';
    public const CHANNEL_BOOKING = 'booking';
    public const CHANNEL_WALK_IN = 'walk_in';

    protected $fillable = [
        'tenant_id', 'public_id', 'reference',
        'property_id', 'room_id', 'guest_id',
        'channel', 'status',
        'check_in', 'check_out', 'nights',
        'adults', 'children',
        'currency', 'base_amount', 'sst_amount', 'tourism_tax_amount',
        'discount_amount', 'total_amount',
        'deposit_pct', 'deposit_amount', 'deposit_paid_at',
        'balance_due_at', 'balance_paid_at', 'full_payment_reminder_at',
        'is_foreigner', 'commission_amount',
        'special_requests', 'source_url', 'source_uid',
        'checked_in_at', 'checked_out_at', 'cancelled_at', 'cancellation_reason',
        'meta',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'deposit_paid_at' => 'datetime',
        'balance_due_at' => 'datetime',
        'balance_paid_at' => 'datetime',
        'full_payment_reminder_at' => 'date',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_foreigner' => 'boolean',
        'base_amount' => 'decimal:2',
        'sst_amount' => 'decimal:2',
        'tourism_tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_pct' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $b) {
            if (empty($b->reference)) {
                $b->reference = 'BK-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
            }
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function bookingGuests(): HasMany
    {
        return $this->hasMany(BookingGuest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    public function isMarketplace(): bool
    {
        return $this->channel === self::CHANNEL_MARKETPLACE;
    }

    public function balanceDue(): float
    {
        $paid = (float) ($this->payments()->where('status', 'succeeded')->sum('amount') ?? 0);
        return round((float) $this->total_amount - $paid, 2);
    }
}
