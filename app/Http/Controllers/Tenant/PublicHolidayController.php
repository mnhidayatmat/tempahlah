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
 * Source: Nager.Date public API (https://date.nager.at) — free, no key
 * required, covers Malaysian national holidays. State-specific dates
 * (Sultan birthdays etc.) may be missing; tenants can still enter those
 * manually.
 *
 * Cached per-year for 24h so we don't hit the upstream API every form open.
 */
class PublicHolidayController extends Controller
{
    private const UPSTREAM_URL  = 'https://date.nager.at/api/v3/PublicHolidays/%d/MY';
    private const CACHE_TTL_HRS = 24;
    private const HTTP_TIMEOUT  = 5;

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
                    $url = sprintf(self::UPSTREAM_URL, $year);
                    $response = Http::timeout(self::HTTP_TIMEOUT)
                        ->retry(2, 200)
                        ->get($url);

                    if (! $response->successful()) {
                        throw new \RuntimeException("Upstream {$response->status()}");
                    }

                    // Normalize to only the fields the UI needs.
                    return collect($response->json())
                        ->map(fn ($h) => [
                            'date'       => $h['date'] ?? null,
                            'local_name' => $h['localName'] ?? ($h['name'] ?? ''),
                            'name'       => $h['name'] ?? '',
                        ])
                        ->filter(fn ($h) => ! empty($h['date']))
                        ->sortBy('date')
                        ->values()
                        ->all();
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
