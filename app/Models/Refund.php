<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasUlidPublicId;

    // Lifecycle states
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    // Why the refund exists
    public const REASON_CHECKOUT_COMPLETE = 'checkout_complete';
    public const REASON_CANCELLATION      = 'cancellation';
    public const REASON_DAMAGE_DEDUCTION  = 'damage_deduction';
    public const REASON_GOODWILL          = 'goodwill';
    public const REASON_OTHER             = 'other';

    public const REASONS = [
        self::REASON_CHECKOUT_COMPLETE,
        self::REASON_CANCELLATION,
        self::REASON_DAMAGE_DEDUCTION,
        self::REASON_GOODWILL,
        self::REASON_OTHER,
    ];

    // How the host returned the money
    public const METHOD_BANK_TRANSFER       = 'bank_transfer';
    public const METHOD_DUITNOW             = 'duitnow';
    public const METHOD_EWALLET             = 'ewallet';
    public const METHOD_CASH                = 'cash';
    public const METHOD_TOYYIBPAY_DASHBOARD = 'toyyibpay_dashboard';

    public const METHODS = [
        self::METHOD_DUITNOW,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_EWALLET,
        self::METHOD_CASH,
        self::METHOD_TOYYIBPAY_DASHBOARD,
    ];

    protected $fillable = [
        'public_id', 'tenant_id', 'booking_id', 'payment_id',
        'amount', 'currency', 'reason', 'status', 'method',
        'external_reference', 'notes', 'failure_reason',
        'requested_at', 'processed_at', 'processed_by_user_id',
        'bank_name', 'bank_account_number', 'bank_account_holder',
        'bank_details_requested_at', 'bank_details_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            // Encrypted at rest — the guest's account number is sensitive.
            'bank_account_number'        => 'encrypted',
            'bank_details_requested_at'  => 'datetime',
            'bank_details_submitted_at'  => 'datetime',
        ];
    }

    /** True once the guest has submitted their bank details for this refund. */
    public function bankDetailsSubmitted(): bool
    {
        return $this->bank_details_submitted_at !== null;
    }

    /**
     * Signed magic-link the guest taps to submit their bank details. No
     * password — the `signed` middleware verifies the HMAC. Expires 60 days out.
     */
    public function bankFormUrl(): string
    {
        $slug = $this->booking?->tenant?->slug ?? $this->tenant?->slug;
        if (! $slug) {
            return config('app.url');
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'tenant-public.refund.bank.show',
            now()->addDays(60),
            ['tenant_slug' => $slug, 'refund' => $this->public_id],
        );
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }
}
