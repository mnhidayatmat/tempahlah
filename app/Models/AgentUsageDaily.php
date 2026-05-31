<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AgentUsageDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'agent_usage_daily';

    protected $fillable = [
        'tenant_id', 'day', 'provider', 'model',
        'inbound_count', 'reply_count', 'tool_calls', 'tokens_in', 'tokens_out',
    ];

    protected $casts = [
        'day' => 'date',
    ];

    public static function todayFor(int $tenantId): self
    {
        return self::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'day' => Carbon::today()->toDateString()],
        );
    }
}
