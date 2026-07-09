<?php

namespace App\Http\Controllers\Public;

use App\Actions\Booking\CreateBooking;
use App\Actions\Invoicing\GenerateInvoice;
use App\Actions\Payments\CreateGatewayBill;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Jobs\SendBookingInstructions;
use App\Jobs\SendBookingInvoice;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Payments\PaymentGatewayException;
use App\Support\Tenancy\BelongsToTenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Pennant\Feature;

/**
 * Public direct-booking flow on the tenant subdomain
 * ({slug}.tempahlah.com).
 *
 *   POST /book           → create booking + Toyyibpay deposit bill + invoice,
 *                          dispatch SendBookingInvoice (email + WA),
 *                          redirect to GET /book/sent/{reference}.
 *   GET  /book/sent/{ref} → "we sent the pay link" landing page that also
 *                           embeds the link as a fallback button.
 *
 * Tenant context comes from the ResolveTenantFromSubdomain middleware via
 * the `subdomain_tenant` request attribute — never trust form input for
 * tenant resolution.
 */
class PublicBookingController extends Controller
{
    public function __construct(
        protected CreateBooking $createBooking,
        protected CreateGatewayBill $createBill,
        protected GenerateInvoice $generateInvoice,
    ) {}

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('subdomain_tenant');
        $data = $request->validated();

        // Resolve the effective payment method. The guest picks gateway or
        // manual on the form, but we validate against what's actually
        // available: fall back gateway→manual (or vice-versa) if the chosen
        // one isn't set up, and only hand off to WhatsApp if NEITHER works.
        $method        = ($data['payment_method'] ?? 'gateway') === 'manual' ? 'manual' : 'gateway';
        $manualEnabled = $tenant->manualPaymentEnabled();
        $gatewayReady  = $this->createBill->gatewayConfigured($tenant->id);

        if ($method === 'gateway' && ! $gatewayReady) {
            $method = $manualEnabled ? 'manual' : 'none';
        } elseif ($method === 'manual' && ! $manualEnabled) {
            $method = $gatewayReady ? 'gateway' : 'none';
        }

        // Neither an online gateway nor manual pay is available → hand the
        // customer off to a WhatsApp deeplink with the enquiry prefilled, so
        // the page still works out-of-the-box for non-paid tenants.
        if ($method === 'none') {
            return redirect()->away($this->whatsappFallbackUrl($tenant, $data));
        }

        // Marketplace attribution → a booking sourced from tempahlah.com is
        // channel=marketplace (3% commission); a plain direct booking is 0%.
        $attribution = \App\Support\Marketplace\Attribution::for($tenant);
        $channel = $attribution ? Booking::CHANNEL_MARKETPLACE : Booking::CHANNEL_DIRECT;

        // 1. Create booking (transactional inside the action).
        try {
            $booking = $this->createBooking->execute([
                'property_id'      => $data['property_id'],
                'room_id'          => $data['room_id'],
                'check_in'         => $data['check_in'],
                'check_out'        => $data['check_out'],
                'adults'           => $data['adults'],
                'children'         => $data['children'] ?? 0,
                'guest_name'       => $data['guest_name'],
                'guest_email'      => $data['guest_email'],
                'guest_phone'      => $request->normalizedPhone(),
                'guest_country'    => 'MY',
                'is_foreigner'     => false,
                'channel'          => $channel,
                // Omit deposit_pct — CreateBooking will use the
                // property's flat booking_fee_amount as the pay-now
                // amount (default RM 100). Falls back to 20% only if
                // the property has no fee configured.
                'special_requests' => $data['special_requests'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            // CreateBooking throws on availability conflicts.
            return redirect()
                ->route('tenant-public.home', ['tenant_slug' => $tenant->slug])
                ->withInput()
                ->with('booking_error', __('Sorry, these dates were just taken — please pick different dates.'));
        }

        // Record the marketplace referral on the booking + clear it so a later
        // direct booking in the same session isn't mis-attributed.
        if ($attribution) {
            $booking->update(['meta' => array_merge($booking->meta ?? [], [
                'marketplace_ref' => $attribution['ref'] ?? 'tempahlah_mp',
                'marketplace_listing_id' => $attribution['listing_id'] ?? null,
            ])]);
            \App\Support\Marketplace\Attribution::clear();
        }

        $requiresFullPayment = (bool) ($booking->meta['requires_full_payment'] ?? false);

        // 2a. MANUAL pay path — the guest pays the tenant directly (bank
        //     transfer / cash). No gateway bill and no Payment row is created
        //     (the tenant records the money later via "Mark paid", which mints
        //     the succeeded Payment + receipt). The booking stays PENDING and
        //     is NOT auto-cancelled by the lifecycle command — that only
        //     targets bookings carrying an unpaid gateway deposit bill. The
        //     guest still gets an invoice with the host's payment instructions.
        if ($method === 'manual') {
            if (Feature::for($tenant)->active('invoice_documents')) {
                $invoice = $this->generateInvoice->execute(
                    $booking->fresh(['property', 'tenant', 'bookingGuests']),
                    null,
                    Invoice::TYPE_INVOICE,
                );

                // Empty payUrl + manual flag → the invoice email/WA render the
                // host's bank-transfer instructions instead of a pay button.
                SendBookingInvoice::dispatch($booking->id, $invoice->id, '', true);
            } else {
                // Free tier issues no invoice document. The guest still needs the
                // booking summary and the host's payment instructions, or they'd
                // be left with no way to pay.
                SendBookingInstructions::dispatch($booking->id);
            }

            return redirect()->route('tenant-public.booking.sent', [
                'tenant_slug' => $tenant->slug,
                'reference'   => $booking->reference,
            ]);
        }

        // 2b. Payment gateway bill (Toyyibpay or Billplz — whichever the tenant
        //    has active). Last-minute bookings (made inside the tenant's
        //    full-payment lead time) are billed for the FULL total —
        //    CreateBooking has already set deposit_amount = total in that case —
        //    so we mark the payment TYPE_FULL for accurate records + receipt
        //    wording. Otherwise it's a TYPE_DEPOSIT (booking fee) with the
        //    balance due before check-in.
        try {
            $bill = $this->createBill->execute(
                $booking,
                $requiresFullPayment ? Payment::TYPE_FULL : Payment::TYPE_DEPOSIT,
                (float) $booking->deposit_amount,
            );
        } catch (PaymentGatewayException $e) {
            // Should be rare since we pre-checked gatewayConfigured(), but
            // creds could be rotated / wrong / disabled between the check and
            // the call.
            Log::warning('Public booking: payment gateway bill creation failed', [
                'tenant_id'  => $tenant->id,
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->away($this->whatsappFallbackUrl($tenant, $data, $booking));
        }

        // Mark this booking as eligible for fee auto-cancel: it's a
        // guest-initiated online-gateway booking with a live pay link, so if
        // the fee goes unpaid past the window the lifecycle command should free
        // the dates. This flag is the ONLY thing that arms auto-cancel — a
        // manual booking, or a host who later attaches a pay link via "send
        // invoice", never gets it, so those are never auto-cancelled.
        $booking->update(['meta' => array_merge($booking->meta ?? [], ['fee_autocancel' => true])]);

        // 3. Invoice PDF.
        $invoice = $this->generateInvoice->execute(
            $booking->fresh(['property', 'tenant', 'bookingGuests']),
            $bill['payment'],
            Invoice::TYPE_INVOICE,
        );

        // 4. Fan-out email + WhatsApp invoice (async).
        SendBookingInvoice::dispatch($booking->id, $invoice->id, $bill['payment_url']);

        return redirect()->route('tenant-public.booking.sent', [
            'tenant_slug' => $tenant->slug,
            'reference'   => $booking->reference,
        ]);
    }

    public function sent(Request $request, string $tenant_slug, string $reference): View
    {
        // NOTE: the first string positional argument is the `{tenant_slug}`
        // placeholder from the subdomain (Route::domain('{tenant_slug}.…')),
        // which Laravel injects BEFORE the URI's `{reference}`. We don't
        // need the slug here (the middleware already resolved the tenant
        // and stashed it on $request->attributes['subdomain_tenant']) —
        // but we MUST accept it in the signature so Laravel binds the
        // URI `{reference}` to the right argument. Without this fix,
        // `$reference` was receiving the tenant slug and `firstOrFail()`
        // would 404 every booking-sent page.
        unset($tenant_slug);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('subdomain_tenant');

        $booking = Booking::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('reference', $reference)
            ->with(['property', 'bookingGuests', 'tenant'])
            ->firstOrFail();

        // Most recent processing/succeeded deposit Payment carries the URL.
        $payment = Payment::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('booking_id', $booking->id)
            ->where('type', Payment::TYPE_DEPOSIT)
            ->orderByDesc('id')
            ->first();

        $payUrl = $payment?->meta['payment_url'] ?? null;

        return view('public-tenant.book-sent', [
            'tenant'             => $tenant,
            'booking'            => $booking,
            'payment'            => $payment,
            'payUrl'             => $payUrl,
            // No open gateway pay link → manual booking. Surface the host's
            // bank-transfer instructions (may be null → generic copy).
            'manualInstructions' => $payUrl ? null : $tenant->manualPaymentInstructions(),
        ]);
    }

    /**
     * Guest-facing booking detail page reached via the signed magic-link in
     * the confirmation email + WhatsApp. The `signed` route middleware has
     * already verified the HMAC + expiry — at this point we only need to
     * resolve the booking and confirm it belongs to THIS subdomain's tenant
     * (defence-in-depth: a leaked link still can't be opened on a different
     * tenant's subdomain even though the signature would mathematically check
     * out, because the URL is host-bound).
     */
    public function show(Request $request, string $tenant_slug, string $booking): View
    {
        unset($tenant_slug); // resolved by middleware → $request->attributes

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('subdomain_tenant');

        $bookingModel = Booking::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('public_id', $booking)
            ->with([
                'property:id,name,city,state,address_line1,address_line2,postcode,check_in_time,check_out_time',
                'room:id,name',
                'bookingGuests',
                'payments' => fn ($q) => $q->orderByDesc('id'),
                'tenant',
            ])
            ->firstOrFail();

        // Outstanding balance + most-recent open pay link (if any) — re-uses
        // the same Payment.meta.payment_url that book-sent.blade.php reads.
        // `meta` is array-cast but may be NULL when the row was created
        // without metadata, so guard with is_array() before key access.
        $openPayment = $bookingModel->payments
            ->first(fn ($p) => in_array($p->status, [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING], true)
                && is_array($p->meta) && ! empty($p->meta['payment_url']));

        return view('public-tenant.booking-detail', [
            'tenant'      => $tenant,
            'booking'     => $bookingModel,
            'leadGuest'   => $bookingModel->bookingGuests->firstWhere('is_lead', true)
                              ?? $bookingModel->bookingGuests->first(),
            'balanceDue'  => $bookingModel->balanceDue(),
            'openPayUrl'  => is_array($openPayment?->meta) ? ($openPayment->meta['payment_url'] ?? null) : null,
            'contactPhone'=> preg_replace('/\D/', '', $tenant->business_phone ?? ''),
        ]);
    }

    /**
     * Build a WhatsApp deeplink with the booking enquiry prefilled. Used
     * as the graceful fallback when Toyyibpay isn't set up yet.
     *
     * If a $booking row has already been created (Toyyibpay error after
     * createBooking), we include the reference; otherwise we just use
     * the form values.
     */
    protected function whatsappFallbackUrl(Tenant $tenant, array $data, ?Booking $booking = null): string
    {
        $phone = preg_replace('/\D/', '', $tenant->business_phone ?? '');
        if ($phone === '' || $phone === null) {
            // No business phone configured either → bounce back to home
            // with a soft error. This is rare.
            return route('tenant-public.home', ['tenant_slug' => $tenant->slug]);
        }

        $ci = $data['check_in'];
        $co = $data['check_out'];
        $name = $data['guest_name'] ?? '';
        $adults = (int) ($data['adults'] ?? 1);

        $msg = "Hi {$tenant->business_name}! I'd like to book — "
             . "Name: {$name}, "
             . "Dates: {$ci} → {$co}, "
             . "Guests: {$adults}"
             . ($booking ? " (Ref: {$booking->reference})" : '')
             . ". Could you confirm availability and payment details?";

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($msg);
    }
}
