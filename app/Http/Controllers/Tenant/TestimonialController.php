<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
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
    public function index(): View
    {
        // Tenant-scoped by the Review model's global scope.
        $base = Review::query()->guestTestimonials();

        $reviews = (clone $base)
            ->with(['booking:id,check_in,check_out,guest_id', 'booking.guest:id,name', 'subject:id,name'])
            ->latest()
            ->paginate(20);

        $publishedCount = (clone $base)->where('is_published', true)->count();
        $hiddenCount = (clone $base)->where('is_published', false)->count();
        $avg = (clone $base)->where('is_published', true)->avg('rating_overall');

        return view('tenant.testimonials.index', [
            'reviews'        => $reviews,
            'publishedCount' => $publishedCount,
            'hiddenCount'    => $hiddenCount,
            'avgRating'      => $avg ? round((float) $avg, 1) : null,
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
