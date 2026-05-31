<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentConversation extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_CLOSED    = 'closed';

    protected $fillable = [
        'tenant_id', 'guest_phone', 'guest_name', 'status',
        'last_inbound_at', 'last_outbound_at', 'message_count',
        'escalated_at', 'escalation_reason', 'locale', 'summary', 'meta',
    ];

    protected $casts = [
        'last_inbound_at'  => 'datetime',
        'last_outbound_at' => 'datetime',
        'escalated_at'     => 'datetime',
        'meta'             => 'array',
    ];

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
