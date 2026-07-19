<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Review;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Read-only testimonials list for the host. Guest testimonials are tamper-proof:
 * the host can see them (and their published/hidden state) but cannot create,
 * edit, delete, or reply. The only writer is the guest (public signed form);
 * the only moderator is a super admin (Platform Admin). This keeps the social
 * proof credible — a host can't fake a glowing review or bury a bad one.
 */
class TestimonialController extends Controller
{
    public function index(Request $request): View
    {
        // Tenant-scoped by the Review model's global scope.
        $base = Review::query()->guestTestimonials();

        // Homestays the host can filter by (tenant-scoped by Property's global
        // scope), each with its testimonial count so the host sees where the
        // reviews are landing at a glance.
        $properties = Property::query()->orderBy('name')->get(['id', 'name']);
        $countsByProperty = (clone $base)
            ->selectRaw('subject_id, COUNT(*) as c')
            ->groupBy('subject_id')
            ->pluck('c', 'subject_id');

        // Active homestay filter — only applied when it's actually one of this
        // tenant's properties, so a crafted ?property_id can't leak another
        // tenant's reviews (subject_id is not tenant-scoped on its own).
        $propertyId = (int) $request->query('property_id');
        $propertyId = $properties->contains('id', $propertyId) ? $propertyId : null;
        $filtered = (clone $base)->when($propertyId, fn ($q) => $q->where('subject_id', $propertyId));

        $reviews = (clone $filtered)
            ->with(['booking:id,check_in,check_out,guest_id', 'booking.guest:id,name', 'subject:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $publishedCount = (clone $filtered)->where('is_published', true)->count();
        $hiddenCount = (clone $filtered)->where('is_published', false)->count();
        $avg = (clone $filtered)->where('is_published', true)->avg('rating_overall');

        return view('tenant.testimonials.index', [
            'reviews'          => $reviews,
            'publishedCount'   => $publishedCount,
            'hiddenCount'      => $hiddenCount,
            'avgRating'        => $avg ? round((float) $avg, 1) : null,
            'properties'       => $properties,
            'countsByProperty' => $countsByProperty,
            'activePropertyId' => $propertyId,
            'totalCount'       => (clone $base)->count(),
        ]);
    }

    /**
     * Appeal to the super admin to hide a testimonial (e.g. it's unfair,
     * abusive, or off-topic). This does NOT hide it — only a super admin can,
     * after reviewing the reason — so the host still can't unilaterally bury a
     * bad review. The review row is tenant-scoped by the global scope, so a
     * host can only appeal their own testimonials.
     */
    public function appeal(Request $request, int $id): RedirectResponse
    {
        $review = Review::query()->guestTestimonials()->findOrFail($id);

        $data = $request->validate([
            'appeal_reason' => 'required|string|min:10|max:1000',
        ], [
            'appeal_reason.required' => __('Please explain why this testimonial should be hidden.'),
            'appeal_reason.min'      => __('Please give a bit more detail (at least 10 characters).'),
        ]);

        if (! $review->is_published) {
            return back()->with('error', __('This testimonial is already hidden.'));
        }
        if ($review->isAppealPending()) {
            return back()->with('error', __('You already have an appeal pending for this testimonial.'));
        }

        $review->update([
            'appeal_status'      => Review::APPEAL_PENDING,
            'appeal_reason'      => trim($data['appeal_reason']),
            'appealed_at'        => now(),
            'appeal_reviewed_at' => null,
            'appeal_admin_note'  => null,
        ]);

        return back()->with('status', __('Appeal submitted. An admin will review your reason and decide whether to hide this testimonial.'));
    }
}
