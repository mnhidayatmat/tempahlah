<?php

namespace App\Services\Agent\Tools;

use App\Models\Property;
use App\Support\Tenancy\BelongsToTenantScope;
use App\Services\Agent\Llm\ToolDefinition;

class ListPropertiesTool extends Tool
{
    public function name(): string { return 'list_properties'; }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "List this homestay business's active properties (id, name, city, state, starting nightly rate). Call this when the guest asks about your homestays in general or hasn't specified which property they're interested in.",
            schema: [
                'type' => 'object',
                'properties' => new \stdClass(),
            ],
        );
    }

    public function execute(array $args, ToolContext $ctx): array
    {
        $rows = Property::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $ctx->tenant->id)
            ->where('status', Property::STATUS_ACTIVE)
            ->with(['rooms:id,property_id,base_price,max_adults,max_children'])
            ->limit(20)
            ->get();

        $properties = $rows->map(fn (Property $p) => [
            'id'                   => $p->id,
            'name'                 => $p->name,
            'city'                 => $p->city,
            'state'                => $p->state,
            'starting_nightly_rm'  => round($p->startingNightlyRate(), 2),
            'rooms_count'          => $p->rooms->count(),
            'sleeps_total'         => (int) $p->rooms->sum(fn ($r) => ($r->max_adults ?? 0) + ($r->max_children ?? 0)),
            'pricing_mode'         => $p->pricing_mode,
            'description'          => $ctx->locale === 'ms'
                ? ($p->description_bm ?: $p->description_en)
                : ($p->description_en ?: $p->description_bm),
        ])->all();

        return [
            'count' => count($properties),
            'properties' => $properties,
        ];
    }
}
