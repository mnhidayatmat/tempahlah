<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
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
}
