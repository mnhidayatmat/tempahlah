<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Contracts\View\View;

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
}
