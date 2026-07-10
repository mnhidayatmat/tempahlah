<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use App\Services\Payments\AttemptOutcome;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlidPublicId;

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_BALANCE = 'balance';
    public const TYPE_FULL = 'full';
    public const TYPE_REFUND = 'refund';

    public const METHOD_MANUAL = 'manual';
    public const METHOD_TOYYIBPAY = 'toyyibpay';
    public const METHOD_BILLPLZ = 'billplz';
    public const METHOD_SECUREPAY = 'securepay';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'tenant_id', 'public_id', 'booking_id',
        'type', 'method', 'gateway_provider', 'gateway_ref',
        'currency', 'amount', 'gateway_fee', 'platform_fee', 'net_to_tenant',
        'status', 'paid_at', 'refunded_at', 'meta',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_to_tenant' => 'decimal:2',
        'meta' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Record that the gateway declined an attempt on this payment.
     *
     * The status deliberately stays `processing`: at all three gateways the
     * bill remains payable after a decline, so the guest reopens the same
     * payment_url. Closing the row here would miss the reuse guard in
     * CreateGatewayBill and mint a SECOND live bill for one booking.
     */
    public function markAttemptFailed(): void
    {
        if ($this->status === self::STATUS_SUCCEEDED) {
            return;
        }

        $this->update(['meta' => array_merge($this->meta ?? [], [
            'last_attempt' => [
                'outcome' => AttemptOutcome::Failed->value,
                'at' => now()->toIso8601String(),
            ],
        ])]);
    }

    /** True when the gateway has told us an attempt on this payment was declined. */
    public function attemptFailed(): bool
    {
        if ($this->status === self::STATUS_SUCCEEDED) {
            return false;
        }

        // Rows written before attempts were tracked separately from status.
        if ($this->status === self::STATUS_FAILED) {
            return true;
        }

        return ($this->meta['last_attempt']['outcome'] ?? null) === AttemptOutcome::Failed->value;
    }

    /** The gateway link this payment can still be paid on, if one was minted. */
    public function payUrl(): ?string
    {
        $url = $this->meta['payment_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
