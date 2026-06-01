<?php

namespace App\Services\Pricing;

use App\Models\PricingRule;
use App\Models\Room;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

class PricingEngine
{
    public function quoteNight(Room $room, CarbonInterface $date): float
    {
        $price = (float) $room->base_price;

        // Prefer the eager-loaded collection (`->pricingRules` magic
        // property) when present — caller is expected to eager-load
        // via `->with('rooms.pricingRules')` for bulk evaluations like
        // the public-page 365-day pre-compute. Falls back to a single
        // query when not loaded, so unit tests / one-off callers
        // still work. Either way, filter + sort happen in PHP — never
        // re-query the DB per date.
        $rules = $room->pricingRules
            ->where('active', true)
            ->sortBy('priority');

        foreach ($rules as $rule) {
            if ($rule->appliesTo($date)) {
                $price = $rule->applyTo($price);
            }
        }

        return round($price, 2);
    }

    public function quoteRange(Room $room, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        $period = CarbonPeriod::create($checkIn, $checkOut->copy()->subDay());
        $nights = [];
        $total = 0.0;

        foreach ($period as $night) {
            $nightly = $this->quoteNight($room, $night);
            $nights[] = ['date' => $night->toDateString(), 'amount' => $nightly];
            $total += $nightly;
        }

        return [
            'nights' => $nights,
            'total' => round($total, 2),
            'count' => count($nights),
        ];
    }
}
