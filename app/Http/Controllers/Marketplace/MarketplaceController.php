<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\MarketplaceListing;
use App\Support\Tenancy\BelongsToTenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function search(Request $request): View
    {
        // The bare homepage (no filters/pagination) shows a few showcase demo
        // homestays alongside the real listings so the grid always looks alive;
        // filtered / paginated requests show only real matches.
        $isHome = ! $request->hasAny(['city', 'state', 'district', 'check_in', 'check_out', 'q', 'house_type', 'min_rooms', 'guests', 'min_price', 'max_price', 'amenities', 'sort', 'page']);

        // Dates are captured (not used to filter availability yet) and carried
        // through to the listing so its booking form opens prefilled.
        $checkIn = $this->cleanDate($request->query('check_in'));
        $checkOut = $this->cleanDate($request->query('check_out'));
        if ($checkOut && $checkIn && $checkOut <= $checkIn) {
            $checkOut = null;
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
            // District (daerah) resolves against the property's city — a listing
            // in "Kluang" matches district=Kluang.
            ->when($request->input('district'), fn ($q, $v) => $q->where('city', 'like', "%$v%"))
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
            })
            // Availability: with both dates set, keep only listings whose property
            // has at least one room free for the range. Raw correlated subqueries
            // (not whereHas) so tenant/soft-delete global scopes never apply — the
            // marketplace is cross-tenant and may be browsed by a logged-in host.
            // Overlap logic mirrors AvailabilityService exactly (half-open range;
            // pending/confirmed/checked_in bookings + room-or-property blocks).
            ->when($checkIn && $checkOut, function ($q) use ($checkIn, $checkOut) {
                $q->whereExists(function ($room) use ($checkIn, $checkOut) {
                    $room->select(DB::raw(1))
                        ->from('rooms')
                        ->whereColumn('rooms.property_id', 'marketplace_listings.property_id')
                        ->whereNull('rooms.deleted_at')
                        ->whereNotExists(function ($b) use ($checkIn, $checkOut) {
                            $b->select(DB::raw(1))
                                ->from('bookings')
                                ->whereColumn('bookings.room_id', 'rooms.id')
                                ->whereIn('bookings.status', [
                                    Booking::STATUS_PENDING,
                                    Booking::STATUS_CONFIRMED,
                                    Booking::STATUS_CHECKED_IN,
                                ])
                                ->where('bookings.check_in', '<', $checkOut)
                                ->where('bookings.check_out', '>', $checkIn);
                        })
                        ->whereNotExists(function ($blk) use ($checkIn, $checkOut) {
                            $blk->select(DB::raw(1))
                                ->from('calendar_blocks')
                                ->where('calendar_blocks.starts_on', '<', $checkOut)
                                ->where('calendar_blocks.ends_on', '>', $checkIn)
                                ->where(function ($w) {
                                    $w->whereColumn('calendar_blocks.room_id', 'rooms.id')
                                        ->orWhere(function ($w2) {
                                            $w2->whereNull('calendar_blocks.room_id')
                                                ->whereColumn('calendar_blocks.property_id', 'rooms.property_id');
                                        });
                                });
                        });
                });
            });

        if ($sort === 'price_low') {
            $query->orderByRaw('base_price_min is null')->orderBy('base_price_min');
        } elseif ($sort === 'rating') {
            $query->orderByDesc('rating_avg')->orderByDesc('published_at');
        } else {
            // Showcase band first (Ultra featured > Pro priority > Free
            // standard), then relevance/recency within each band.
            $query->orderByDesc('showcase_rank')->orderByDesc('is_featured')->orderByDesc('rating_avg')->orderByDesc('published_at');
        }

        $listings = $query->paginate(12)->withQueryString();

        $covers = ['beach', 'highland', 'kampung', 'heritage', 'city'];
        foreach ($listings as $listing) {
            $listing->cover_kind = $covers[crc32((string) $listing->id) % count($covers)];
        }

        return view('marketplace.search', [
            'listings' => $listings,
            'filters' => $request->only(['city', 'state', 'district', 'q', 'house_type', 'min_rooms', 'guests', 'min_price', 'max_price'])
                + ['sort' => $sort, 'amenities' => $amenityKeys, 'check_in' => $checkIn, 'check_out' => $checkOut],
            'total' => MarketplaceListing::query()->published()->count(),
            'amenityList' => Amenity::orderBy('sort_order')->get(['key', 'label_bm', 'label_en', 'icon', 'category']),
            'demos' => $isHome ? $this->demoListings() : [],
            // State → districts map drives the cascading daerah dropdown.
            'districtsByState' => config('districts'),
        ]);
    }

    /** Strict Y-m-d only (rejects "2026-13-45"); returns the string or null. */
    protected function cleanDate(?string $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $d = \Carbon\Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $d->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * Showcase demo homestays shown on the bare homepage grid (after the real
     * listings) so a freshly-launched marketplace never looks empty. Display
     * only — not bookable, not counted in totals, never on filtered searches.
     */
    protected function demoListings(): array
    {
        $p = 'https://images.unsplash.com/';

        return [
            ['t' => 'Villa Damai Langkawi',   'city' => 'Langkawi',      'state' => 'Kedah',          'rate' => 280, 'rating' => '4.9', 'rev' => 64, 'feat' => true,  'tag' => 'Tepi pantai',  'img' => $p.'photo-1571003123894-1f0594d2b5d9?w=640&q=80&auto=format&fit=crop'],
            ['t' => 'Rumah Kayu Cameron',     'city' => 'Tanah Rata',    'state' => 'Pahang',         'rate' => 190, 'rating' => '4.7', 'rev' => 38, 'feat' => false, 'tag' => 'Tanah tinggi', 'img' => $p.'photo-1449158743715-0a90ebb6d2d8?w=640&q=80&auto=format&fit=crop'],
            ['t' => 'Kampung Stay Sekinchan', 'city' => 'Sekinchan',     'state' => 'Selangor',       'rate' => 120, 'rating' => '4.8', 'rev' => 52, 'feat' => false, 'tag' => 'Kampung',      'img' => $p.'photo-1568605114967-8130f3a36994?w=640&q=80&auto=format&fit=crop'],
            ['t' => 'Heritage House Melaka',  'city' => 'Bandar Melaka', 'state' => 'Melaka',         'rate' => 230, 'rating' => '4.9', 'rev' => 81, 'feat' => false, 'tag' => 'Warisan',      'img' => $p.'photo-1600585154340-be6161a56a0c?w=640&q=80&auto=format&fit=crop'],
            ['t' => 'Teluk Kemang Retreat',   'city' => 'Port Dickson',  'state' => 'Negeri Sembilan','rate' => 210, 'rating' => '4.6', 'rev' => 29, 'feat' => false, 'tag' => 'Tepi pantai',  'img' => $p.'photo-1520250497591-112f2f40a3f4?w=640&q=80&auto=format&fit=crop'],
            ['t' => 'Chalet Janda Baik',      'city' => 'Janda Baik',    'state' => 'Pahang',         'rate' => 340, 'rating' => '5.0', 'rev' => 47, 'feat' => true,  'tag' => 'Tanah tinggi', 'img' => $p.'photo-1518780664697-55e3ad937233?w=640&q=80&auto=format&fit=crop'],
            ['t' => 'Kampung Air Tawar',      'city' => 'Kuala Selangor','state' => 'Selangor',       'rate' => 95,  'rating' => '4.5', 'rev' => 19, 'feat' => false, 'tag' => 'Kampung',      'img' => $p.'photo-1564013799919-ab600027ffc6?w=640&q=80&auto=format&fit=crop'],
            ['t' => 'Georgetown Loft',        'city' => 'George Town',   'state' => 'Pulau Pinang',   'rate' => 250, 'rating' => '4.8', 'rev' => 73, 'feat' => false, 'tag' => 'Warisan',      'img' => $p.'photo-1505691938895-1758d7feb511?w=640&q=80&auto=format&fit=crop'],
        ];
    }

    /**
     * Listing detail. Device-aware:
     *   • Phones  → the SAME public booking page the host's own subdomain shows
     *     ({host}.tempahlah.com), scoped to this one homestay (mobile-first,
     *     calendar-led). No redirect — the marketplace URL is preserved.
     *   • Desktop/laptop/tablet → the marketplace's own rich detail layout
     *     (gallery + sticky booking widget).
     * Either way the marketplace attribution is armed so a resulting booking is
     * flagged channel=marketplace (3% commission).
     */
    public function show(MarketplaceListing $listing, \App\Services\Public\PublicHomeBuilder $builder, Request $request): View
    {
        abort_unless($listing->status === MarketplaceListing::STATUS_ACTIVE, 404);

        $listing->load([
            'tenant',
            'property.rooms:id,property_id,base_price,max_adults,max_children,beds',
            'property.rooms.pricingRules',
            'property.photos:id,property_id,path,disk,is_hero,sort_order',
            'property.amenities:id,key,label_bm,label_en,icon,category,sort_order',
        ]);

        abort_unless(
            $listing->property && $listing->property->status === \App\Models\Property::STATUS_ACTIVE,
            404,
        );

        // Arm attribution so a booking made from here is marketplace-sourced.
        \App\Support\Marketplace\Attribution::remember($listing->tenant, $listing->id);

        // Real phones (UA-detected) get the host's subdomain-style booking page
        // scoped to this homestay. Larger screens get the marketplace's own rich
        // detail layout, which is itself responsive: squeezing a desktop browser
        // below 820px morphs it into a public-link-style mobile view via CSS —
        // no reload, so the transition is smooth.
        // Dates chosen in marketplace search flow into the booking form.
        [$checkIn, $checkOut] = $this->searchDates($request);

        if ($this->isMobile($request)) {
            $data = $builder->build($listing->tenant, collect([$listing->property]), $request);
            $data['marketplaceContext'] = true;
            $data['backUrl'] = route('marketplace.search');

            if ($checkIn) {
                $data['prefill'] = array_filter([
                    'property_id' => $listing->property->id,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                ], fn ($v) => $v !== null);
            }

            return view('public-tenant.home', $data);
        }

        return $this->showDetailDesktop($listing, $checkIn, $checkOut);
    }

    /**
     * Cleaned, ordered [check_in, check_out] from the request — check_in must be
     * today or later, check_out strictly after it. Either may be null.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function searchDates(Request $request): array
    {
        $checkIn = $this->cleanDate($request->query('check_in'));
        $checkOut = $this->cleanDate($request->query('check_out'));

        // MYT calendar date — the app runs in UTC, so now()->toDateString() is
        // yesterday between 00:00–08:00 MYT and would reject a valid today date.
        if ($checkIn && $checkIn < now(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->toDateString()) {
            $checkIn = null;
        }
        if (! $checkIn || ($checkOut && $checkOut <= $checkIn)) {
            $checkOut = null;
        }

        return [$checkIn, $checkOut];
    }

    /**
     * Desktop/laptop/tablet marketplace detail — gallery + sticky booking
     * widget. Booking still hands off to the host's own subdomain page,
     * carrying ?src=marketplace so a resulting booking is marketplace-sourced.
     */
    protected function showDetailDesktop(MarketplaceListing $listing, ?string $checkIn = null, ?string $checkOut = null): View
    {
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

        // Click-through to the host's own booking page, carrying marketplace
        // attribution so a resulting booking is flagged as marketplace-sourced.
        $bookUrl = $listing->tenant->publicUrl().'?'.http_build_query(array_filter([
            'src' => 'marketplace',
            'ref' => 'tempahlah_mp',
            'listing_id' => $listing->id,
            // Carry the search dates so the host's booking page opens prefilled.
            'property_id' => $checkIn ? $listing->property_id : null,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ], fn ($v) => $v !== null));

        return view('marketplace.show', [
            'listing' => $listing,
            'property' => $listing->property,
            'rooms' => $listing->property->rooms,
            'bookedDates' => $bookedDates,
            'coverKind' => $coverKind,
            'contactPhone' => $contactPhone,
            'bookUrl' => $bookUrl,
            'sleeps' => $listing->property->rooms->sum('max_adults') ?: 4,
            'defaultGuests' => $listing->property->effectiveDefaultGuests(),
            'rate' => (float) $listing->base_price_min ?: ($listing->property->rooms->min('base_price') ?? 0),
            'roomCount' => $listing->property->rooms->count(),
        ]);
    }

    /**
     * Phone detection from the User-Agent. "Mobi" is the token recommended for
     * mobile detection; tablets (iPad / Android tablets, which omit "Mobi")
     * fall through to the desktop layout, which the user wants.
     */
    protected function isMobile(Request $request): bool
    {
        $ua = (string) $request->header('User-Agent');

        return preg_match('/Mobi/i', $ua) === 1 && preg_match('/iPad/i', $ua) !== 1;
    }
}
