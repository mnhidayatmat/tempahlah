<?php

namespace App\Actions\Marketplace;

use App\Models\MarketplaceListing;
use App\Models\Property;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class PublishListing
{
    public function execute(Property $property): MarketplaceListing
    {
        if (! Feature::value('marketplace_listing')) {
            throw new \RuntimeException(__('Marketplace listings require the Pro plan.'));
        }

        if ($property->status !== Property::STATUS_ACTIVE) {
            throw new \RuntimeException(__('Property must be active before publishing.'));
        }

        $minPrice = $property->rooms()->min('base_price');
        $maxPrice = $property->rooms()->max('base_price');

        $listing = MarketplaceListing::updateOrCreate(
            ['property_id' => $property->id],
            [
                'tenant_id' => $property->tenant_id,
                'slug' => $this->uniqueSlug($property->name),
                'title_bm' => $property->name,
                'title_en' => $property->name,
                'hero_photo_path' => $property->hero_photo_path,
                'city' => $property->city,
                'state' => $property->state,
                'country' => $property->country,
                'lat' => $property->lat,
                'lng' => $property->lng,
                'base_price_min' => $minPrice,
                'base_price_max' => $maxPrice,
                'status' => MarketplaceListing::STATUS_ACTIVE,
                'published_at' => now(),
            ],
        );

        $property->update([
            'marketplace_enabled' => true,
            'marketplace_published_at' => now(),
        ]);

        return $listing;
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

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 0;
        while (MarketplaceListing::where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }
        return $slug;
    }
}
