<?php

namespace App\Actions\Reviews;

use App\Models\Booking;
use App\Models\MarketplaceListing;
use App\Models\Property;
use App\Models\Review;

class SubmitGuestReview
{
    public function execute(Booking $booking, array $data): Review
    {
        $review = Review::firstOrCreate(
            [
                'booking_id' => $booking->id,
                'reviewer_type' => Review::REVIEWER_GUEST,
                'subject_type' => Property::class,
                'subject_id' => $booking->property_id,
            ],
            [
                'tenant_id' => $booking->tenant_id,
                'rating_overall' => $data['rating_overall'],
                'rating_breakdown' => $data['rating_breakdown'] ?? null,
                'comment' => $data['comment'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                // Auto-publish on submit (host can never touch it; a super admin
                // can hide it later from the Platform Admin moderation screen).
                'is_published' => true,
            ],
        );

        $this->refreshMarketplaceRating($booking->property_id);

        return $review;
    }

    protected function refreshMarketplaceRating(int $propertyId): void
    {
        $listing = MarketplaceListing::where('property_id', $propertyId)->first();
        if (! $listing) {
            return;
        }

        $stats = Review::where('subject_type', Property::class)
            ->where('subject_id', $propertyId)
            ->where('reviewer_type', Review::REVIEWER_GUEST)
            ->where('is_published', true)
            ->selectRaw('AVG(rating_overall) as avg_rating, COUNT(*) as review_count')
            ->first();

        $listing->update([
            'rating_avg' => $stats?->avg_rating ? round((float) $stats->avg_rating, 2) : null,
            'review_count' => (int) ($stats?->review_count ?? 0),
        ]);
    }
}
