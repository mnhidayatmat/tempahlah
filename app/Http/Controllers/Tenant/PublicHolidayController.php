<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Malaysian public-holiday lookup for the pricing-rule form.
 *
 * Source: malaysia-holiday.dydxsoft.my — free, no API key required,
 * complete MY coverage including all 13 states + 3 federal territories.
 * Docs: https://malaysia-holiday.dydxsoft.my/api/docs
 *
 * We query WITHOUT a state filter to get the full nationwide list, then
 * dedupe by (date, name) since the upstream lists the same holiday once
 * per observing state (e.g. "Hari Thaipusam" appears 7 times for the 7
 * states that observe it). Each deduped entry carries its state_codes so
 * the UI can show which states observe it.
 *
 * Cached per-year for 24h server-side so a form open doesn't re-hit
 * the upstream.
 */
class PublicHolidayController extends Controller
{
    private const UPSTREAM_URL  = 'https://malaysia-holiday.dydxsoft.my/api/v1/holidays';
    private const CACHE_TTL_HRS = 24;
    private const HTTP_TIMEOUT  = 8;

    public function index(int $year): JsonResponse
    {
        $currentYear = (int) date('Y');
        if ($year < $currentYear - 1 || $year > $currentYear + 2) {
            return response()->json([
                'error' => 'Year out of supported range',
            ], 422);
        }

        $cacheKey = "public_holidays_my_{$year}";

        try {
            $holidays = Cache::remember(
                $cacheKey,
                now()->addHours(self::CACHE_TTL_HRS),
                function () use ($year) {
                    $response = Http::timeout(self::HTTP_TIMEOUT)
                        ->retry(2, 250)
                        ->get(self::UPSTREAM_URL, [
                            'year' => $year,
                        ]);

                    if (! $response->successful()) {
                        throw new \RuntimeException("Upstream {$response->status()}: ".substr((string) $response->body(), 0, 200));
                    }

                    $list = (array) data_get($response->json(), 'data', []);

                    // Dedupe by (date, name) — upstream lists the same
                    // holiday once per observing state. Merge state_codes
                    // so the UI can show coverage at a glance.
                    $byKey = [];
                    foreach ($list as $h) {
                        $date = (string) ($h['date'] ?? '');
                        $name = (string) ($h['name'] ?? '');
                        if ($date === '' || $name === '') continue;

                        $key = $date.'|'.$name;
                        if (! isset($byKey[$key])) {
                            $byKey[$key] = [
                                'date'        => $date,
                                'local_name'  => $name,
                                'name'        => $name,
                                'day_name'    => (string) ($h['day_name'] ?? ''),
                                'state_codes' => [],
                            ];
                        }
                        $byKey[$key]['state_codes'] = array_values(array_unique(array_merge(
                            $byKey[$key]['state_codes'],
                            (array) ($h['state_codes'] ?? [])
                        )));
                    }

                    // Sort by date, then by name for stable order.
                    $deduped = array_values($byKey);
                    usort($deduped, fn ($a, $b) => $a['date'] <=> $b['date'] ?: $a['local_name'] <=> $b['local_name']);
                    return $deduped;
                }
            );

            return response()->json([
                'year'     => $year,
                'count'    => count($holidays),
                'holidays' => $holidays,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Public holidays fetch failed', [
                'year' => $year,
                'err'  => $e->getMessage(),
            ]);
            return response()->json([
                'error'    => 'Could not load holidays right now. Please enter the date manually.',
                'year'     => $year,
                'holidays' => [],
            ], 503);
        }
    }
}
