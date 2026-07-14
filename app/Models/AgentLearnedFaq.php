<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single suggestion the agent distilled from real conversations, awaiting the
 * host's review on /dashboard/integrations/agent. See the migration for the
 * full rationale. Platform note: BelongsToTenant, but the distiller runs
 * without web tenant context — it sets tenant_id explicitly and queries
 * withoutGlobalScopes().
 */
class AgentLearnedFaq extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    public const KIND_RECURRING = 'recurring';
    public const KIND_GAP       = 'gap';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'tenant_id', 'question', 'suggested_answer', 'kind', 'status',
        'occurrences', 'example_phrases', 'meta', 'reviewed_at',
    ];

    protected $casts = [
        'occurrences'     => 'integer',
        'example_phrases' => 'array',
        'meta'            => 'array',
        'reviewed_at'     => 'datetime',
    ];

    public function isGap(): bool
    {
        return $this->kind === self::KIND_GAP;
    }
}
