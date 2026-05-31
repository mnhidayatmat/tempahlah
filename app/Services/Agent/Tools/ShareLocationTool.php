<?php

namespace App\Services\Agent\Tools;

use App\Models\Property;
use App\Services\Agent\Llm\ToolDefinition;

class ShareLocationTool extends Tool
{
    public function name(): string { return 'share_location'; }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: 'Get the Google Maps link + full address for a property. Use this when the guest asks "where is it?", "how to get there?", or "share location". Returns a string the LLM can include in the chat reply.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'property_id' => ['type' => 'integer'],
                ],
                'required' => ['property_id'],
            ],
        );
    }

    public function execute(array $args, ToolContext $ctx): array
    {
        $property = Property::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenant->id)
            ->where('id', (int) ($args['property_id'] ?? 0))
            ->first();

        if (! $property) {
            return ['error' => 'Property not found for this tenant.'];
        }

        $address = trim(implode(', ', array_filter([
            $property->address_line1,
            $property->address_line2,
            $property->postcode.' '.$property->city,
            $property->state,
            $property->country,
        ])));

        $mapsLink = ($property->lat && $property->lng)
            ? "https://www.google.com/maps/search/?api=1&query={$property->lat},{$property->lng}"
            : 'https://www.google.com/maps/search/?api=1&query='.urlencode($address);

        return [
            'property_name' => $property->name,
            'address'       => $address,
            'maps_link'     => $mapsLink,
            'has_coords'    => (bool) ($property->lat && $property->lng),
        ];
    }
}
