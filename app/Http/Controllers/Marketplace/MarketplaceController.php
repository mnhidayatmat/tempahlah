<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MarketplaceListing;
use App\Support\Tenancy\BelongsToTenantScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function search(Request $request): View
    {
        $listings = MarketplaceListing::query()
            ->published()
            ->when($request->input('city'), fn ($q, $v) => $q->where('city', 'like', "%$v%"))
            ->when($request->input('state'), fn ($q, $v) => $q->where('state', $v))
            ->when($request->input('q'), fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('title_bm', 'like', "%$v%")->orWhere('title_en', 'like', "%$v%");
            }))
            ->when($request->input('min_price'), fn ($q, $v) => $q->where('base_price_min', '>=', $v))
            ->when($request->input('max_price'), fn ($q, $v) => $q->where('base_price_max', '<=', $v))
            ->orderByDesc('is_featured')
            ->orderByDesc('rating_avg')
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        $covers = ['beach', 'highland', 'kampung', 'heritage', 'city'];
        foreach ($listings as $listing) {
            $listing->cover_kind = $covers[crc32((string) $listing->id) % count($covers)];
        }

        $facets = [
            'all'      => MarketplaceListing::query()->published()->count(),
            'beach'    => MarketplaceListing::query()->published()->where(fn ($q) => $q->where('city', 'like', '%pantai%')->orWhere('search_keywords', 'like', '%beach%'))->count(),
            'highland' => MarketplaceListing::query()->published()->where(fn ($q) => $q->where('state', 'Pahang')->orWhere('search_keywords', 'like', '%highland%'))->count(),
            'kampung'  => MarketplaceListing::query()->published()->where('search_keywords', 'like', '%kampung%')->count(),
            'heritage' => MarketplaceListing::query()->published()->where(fn ($q) => $q->where('city', 'like', '%george town%')->orWhere('search_keywords', 'like', '%heritage%'))->count(),
        ];

        return view('marketplace.search', [
            'listings' => $listings,
            'filters' => $request->only(['city', 'state', 'q', 'min_price', 'max_price', 'cover']),
            'facets' => $facets,
        ]);
    }

    public function show(MarketplaceListing $listing): View
    {
        abort_unless($listing->status === MarketplaceListing::STATUS_ACTIVE, 404);
        $listing->load('property.rooms', 'property.photos', 'tenant');

        $covers = ['beach', 'highland', 'kampung', 'heritage', 'city'];
        $coverKind = $covers[crc32((string) $listing->id) % count($covers)];

        $bookedDates = Booking::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('property_id', $listing->property_id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->where('check_out', '>=', now()->startOfDay())
            ->get(['check_in', 'check_out'])
            ->flatMap(function ($b) {
                $dates = [];
                $cursor = $b->check_in->copy();
                while ($cursor->lt($b->check_out)) {
                    $dates[] = $cursor->toDateString();
                    $cursor->addDay();
                }
                return $dates;
            })
            ->unique()
            ->values()
            ->all();

        $contactPhone = preg_replace('/\D/', '', $listing->property->business_phone ?? $listing->tenant->business_phone ?? '');

        return view('marketplace.show', [
            'listing' => $listing,
            'property' => $listing->property,
            'rooms' => $listing->property->rooms,
            'bookedDates' => $bookedDates,
            'coverKind' => $coverKind,
            'contactPhone' => $contactPhone,
            'sleeps' => $listing->property->rooms->sum('max_adults') ?: 4,
            'rate' => (float) $listing->base_price_min ?: ($listing->property->rooms->min('base_price') ?? 0),
            'roomCount' => $listing->property->rooms->count(),
        ]);
    }
}
