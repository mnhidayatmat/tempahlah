<?php

namespace App\Services\Agent\Tools;

use App\Models\Room;
use App\Support\Tenancy\BelongsToTenantScope;
use App\Services\Agent\Llm\ToolDefinition;
use App\Services\Booking\AvailabilityService;
use App\Services\Pricing\PricingEngine;
use Carbon\Carbon;

class GetQuoteTool extends Tool
{
    public function __construct(
        protected PricingEngine $pricing,
        protected AvailabilityService $availability,
    ) {}

    public function name(): string { return 'get_quote'; }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: 'Compute a full nightly + total quote for a specific room and date range, including SST (if registered), Malaysian tourism tax (RM 10 / night for foreign guests), and any per-booking flat fee the host has configured (e.g. cleaning fee). Always call this before quoting a total to the guest — never invent prices.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'room_id'       => ['type' => 'integer', 'description' => 'Room.id (from check_availability or get_property_info)'],
                    'check_in'      => ['type' => 'string',  'description' => 'YYYY-MM-DD'],
                    'check_out'     => ['type' => 'string',  'description' => 'YYYY-MM-DD'],
                    'guests'        => ['type' => 'integer', 'description' => 'Total guest count', 'minimum' => 1],
                    'foreign_guest' => ['type' => 'boolean', 'description' => 'true if any guest is not Malaysian (triggers RM 10/night tourism tax). NEVER ask the guest their nationality/warganegara to fill this in — leave it unset (assume Malaysian) unless the guest has already volunteered that they are a foreign visitor.'],
                ],
                'required' => ['room_id', 'check_in', 'check_out', 'guests'],
            ],
        );
    }

    public function execute(array $args, ToolContext $ctx): array
    {
        try {
            $checkIn  = Carbon::parse($args['check_in']  ?? '');
            $checkOut = Carbon::parse($args['check_out'] ?? '');
        } catch (\Throwable) {
            return ['error' => 'Invalid date format. Use YYYY-MM-DD.'];
        }
        if ($checkIn->gte($checkOut)) {
            return ['error' => 'check_out must be after check_in.'];
        }

        $room = Room::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->whereHas('property', fn ($q) => $q->where('tenant_id', $ctx->tenant->id))
            ->where('id', (int) $args['room_id'])
            ->with('property')
            ->first();

        if (! $room) {
            return ['error' => 'Room not found for this tenant.'];
        }

        $available = $this->availability->isAvailable($room, $checkIn, $checkOut);
        $quote = $this->pricing->quoteRange($room, $checkIn, $checkOut);

        $sstRate = $room->sst_applicable && $ctx->tenant->sst_registered ? (float) $ctx->tenant->sst_rate : 0;
        $sstAmount = round($quote['total'] * $sstRate, 2);

        $foreign = (bool) ($args['foreign_guest'] ?? false);
        $guests  = max(1, (int) ($args['guests'] ?? 1));
        $tourismTax = $foreign ? 10.0 * $quote['count'] : 0.0;

        // Per-booking flat fee (cleaning fee, service fee, etc.). Pulled
        // from the property the room belongs to. 0 if the host hasn't set
        // one. Mirrors CreateBooking's math.
        $bookingFee = round((float) ($room->property->booking_fee_amount ?? 0), 2);
        $bookingFeeLabel = (string) ($room->property->booking_fee_label ?? '');

        $total = round($quote['total'] + $sstAmount + $tourismTax + $bookingFee, 2);
        $deposit = round($total * 0.20, 2);

        $maxSleeps = (int) ($room->max_adults + $room->max_children);
        $exceedsCapacity = $guests > $maxSleeps;

        return [
            'room_id'         => $room->id,
            'room_name'       => $room->name,
            'property_name'   => $room->property->name,
            'available'       => $available,
            'check_in'        => $checkIn->toDateString(),
            'check_out'       => $checkOut->toDateString(),
            'nights'          => $quote['count'],
            'guests'          => $guests,
            'max_sleeps'      => $maxSleeps,
            'exceeds_capacity'=> $exceedsCapacity,
            'currency'        => 'MYR',
            'base_total_rm'   => $quote['total'],
            'sst_rate'        => $sstRate,
            'sst_amount_rm'   => $sstAmount,
            'tourism_tax_rm'  => $tourismTax,
            'booking_fee_rm'  => $bookingFee,
            'booking_fee_label' => $bookingFee > 0 ? ($bookingFeeLabel ?: 'Booking fee') : '',
            'total_rm'        => $total,
            'deposit_rm'      => $deposit,
            'deposit_pct'     => 20,
            'per_night'       => array_slice($quote['nights'], 0, 14),
        ];
    }
}
