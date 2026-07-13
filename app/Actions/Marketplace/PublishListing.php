<?php

namespace App\Actions\Marketplace;

use App\Models\MarketplaceListing;
use App\Models\Property;
use Illuminate\Support\Str;

class PublishListing
{
    public function execute(Property $property): MarketplaceListing
    {
        // Marketplace listing is open to every host — a homestay auto-lists once
        // it goes live (opt-out via the dashboard). Monetization is the 3%
        // commission on marketplace-sourced bookings, not a listing paywall.
        if ($property->status !== Property::STATUS_ACTIVE) {
            throw new \RuntimeException(__('Property must be active before publishing.'));
        }

        $existing = MarketplaceListing::where('property_id', $property->id)->first();

        $listing = MarketplaceListing::updateOrCreate(
            ['property_id' => $property->id],
            array_merge($this->syncData($property), [
                'tenant_id' => $property->tenant_id,
                // Keep the existing slug on re-publish so the public URL is stable.
                'slug' => $existing?->slug ?? $this->uniqueSlug($property->name),
                'status' => MarketplaceListing::STATUS_ACTIVE,
                'published_at' => $existing?->published_at ?? now(),
            ]),
        );

        $property->update([
            'marketplace_enabled' => true,
            'marketplace_published_at' => $property->marketplace_published_at ?? now(),
        ]);

        return $listing;
    }

    /**
     * Refresh an already-published listing's denormalized fields (price, room
     * count, capacity, house type, cover) after a property edit — without
     * touching its slug/published_at or (re)publishing a paused/removed one.
     */
    public function sync(Property $property): void
    {
        $listing = MarketplaceListing::where('property_id', $property->id)
            ->where('status', MarketplaceListing::STATUS_ACTIVE)
            ->first();

        $listing?->update($this->syncData($property));
    }

    public function unpublish(Property $property): void
    {
        $property->update([
            'marketplace_enabled' => false,
            'marketplace_published_at' => null,
        ]);

        MarketplaceListing::where('property_id', $property->id)
            ->update(['status' => MarketplaceListing::STATUS_PAUSED]);
    }

    /**
     * The property-derived fields copied onto the listing — shared by publish
     * (create) and sync (update). Homestay filter facets are denormalized here.
     */
    protected function syncData(Property $property): array
    {
        $property->loadMissing(['rooms', 'tenant.subscription']);

        $capacity = (int) $property->rooms->sum(fn ($r) => (int) $r->max_adults + (int) $r->max_children);

        return [
            // Showcase band: Ultra (featured) 2 > Pro (priority) 1 > Free 0.
            // SubscriptionObserver keeps this current on plan changes.
            'showcase_rank' => \App\Support\Billing\Plans::rank($property->tenant?->planKey() ?? 'free'),
            'title_bm' => $property->name,
            'title_en' => $property->name,
            'hero_photo_path' => $property->hero_photo_path,
            'city' => $property->city,
            'state' => $property->state,
            'country' => $property->country,
            'lat' => $property->lat,
            'lng' => $property->lng,
            'house_type' => $property->pricing_mode,          // whole_house | per_room
            'rooms_count' => $property->rooms->count(),
            'max_guests' => $capacity,
            'base_price_min' => $property->rooms->min('base_price'),
            'base_price_max' => $property->rooms->max('base_price'),
        ];
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'homestay';
        $slug = $base;
        $i = 0;
        while (MarketplaceListing::where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }
        return $slug;
    }
}
