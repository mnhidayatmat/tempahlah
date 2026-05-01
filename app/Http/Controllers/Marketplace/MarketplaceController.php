<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceListing;
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

        return view('marketplace.search', [
            'listings' => $listings,
            'filters' => $request->only(['city', 'state', 'q', 'min_price', 'max_price']),
        ]);
    }

    public function show(MarketplaceListing $listing): View
    {
        abort_unless($listing->status === MarketplaceListing::STATUS_ACTIVE, 404);
        $listing->load('property.rooms', 'property.photos');

        return view('marketplace.show', compact('listing'));
    }
}
