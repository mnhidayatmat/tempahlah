<?php

namespace App\Http\Controllers\Public;

use App\Actions\Reviews\SubmitGuestReview;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Tenant;
use App\Support\Tenancy\BelongsToTenantScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Guest-facing "leave a testimonial" page. Reached via a signed magic-link the
 * guest gets by email + WhatsApp after checkout (see SendReviewRequest). The
 * `signed` route middleware verifies the HMAC — no password.
 *
 * Hosts never touch testimonials: this is the only write path, and it's the
 * guest's. The submission auto-publishes; a super admin can hide it later.
 */
class ReviewController extends Controller
{
    public function show(Request $request, string $tenant_slug, string $booking): View
    {
        unset($tenant_slug); // resolved by the subdomain middleware

        [$tenant, $bookingModel] = $this->resolve($request, $booking);

        return view('public-tenant.review', [
            'tenant' => $tenant,
            'booking' => $bookingModel,
            'existing' => $bookingModel->review,
        ]);
    }

    public function store(Request $request, string $tenant_slug, string $booking): RedirectResponse
    {
        unset($tenant_slug);

        [$tenant, $bookingModel] = $this->resolve($request, $booking);

        // One testimonial per booking. If it already exists, just show the
        // thank-you state — never overwrite (a host could otherwise coach a
        // guest to resubmit a better score; keep the first, honest one).
        if ($bookingModel->review) {
            return redirect()->to($bookingModel->reviewUrl())
                ->with('status', __('Thank you — your testimonial was already received.'));
        }

        $validated = $request->validate([
            'rating_overall' => 'required|integer|min:1|max:5',
            'comment'        => 'required|string|min:4|max:1500',
            'guest_name'     => 'nullable|string|max:120',
        ], [
            'rating_overall.required' => __('Please pick a star rating.'),
            'comment.required'        => __('Please write a short review.'),
        ]);

        app(SubmitGuestReview::class)->execute($bookingModel, [
            'rating_overall' => (int) $validated['rating_overall'],
            'comment'        => trim($validated['comment']),
            'guest_name'     => trim($validated['guest_name'] ?? '') ?: $bookingModel->guestName(),
        ]);

        return redirect()->to($bookingModel->reviewUrl())
            ->with('status', __('Thank you! Your testimonial is now live on the homestay page.'));
    }

    /**
     * Resolve the tenant (from the subdomain middleware) + the booking, scoped
     * to that tenant. 404 on any mismatch so a signed link for one tenant can't
     * reach another's booking. Only a checked-out booking can be reviewed.
     *
     * @return array{0: Tenant, 1: Booking}
     */
    private function resolve(Request $request, string $bookingPublicId): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('subdomain_tenant');
        abort_unless($tenant, 404);

        $booking = Booking::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('public_id', $bookingPublicId)
            ->with(['property:id,name', 'guest:id,name', 'leadGuest', 'review'])
            ->firstOrFail();

        // A testimonial is about a completed stay. Guard so an unfinished
        // booking's link (should never be minted) can't leave a review.
        abort_unless($booking->status === Booking::STATUS_CHECKED_OUT, 404);

        return [$tenant, $booking];
    }
}
