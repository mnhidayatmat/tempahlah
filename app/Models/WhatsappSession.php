<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappSession extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_QR_PENDING   = 'qr_pending';
    public const STATUS_CONNECTING   = 'connecting';
    public const STATUS_CONNECTED    = 'connected';
    public const STATUS_EXPIRED      = 'expired';
    public const STATUS_BANNED       = 'banned';
    public const STATUS_ERROR        = 'error';

    public const PREF_DEFAULTS = [
        'auto_confirmation'    => true,
        'auto_reminder'        => true,
        'auto_checkin'         => true,
        'reminder_days_before' => 3,
        'checkin_hours_before' => 24,
        'rate_limit_seconds'   => 8,
        'opt_out_keywords'     => ['STOP', 'BERHENTI', 'UNSUBSCRIBE'],
    ];

    protected $fillable = [
        'tenant_id', 'status', 'phone_e164', 'push_name',
        'qr_payload', 'qr_generated_at',
        'connected_at', 'disconnected_at', 'last_seen_at', 'last_error',
        'daily_sent_count', 'daily_count_reset_at',
        'session_blob_path', 'prefs',
    ];

    protected $casts = [
        'qr_generated_at'      => 'datetime',
        'connected_at'         => 'datetime',
        'disconnected_at'      => 'datetime',
        'last_seen_at'         => 'datetime',
        'daily_count_reset_at' => 'datetime',
        'prefs'                => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    /**
     * Resolve a preference key, falling back to PREF_DEFAULTS.
     */
    public function pref(string $key): mixed
    {
        return data_get($this->prefs, $key, self::PREF_DEFAULTS[$key] ?? null);
    }
}
