<?php

namespace App\Services\Agent\Tools;

use App\Models\Property;
use App\Support\Tenancy\BelongsToTenantScope;
use App\Services\Agent\Llm\ToolDefinition;

class GetPropertyInfoTool extends Tool
{
    public function name(): string { return 'get_property_info'; }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: 'Get detailed info about ONE property — address, check-in/out times, house rules, cancellation policy, amenities, rooms. Use this when the guest asks specific questions about a property.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'property_id' => ['type' => 'integer', 'description' => 'The property.id from list_properties'],
                ],
                'required' => ['property_id'],
            ],
        );
    }

    public function execute(array $args, ToolContext $ctx): array
    {
        $id = (int) ($args['property_id'] ?? 0);
        $property = Property::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $ctx->tenant->id)
            ->where('id', $id)
            ->with(['rooms', 'amenities:id,name_bm,name_en,icon,category'])
            ->first();

        if (! $property) {
            return ['error' => 'Property not found for this tenant.'];
        }

        $rooms = $property->rooms->map(fn ($r) => [
            'id'             => $r->id,
            'name'           => $r->name,
            'bed_type'       => $r->bed_type,
            'beds'           => $r->beds,
            'max_adults'     => $r->max_adults,
            'max_children'   => $r->max_children,
            'base_price_rm'  => round((float) $r->base_price, 2),
            'sst_applicable' => (bool) $r->sst_applicable,
        ])->all();

        $amenities = $property->amenities->map(fn ($a) =>
            $ctx->locale === 'ms' ? ($a->name_bm ?: $a->name_en) : ($a->name_en ?: $a->name_bm)
        )->filter()->values()->all();

        return [
            'id'                   => $property->id,
            'name'                 => $property->name,
            'property_type'        => $property->property_type,
            'description'          => $ctx->locale === 'ms'
                ? ($property->description_bm ?: $property->description_en)
                : ($property->description_en ?: $property->description_bm),
            'address' => trim(implode(', ', array_filter([
                $property->address_line1,
                $property->address_line2,
                $property->postcode.' '.$property->city,
                $property->state,
                $property->country,
            ]))),
            'city'                 => $property->city,
            'state'                => $property->state,
            'has_coords'           => $property->lat && $property->lng,
            'check_in_time'        => $property->check_in_time,
            'check_out_time'       => $property->check_out_time,
            'house_rules'          => $property->house_rules,
            'cancellation_policy'  => $property->cancellation_policy,
            'pricing_mode'         => $property->pricing_mode,
            'starting_nightly_rm'  => round($property->startingNightlyRate(), 2),
            'rooms'                => $rooms,
            'amenities'            => $amenities,
        ];
    }
}
