<?php

namespace Database\Seeders;

use App\Models\MarketplaceListing;
use App\Models\Property;
use Illuminate\Database\Seeder;

/**
 * Backfills homestay location so state/district match the marketplace filters
 * (state = `state`, district = `city`), for records created before the
 * Negeri → Daerah dropdown landed (2026-07-12).
 *
 * Two rules, both idempotent — re-running after everything is clean is a no-op:
 *   1. City IS a known district (config/districts.php) but the state is blank
 *      or invalid → infer + set the state. (e.g. Wafa: city "Kluang", state "" → Johor)
 *   2. City is a town/area that isn't itself a district → map it to its official
 *      district via CITY_ALIASES. (e.g. Biena Sana: "Ampang", Selangor → Hulu Langat)
 *
 * Any affected property's marketplace listing (published or not) is re-synced so
 * its denormalized state/city stay consistent.
 *
 * Run: php artisan db:seed --class=FixHomestayDistrictsSeeder
 * NOT registered in DatabaseSeeder — it's a one-off correction kept for the record.
 */
class FixHomestayDistrictsSeeder extends Seeder
{
    /**
     * Town/area → official district, for cities that are not themselves a
     * district. Keyed by lowercased city. Extend as more are discovered.
     *
     * @var array<string, array{state: string, district: string}>
     */
    private const CITY_ALIASES = [
        'ampang' => ['state' => 'Selangor', 'district' => 'Hulu Langat'],
    ];

    public function run(): void
    {
        $map = config('districts');
        $validStates = array_keys($map);

        // Lowercased district name → its {state, district}.
        $districtToState = [];
        foreach ($map as $state => $districts) {
            foreach ($districts as $d) {
                $districtToState[mb_strtolower($d)] = ['state' => $state, 'district' => $d];
            }
        }

        $fixed = 0;

        foreach (Property::withoutGlobalScopes()->get() as $property) {
            $cityKey = mb_strtolower(trim((string) $property->city));
            $target = null;

            if (isset(self::CITY_ALIASES[$cityKey])) {
                // Rule 2 — always apply (the city itself is not a district).
                $target = self::CITY_ALIASES[$cityKey];
            } elseif (isset($districtToState[$cityKey]) && ! in_array($property->state, $validStates, true)) {
                // Rule 1 — city is a district, only backfill a blank/invalid state.
                $target = $districtToState[$cityKey];
            }

            if (! $target) {
                continue;
            }

            if ($property->state === $target['state'] && $property->city === $target['district']) {
                continue; // already correct — idempotent
            }

            Property::withoutGlobalScopes()
                ->whereKey($property->id)
                ->update(['state' => $target['state'], 'city' => $target['district']]);

            MarketplaceListing::where('property_id', $property->id)
                ->update(['state' => $target['state'], 'city' => $target['district']]);

            $fixed++;
            $this->command?->line("  P#{$property->id}: [{$property->city}] → {$target['state']} / {$target['district']}");
        }

        $this->command?->info("District backfill complete: {$fixed} property/properties corrected.");
    }
}
