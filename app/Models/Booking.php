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

    /**
     * Host-selectable status labels (single source of truth for the status
     * dropdowns). `confirmed` reads as "Paid Booking Fee" because in the
     * Tempahlah flow a confirmed booking is one whose booking fee has been
     * paid. `pending` is intentionally NOT offered here — hosts don't manually
     * put a booking back into the unpaid state; it only ever arises internally
     * from the online flow before the Toyyibpay fee clears. The stored values
     * are unchanged.
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_CONFIRMED   => __('Paid Booking Fee'),
            self::STATUS_CHECKED_IN  => __('Checked in'),
            self::STATUS_CHECKED_OUT => __('Checked out'),
            self::STATUS_CANCELLED   => __('Cancelled'),
            self::STATUS_NO_SHOW     => __('No-show'),
        ];
    }

    /**
     * Resolve ANY status to a display label. "Pay Booking Fee" has been
     * removed from the system entirely — `pending` (the transient state an
     * online Toyyibpay booking sits in before its fee clears) now resolves to
     * the same "Paid Booking Fee" label as `confirmed`, so the unpaid wording
     * never surfaces anywhere. Only "Paid Booking Fee" remains.
     */
    public static function statusLabel(?string $status): string
    {
        $all = self::statusLabels() + [self::STATUS_PENDING => __('Paid Booking Fee')];

        return $all[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    /**
     * The single, merged "Payment Status" the host sees + edits — it folds the
     * old separate Status and Payment columns into one value. The first three
     * are payment-driven (derived from the deposit/balance timestamps); the
     * rest are lifecycle states carried on the `status` column. This array is
     * the single source of truth for the edit-form dropdown.
     */
    public static function paymentStatusOptions(): array
    {
        return [
            'pending'                => __('Pending'),
            'paid_booking_fee'       => __('Paid Booking Fee'),
            'paid_full'              => __('Paid Full Payment'),
            self::STATUS_CHECKED_IN  => __('Checked in'),
            self::STATUS_CHECKED_OUT => __('Checked out'),
            self::STATUS_CANCELLED   => __('Cancelled'),
            self::STATUS_NO_SHOW     => __('No-show'),
        ];
    }

    /**
     * Resolve THIS booking to its merged payment-status key. Lifecycle states
     * (checked-in/out, cancelled, no-show) take precedence once reached;
     * otherwise the key is derived from how much has been paid.
     */
    public function paymentStatusKey(): string
    {
        // Lifecycle states win once reached.
        return match ($this->status) {
            self::STATUS_CHECKED_IN  => self::STATUS_CHECKED_IN,
            self::STATUS_CHECKED_OUT => self::STATUS_CHECKED_OUT,
            self::STATUS_CANCELLED   => self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW     => self::STATUS_NO_SHOW,
            // Otherwise derive from how much has been paid. A `confirmed`
            // booking counts as at least "Paid Booking Fee" even without a
            // stamped deposit date — in this system a booking is confirmed
            // precisely when its booking fee clears. Only an unpaid `pending`
            // hold reads "Pending".
            default => $this->balance_paid_at
                ? 'paid_full'
                : (($this->deposit_paid_at || $this->status === self::STATUS_CONFIRMED)
                    ? 'paid_booking_fee'
                    : 'pending'),
        };
    }

    public function paymentStatusLabel(): string
    {
        $key = $this->paymentStatusKey();

        return self::paymentStatusOptions()[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /** Pill colour variant for the merged payment-status badge. */
    public function paymentStatusVariant(): string
    {
        return match ($this->paymentStatusKey()) {
            'paid_full'              => 'ok',
            'paid_booking_fee'       => 'info',
            'pending'                => 'warn',
            self::STATUS_CHECKED_IN  => 'primary',
            self::STATUS_CHECKED_OUT => 'default',
            self::STATUS_CANCELLED, self::STATUS_NO_SHOW => 'err',
            default                  => 'default',
        };
    }

    /**
     * Translate a chosen merged payment-status key into the concrete column
     * updates (lifecycle `status` + payment timestamps) to persist. Existing
     * timestamps are preserved where possible so re-saving doesn't move a paid
     * date. Caller merges the returned array into update().
     */
    public function paymentStatusUpdates(string $key, ?\Carbon\Carbon $now = null): array
    {
        $now ??= now();

        return match ($key) {
            'pending' => [
                'status'          => self::STATUS_PENDING,
                'deposit_paid_at' => null,
                'balance_paid_at' => null,
                'cancelled_at'    => null,
            ],
            'paid_booking_fee' => [
                'status'          => self::STATUS_CONFIRMED,
                'deposit_paid_at' => $this->deposit_paid_at ?? $now,
                'balance_paid_at' => null,
                'cancelled_at'    => null,
            ],
            'paid_full' => [
                'status'          => self::STATUS_CONFIRMED,
                'deposit_paid_at' => $this->deposit_paid_at ?? $now,
                'balance_paid_at' => $this->balance_paid_at ?? $now,
                'cancelled_at'    => null,
            ],
            self::STATUS_CHECKED_IN => [
                'status'        => self::STATUS_CHECKED_IN,
                'checked_in_at' => $this->checked_in_at ?? $now,
                'cancelled_at'  => null,
            ],
            self::STATUS_CHECKED_OUT => [
                'status'         => self::STATUS_CHECKED_OUT,
                'checked_in_at'  => $this->checked_in_at ?? $now,
                'checked_out_at' => $this->checked_out_at ?? $now,
                'cancelled_at'   => null,
            ],
            self::STATUS_CANCELLED => [
                'status'       => self::STATUS_CANCELLED,
                'cancelled_at' => $this->cancelled_at ?? $now,
            ],
            self::STATUS_NO_SHOW => [
                'status'       => self::STATUS_NO_SHOW,
                'cancelled_at' => $this->cancelled_at ?? $now,
            ],
            default => [],
        };
    }

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
        'booking_fee_amount',
        'discount_amount', 'total_amount',
        'deposit_pct', 'deposit_amount', 'deposit_paid_at',
        'balance_due_at', 'balance_paid_at', 'fee_due_at',
        'full_payment_reminder_at', 'fee_reminder_sent_at', 'full_payment_reminder_sent_at',
        'checkin_instructions_sent_at',
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
        'fee_due_at' => 'datetime',
        'full_payment_reminder_at' => 'date',
        'fee_reminder_sent_at' => 'datetime',
        'full_payment_reminder_sent_at' => 'datetime',
        'checkin_instructions_sent_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_foreigner' => 'boolean',
        'base_amount' => 'decimal:2',
        'sst_amount' => 'decimal:2',
        'tourism_tax_amount' => 'decimal:2',
        'booking_fee_amount' => 'decimal:2',
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

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderByDesc('created_at');
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

    /**
     * Signed magic-link URL to the guest-facing booking detail page on the
     * tenant's own subdomain (e.g. wafahomestay.tempahlah.com/booking/{ulid}).
     *
     * Expires 90 days after check-out — long enough to cover refund / complaint
     * windows without leaving an indefinitely-live link in the guest's inbox.
     *
     * Embedded in every BookingConfirmation email + WhatsApp message so the
     * guest can return to their booking without a password.
     */
    public function guestPortalUrl(): string
    {
        $slug = $this->tenant?->slug;
        if (! $slug) {
            // Fallback to apex — shouldn't happen in practice (every booking
            // has a tenant), but keeps the link generator safe.
            return config('app.url');
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'tenant-public.booking.show',
            $this->check_out->copy()->addDays(90),
            ['tenant_slug' => $slug, 'booking' => $this->public_id],
        );
    }
}
