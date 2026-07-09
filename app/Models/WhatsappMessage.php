<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WhatsappMessage extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const DIRECTION_OUT = 'out';
    public const DIRECTION_IN  = 'in';

    public const KIND_MANUAL       = 'manual';
    public const KIND_CONFIRMATION = 'confirmation';
    public const KIND_REMINDER     = 'reminder';
    public const KIND_CHECKIN      = 'checkin';
    public const KIND_CHECKOUT     = 'checkout';
    public const KIND_LOCATION     = 'location';
    public const KIND_INVOICE      = 'invoice';
    public const KIND_RECEIPT      = 'receipt';
    // Free tier: no Invoice record exists, so the guest gets a plain booking
    // summary carrying the host's payment instructions instead of an invoice.
    public const KIND_BOOKING_RECEIVED = 'booking_received';
    public const KIND_CANCELLATION = 'cancellation';
    public const KIND_TEST         = 'test';
    public const KIND_INBOUND      = 'inbound';
    public const KIND_AGENT_REPLY  = 'agent_reply';

    public const STATUS_QUEUED       = 'queued';
    public const STATUS_SENDING      = 'sending';
    public const STATUS_SENT         = 'sent';
    public const STATUS_DELIVERED    = 'delivered';
    public const STATUS_READ         = 'read';
    public const STATUS_FAILED       = 'failed';
    public const STATUS_BLOCKED      = 'blocked_by_guard';
    public const STATUS_RATE_LIMITED = 'rate_limited';

    protected $fillable = [
        'public_id', 'tenant_id', 'booking_id', 'user_id',
        'direction', 'kind',
        'recipient_phone', 'recipient_name',
        'body', 'media_url', 'media_kind', 'template_key',
        'status', 'sidecar_message_id', 'error',
        'queued_at', 'sent_at', 'delivered_at', 'read_at',
    ];

    protected $casts = [
        'queued_at'    => 'datetime',
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $msg) {
            if (! $msg->public_id) {
                $msg->public_id = (string) Str::ulid();
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
