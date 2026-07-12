<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a tenant owes Tempahlah for one subscription cycle.
 *
 * Deliberately NOT `BelongsToTenant`: the webhook, the payment-return page and
 * the billing command all run without tenant context, and the super-admin needs
 * to read across tenants. Every query scopes on tenant_id explicitly instead.
 *
 * Do not confuse with `Invoice`, which is the tenant's own document issued to
 * their guest for a booking.
 */
class SubscriptionInvoice extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'number', 'status',
        'amount', 'currency', 'period_start', 'period_end',
        'due_at', 'paid_at', 'gateway_provider', 'gateway_bill_id', 'payment_url',
        'reminders_sent', 'last_reminder_at', 'meta',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'amount' => 'decimal:2',
        'reminders_sent' => 'integer',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Still owed and still worth chasing.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_FAILED], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Sequential, human-quotable, and unique — it doubles as the gateway's
     * reference_1 so a callback missing the bill id can still be resolved.
     */
    public static function nextNumber(): string
    {
        $year = now(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->format('y');
        $prefix = "TPL-SUB-{$year}-";

        $last = static::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
