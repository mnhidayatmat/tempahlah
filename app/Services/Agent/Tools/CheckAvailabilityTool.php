<?php

namespace App\Services\Agent\Tools;

use App\Models\Property;
use App\Support\Tenancy\BelongsToTenantScope;
use App\Services\Agent\Llm\ToolDefinition;
use App\Services\Booking\AvailabilityService;
use Carbon\Carbon;

class CheckAvailabilityTool extends Tool
{
    public function __construct(protected AvailabilityService $availability) {}

    public function name(): string { return 'check_availability'; }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: 'Check whether a property has any room available for the given check-in and check-out dates. Always call this BEFORE telling the guest something is available. Dates use ISO format YYYY-MM-DD. check_out must be at least 1 day after check_in.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'property_id' => ['type' => 'integer', 'description' => 'The property.id from list_properties'],
                    'check_in'    => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'check_out'   => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                ],
                'required' => ['property_id', 'check_in', 'check_out'],
            ],
        );
    }

    public function execute(array $args, ToolContext $ctx): array
    {
        try {
            $checkIn  = Carbon::parse($args['check_in'] ?? '');
            $checkOut = Carbon::parse($args['check_out'] ?? '');
        } catch (\Throwable) {
            return ['error' => 'Invalid date format. Use YYYY-MM-DD.'];
        }
        if ($checkIn->gte($checkOut)) {
            return ['error' => 'check_out must be after check_in (minimum 1 night).'];
        }
        if ($checkIn->isPast()) {
            return ['error' => 'check_in is in the past — ask the guest for new dates.'];
        }

        $property = Property::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $ctx->tenant->id)
            ->where('id', (int) $args['property_id'])
            ->with('rooms')
            ->first();

        if (! $property) {
            return ['error' => 'Property not found for this tenant.'];
        }

        $rooms = [];
        foreach ($property->rooms as $room) {
            $available = $this->availability->isAvailable($room, $checkIn, $checkOut);
            $rooms[] = [
                'id'             => $room->id,
                'name'           => $room->name,
                'sleeps'         => (int) ($room->max_adults + $room->max_children),
                'base_price_rm'  => round((float) $room->base_price, 2),
                'available'      => $available,
            ];
        }

        $nights = $checkIn->diffInDays($checkOut);
        $anyAvailable = collect($rooms)->contains('available', true);

        return [
            'property_id'   => $property->id,
            'property_name' => $property->name,
            'check_in'      => $checkIn->toDateString(),
            'check_out'     => $checkOut->toDateString(),
            'nights'        => $nights,
            'any_available' => $anyAvailable,
            'rooms'         => $rooms,
        ];
    }
}
