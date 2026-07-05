<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceListing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * SEO location landing pages: /homestay/{state} and /homestay/{state}/{town}.
 * Server-rendered, pre-filtered marketplace grid with unique title/meta/H1 and
 * JSON-LD, targeting queries like "homestay di Cameron Highlands".
 */
class LocationController extends Controller
{
    /** Malaysian states (display names); URL slugs are Str::slug of these. */
    protected array $states = [
        'Selangor', 'Kuala Lumpur', 'Penang', 'Sabah', 'Sarawak', 'Johor',
        'Pahang', 'Terengganu', 'Kelantan', 'Kedah', 'Perak',
        'Negeri Sembilan', 'Melaka', 'Perlis', 'Putrajaya', 'Labuan',
    ];

    public function show(Request $request, string $state, ?string $town = null): View
    {
        $stateName = $this->resolveState($state);
        abort_if($stateName === null, 404);

        $townName = null;
        if ($town !== null) {
            // A town page only exists if a real city with listings matches —
            // avoids thin/empty auto-generated town pages.
            $townName = $this->resolveTown($stateName, $town);
            abort_if($townName === null, 404);
        }

        $filtered = fn () => MarketplaceListing::query()
            ->published()
            ->where('state', $stateName)
            ->when($townName, fn ($q) => $q->where('city', $townName));

        $listings = $filtered()
            ->orderByDesc('is_featured')
            ->orderByDesc('rating_avg')
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        $covers = ['beach', 'highland', 'kampung', 'heritage', 'city'];
        foreach ($listings as $l) {
            $l->cover_kind = $covers[crc32((string) $l->id) % count($covers)];
        }

        // Towns in this state (internal links) — only on the state page.
        $towns = $town === null
            ? MarketplaceListing::query()->published()->where('state', $stateName)
                ->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')
            : collect();

        return view('marketplace.location', [
            'listings' => $listings,
            'stateName' => $stateName,
            'stateSlug' => Str::slug($stateName),
            'townName' => $townName,
            'locationName' => $townName ? "{$townName}, {$stateName}" : $stateName,
            'towns' => $towns,
            'total' => $filtered()->count(),
        ]);
    }

    protected function resolveState(string $slug): ?string
    {
        foreach ($this->states as $s) {
            if (Str::slug($s) === $slug) {
                return $s;
            }
        }

        return null;
    }

    protected function resolveTown(string $stateName, string $slug): ?string
    {
        return MarketplaceListing::query()->published()
            ->where('state', $stateName)->whereNotNull('city')
            ->distinct()->pluck('city')
            ->first(fn ($c) => Str::slug($c) === $slug);
    }
}
