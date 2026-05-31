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
 * Source: Calendarific (https://calendarific.com) — free tier 500
 * calls/month, comprehensive Malaysia coverage including state-level
 * variations. Requires an API key in .env as CALENDARIFIC_API_KEY.
 *
 * (Nager.Date — our first choice — returns HTTP 204 for Malaysia. They
 * just don't cover MY at all.)
 *
 * Cached per-year for 24h server-side so a form open doesn't re-hit
 * the upstream. With the 24h cache, prod traffic is at most ~3 calls
 * per month per tenant — well inside the free tier.
 */
class PublicHolidayController extends Controller
{
    private const UPSTREAM_URL  = 'https://calendarific.com/api/v2/holidays';
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

        $apiKey = config('services.calendarific.key');
        if (! $apiKey) {
            return response()->json([
                'error'    => 'Public-holiday API not configured. Ask admin to set CALENDARIFIC_API_KEY in .env (free key at calendarific.com).',
                'year'     => $year,
                'holidays' => [],
            ], 503);
        }

        $cacheKey = "public_holidays_my_{$year}";

        try {
            $holidays = Cache::remember(
                $cacheKey,
                now()->addHours(self::CACHE_TTL_HRS),
                function () use ($year, $apiKey) {
                    $response = Http::timeout(self::HTTP_TIMEOUT)
                        ->retry(2, 250)
                        ->get(self::UPSTREAM_URL, [
                            'api_key' => $apiKey,
                            'country' => 'MY',
                            'year'    => $year,
                            // Federal national holidays only — state-specific
                            // dates (Sultan birthdays etc.) vary per tenant
                            // location and are noted as manual entry in the UI.
                            'type'    => 'national',
                        ]);

                    if (! $response->successful()) {
                        throw new \RuntimeException("Upstream {$response->status()}: ".substr((string) $response->body(), 0, 200));
                    }

                    $list = data_get($response->json(), 'response.holidays', []);

                    // Normalize to only the fields the UI needs. Dedupe by
                    // (date, name) since Calendarific can list the same
                    // holiday multiple times with different "type" rows.
                    return collect($list)
                        ->map(function ($h) {
                            $date = data_get($h, 'date.iso');
                            // Some Calendarific entries return ISO with time
                            // suffix (e.g. "2026-03-31T00:00:00+08:00") —
                            // strip to plain YYYY-MM-DD.
                            $date = $date ? substr((string) $date, 0, 10) : null;
                            return [
                                'date'       => $date,
                                'local_name' => (string) ($h['name'] ?? ''),
                                'name'       => (string) ($h['name'] ?? ''),
                            ];
                        })
                        ->filter(fn ($h) => ! empty($h['date']) && ! empty($h['local_name']))
                        ->unique(fn ($h) => $h['date'].'|'.$h['local_name'])
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
