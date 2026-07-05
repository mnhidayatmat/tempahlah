<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\MarketplaceListing;
use App\Support\Tenancy\BelongsToTenantScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function search(Request $request): View
    {
        // Soft launch: while no homestay is published yet, the bare root page
        // shows the host-acquisition landing instead of an empty grid. The
        // moment any host lists a homestay, / becomes the search page. Filtered
        // / paginated requests always render the marketplace (with its empty
        // state) so search behaviour is predictable.
        if (! $request->hasAny(['city', 'state', 'q', 'house_type', 'min_rooms', 'guests', 'min_price', 'max_price', 'amenities', 'sort', 'page'])
            && MarketplaceListing::query()->published()->doesntExist()) {
            return view('welcome');
        }

        $sort = in_array($request->input('sort'), ['price_low', 'rating'], true)
            ? $request->input('sort')
            : 'relevance';

        // Amenity filter: only real keys, treated as "must have ALL selected".
        $amenityKeys = array_values(array_filter((array) $request->input('amenities', [])));

        $query = MarketplaceListing::query()
            ->published()
            ->when($request->input('city'), fn ($q, $v) => $q->where('city', 'like', "%$v%"))
            ->when($request->input('state'), fn ($q, $v) => $q->where('state', $v))
            ->when($request->input('q'), fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('title_bm', 'like', "%$v%")
                    ->orWhere('title_en', 'like', "%$v%")
                    ->orWhere('city', 'like', "%$v%");
            }))
            ->when($request->input('house_type'), fn ($q, $v) => $q->where('house_type', $v))
            ->when($request->input('min_rooms'), fn ($q, $v) => $q->where('rooms_count', '>=', (int) $v))
            ->when($request->input('guests'), fn ($q, $v) => $q->where('max_guests', '>=', (int) $v))
            // Price filters run on the starting rate (what the card shows as
            // "From RM X") so results match the displayed price.
            ->when($request->input('min_price'), fn ($q, $v) => $q->where('base_price_min', '>=', (float) $v))
            ->when($request->input('max_price'), fn ($q, $v) => $q->where('base_price_min', '<=', (float) $v))
            ->when($amenityKeys, function ($q) use ($amenityKeys) {
                // whereHas per key → property must have ALL selected amenities.
                foreach ($amenityKeys as $key) {
                    $q->whereHas('property.amenities', fn ($q2) => $q2->where('amenities.key', $key));
                }
            });

        if ($sort === 'price_low') {
            $query->orderByRaw('base_price_min is null')->orderBy('base_price_min');
        } elseif ($sort === 'rating') {
            $query->orderByDesc('rating_avg')->orderByDesc('published_at');
        } else {
            $query->orderByDesc('is_featured')->orderByDesc('rating_avg')->orderByDesc('published_at');
        }

        $listings = $query->paginate(12)->withQueryString();

        $covers = ['beach', 'highland', 'kampung', 'heritage', 'city'];
        foreach ($listings as $listing) {
            $listing->cover_kind = $covers[crc32((string) $listing->id) % count($covers)];
        }

        return view('marketplace.search', [
            'listings' => $listings,
            'filters' => $request->only(['city', 'state', 'q', 'house_type', 'min_rooms', 'guests', 'min_price', 'max_price'])
                + ['sort' => $sort, 'amenities' => $amenityKeys],
            'total' => MarketplaceListing::query()->published()->count(),
            'amenityList' => Amenity::orderBy('sort_order')->get(['key', 'label_bm', 'label_en', 'icon', 'category']),
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
