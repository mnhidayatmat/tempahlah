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

        $rules = $room->pricingRules()
            ->where('active', true)
            ->orderBy('priority')
            ->get();

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
