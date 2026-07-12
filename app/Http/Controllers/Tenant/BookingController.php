<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Booking\CreateBooking;
use App\Actions\Invoicing\GenerateInvoice;
use App\Actions\Payments\CreateGatewayBill;
use App\Http\Controllers\Controller;
use App\Jobs\SendBookingConfirmation;
use App\Jobs\SendReviewRequest;
use App\Jobs\SendBookingReceipt;
use App\Jobs\SendPaymentReminder;
use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\CleaningTask;
use App\Models\Commission;
use App\Models\Dispute;
use App\Models\GuestBlacklistEntry;
use App\Models\IncidentReport;
use App\Models\Invoice;
use App\Models\LaundryTask;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\DB;
use App\Services\Payments\PaymentGatewayException;
use App\Services\WhatsApp\WhatsappMessenger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Pennant\Feature;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('status', 'all');
        $valid = ['all', 'upcoming', 'checked-in', 'past', 'deposit-due'];
        if (! in_array($filter, $valid, true)) {
            $filter = 'all';
        }

        $today = Carbon::today();

        $bookings = Booking::query()
            ->with(['guest:id,name,email,phone', 'leadGuest', 'property:id,name,city'])
            ->when($filter === 'upcoming', fn ($q) => $q
                ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
                ->where('check_in', '>=', $today))
            ->when($filter === 'checked-in', fn ($q) => $q
                ->where('status', Booking::STATUS_CHECKED_IN))
            ->when($filter === 'past', fn ($q) => $q
                ->where('check_out', '<', $today))
            // Mirrors the dashboard "Action queue" deposit-due item exactly:
            // confirmed/pending bookings whose deposit is unpaid and whose
            // check-in falls within the next 7 days. Clicking that item lands here.
            ->when($filter === 'deposit-due', fn ($q) => $q
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING])
                ->whereNull('deposit_paid_at')
                ->whereBetween('check_in', [now(), now()->addDays(7)]))
            // Deposit-due: soonest check-in first (nearest deadline to chase).
            // Everything else: most recent check-in first.
            ->when(
                $filter === 'deposit-due',
                fn ($q) => $q->orderBy('check_in'),
                fn ($q) => $q->orderByDesc('check_in'),
            )
            ->paginate(20)
            ->withQueryString();

        return view('tenant.bookings.index', [
            'bookings' => $bookings,
            'filter' => $filter,
        ]);
    }

    /**
     * "Send booking form" — build a link to this tenant's own public booking
     * page, prefilled with the homestay, dates, guest count and payment method
     * the host agreed with a guest over WhatsApp or the phone.
     *
     * There is no token and no invite record on purpose: the booking page is
     * already public, so a signed link would add ceremony without protecting
     * anything. The guest may edit the dates; the price is recomputed and
     * availability re-checked server-side when they submit.
     */
    public function sendForm(Request $request)
    {
        $tenant = app(\App\Support\Tenancy\TenantContext::class)->current();

        $properties = Property::query()
            ->where('status', Property::STATUS_ACTIVE)
            ->with('rooms:id,property_id,base_price,max_adults,max_children')
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'default_guests']);

        $today = Carbon::today();

        // Nights already occupied, so the host is warned before quoting a date
        // that is gone. Half-open [check_in, check_out) — the checkout morning
        // is still sellable, matching AvailabilityService.
        $booked = Booking::query()
            ->whereIn('status', [
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CHECKED_IN,
            ])
            ->whereDate('check_out', '>=', $today)
            ->get(['property_id', 'check_in', 'check_out'])
            ->groupBy('property_id')
            ->map(function ($rows) {
                $dates = [];
                foreach ($rows as $b) {
                    for ($d = $b->check_in->copy(); $d->lt($b->check_out); $d->addDay()) {
                        $dates[] = $d->toDateString();
                    }
                }

                return array_values(array_unique($dates));
            });

        $payload = $properties->map(function (Property $p) use ($booked) {
            $sleeps = (int) $p->rooms->sum(fn ($r) => (int) $r->max_adults + (int) $r->max_children);
            $configured = (int) ($p->default_guests ?? 0);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'city' => $p->city,
                'rate' => round((float) ($p->rooms->min('base_price') ?? 0), 2),
                'sleeps' => max(1, $sleeps),
                'default_guests' => $configured > 0 ? $configured : max(1, (int) floor($sleeps / 2)),
                'booked' => $booked[$p->id] ?? [],
            ];
        })->values();

        return view('tenant.bookings.send-form', [
            'properties' => $payload,
            'publicUrl' => $tenant->publicUrl(),
            'businessName' => $tenant->business_name,
            'gatewayReady' => app(CreateGatewayBill::class)->gatewayConfigured($tenant->id),
        ]);
    }

    public function create(Request $request)
    {
        $rooms = Room::query()
            ->with('property:id,name,booking_fee_amount,default_guests')
            ->where('status', '!=', 'archived')
            ->orderBy('property_id')
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'base_price', 'max_adults', 'max_children']);

        // room_id => the property's booking fee, so the form can pre-fill the
        // "Booking fee" field (the pay-now amount) when a room is chosen.
        $roomFees = $rooms->mapWithKeys(fn ($room) => [
            $room->id => round((float) ($room->property?->booking_fee_amount ?? 0), 2),
        ]);

        // room_id => { default, max } guest counts, so the form follows the
        // tenant's per-property "Default guests" + "Max guests" setup instead
        // of a hardcoded 2 adults / cap of 30.
        $roomGuests = $rooms->mapWithKeys(function ($room) {
            $sleeps = (int) $room->max_adults + (int) $room->max_children;
            $max = max(1, $sleeps);
            $configured = (int) ($room->property?->default_guests ?? 0);
            $default = $configured > 0 ? $configured : max(1, (int) floor($sleeps / 2));

            return [$room->id => [
                'default' => min($default, $max),
                'max' => $max,
            ]];
        });

        // Fallback default-guests for when no room is pre-selected yet — use
        // the first room's configured default so the Adults field reflects the
        // tenant's "Default guests" setting instead of a hardcoded 2.
        $firstRoomId = $rooms->first()?->id;
        $defaultGuests = $firstRoomId !== null ? ($roomGuests[$firstRoomId]['default'] ?? 1) : 1;

        $today = Carbon::today();

        // Occupied nights per room, so the booking form's availability calendar
        // can grey out dates that are already taken and stop the host from
        // double-booking. A booking check_in→check_out occupies the nights
        // [check_in, check_out) — the check_out morning itself is free again
        // (back-to-back arrivals allowed), matching AvailabilityService.
        $roomIds = $rooms->pluck('id');
        $roomBookedDates = [];

        Booking::query()
            ->whereIn('room_id', $roomIds)
            ->whereIn('status', [
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CHECKED_IN,
            ])
            ->where('check_out', '>=', $today->toDateString())
            ->get(['room_id', 'check_in', 'check_out'])
            ->each(function ($b) use (&$roomBookedDates) {
                $end = Carbon::parse($b->check_out)->startOfDay();
                for ($d = Carbon::parse($b->check_in)->startOfDay(); $d->lt($end); $d->addDay()) {
                    $roomBookedDates[$b->room_id][] = $d->toDateString();
                }
            });

        // Host-created calendar blocks (maintenance, owner stay, etc.) also make
        // a room unavailable. A null room_id blocks the whole property.
        $propertyIds = $rooms->pluck('property_id')->unique();
        $roomsByProperty = $rooms->groupBy('property_id');

        \App\Models\CalendarBlock::query()
            ->where('ends_on', '>=', $today->toDateString())
            ->where(function ($q) use ($roomIds, $propertyIds) {
                $q->whereIn('room_id', $roomIds)
                    ->orWhere(fn ($q2) => $q2->whereNull('room_id')->whereIn('property_id', $propertyIds));
            })
            ->get(['room_id', 'property_id', 'starts_on', 'ends_on'])
            ->each(function ($block) use (&$roomBookedDates, $roomsByProperty) {
                // A room-specific block hits that room; a property-wide block
                // (null room_id) blocks every room in the property.
                $targets = $block->room_id
                    ? [$block->room_id]
                    : ($roomsByProperty[$block->property_id] ?? collect())->pluck('id')->all();

                $end = Carbon::parse($block->ends_on)->startOfDay();
                for ($d = Carbon::parse($block->starts_on)->startOfDay(); $d->lt($end); $d->addDay()) {
                    foreach ($targets as $rid) {
                        $roomBookedDates[$rid][] = $d->toDateString();
                    }
                }
            });

        // De-duplicate + reindex each room's night list.
        $roomBookedDates = array_map(fn ($dates) => array_values(array_unique($dates)), $roomBookedDates);

        // Pre-fill check-in from the calendar deep link (?check_in=YYYY-MM-DD).
        // Never earlier than today (the field min). Check-out follows the night
        // after check-in so the stay always begins on the date the host picked.
        $prefillCheckIn = $today->toDateString();
        if ($request->filled('check_in')) {
            try {
                $picked = Carbon::parse($request->query('check_in'))->startOfDay();
                if ($picked->greaterThanOrEqualTo($today)) {
                    $prefillCheckIn = $picked->toDateString();
                }
            } catch (\Exception $e) {
                // ignore bad date — fall back to today
            }
        }
        $prefillCheckOut = Carbon::parse($prefillCheckIn)->addDay()->toDateString();

        // Pre-select the room when the deep link names a property/room, so the
        // booking is scoped to the calendar the host clicked from.
        $prefillRoomId = null;
        if ($request->filled('room_id')) {
            $prefillRoomId = $rooms->firstWhere('id', (int) $request->query('room_id'))?->id;
        }
        if ($prefillRoomId === null && $request->filled('property_id')) {
            $prefillRoomId = $rooms->firstWhere('property_id', (int) $request->query('property_id'))?->id;
        }

        return view('tenant.bookings.create', [
            'rooms' => $rooms,
            'roomFees' => $roomFees,
            'roomGuests' => $roomGuests,
            'roomBookedDates' => $roomBookedDates,
            'defaultGuests' => $defaultGuests,
            'today' => $today->toDateString(),
            'tomorrow' => Carbon::tomorrow()->toDateString(),
            'prefillCheckIn' => $prefillCheckIn,
            'prefillCheckOut' => $prefillCheckOut,
            'prefillRoomId' => $prefillRoomId,
        ]);
    }

    /**
     * Live price quote for the manual booking form (JSON).
     *
     * Returns the accommodation subtotal the PricingEngine would charge for
     * this room + date range — pricing rules (weekend / holiday / season) and
     * all — plus the taxes and booking fee stacked on top. The form pre-fills
     * its editable "Accommodation price" field from `accommodation`, so the
     * host sees the real computed price and can accept or override it.
     *
     * Read-only: touches no rows. Room lookup is tenant-scoped by the
     * BelongsToTenant global scope, so one tenant can't quote another's room.
     */
    public function quote(Request $request, \App\Services\Pricing\PricingEngine $pricing)
    {
        $validated = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')],
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'is_foreigner' => 'nullable|boolean',
        ]);

        $room = Room::with('property:id,booking_fee_amount')->find($validated['room_id']);
        abort_unless($room, 403);

        $checkIn = Carbon::parse($validated['check_in'])->startOfDay();
        $checkOut = Carbon::parse($validated['check_out'])->startOfDay();

        $quote = $pricing->quoteRange($room, $checkIn, $checkOut);

        $tenant = $room->tenant;
        $sstRate = $room->sst_applicable && $tenant->sst_registered ? (float) $tenant->sst_rate : 0;
        $sstAmount = round($quote['total'] * $sstRate, 2);

        $isForeigner = (bool) ($validated['is_foreigner'] ?? false);
        $tourismTax = $isForeigner
            ? round((float) config('homestay.tourism_tax_per_night_foreigner', 10) * $quote['count'], 2)
            : 0;

        $bookingFee = round((float) ($room->property->booking_fee_amount ?? 0), 2);

        return response()->json([
            'accommodation' => $quote['total'],
            'nights' => $quote['count'],
            // Rate (not just the amount) so the form can re-derive SST when the
            // host overrides the accommodation price — same maths as CreateBooking.
            'sst_rate' => $sstRate,
            'sst' => $sstAmount,
            'tourism_tax' => $tourismTax,
            'booking_fee' => $bookingFee,
            'total' => round($quote['total'] + $sstAmount + $tourismTax + $bookingFee, 2),
        ]);
    }

    public function store(Request $request, CreateBooking $createBooking)
    {
        $validated = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')],
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guest_name' => 'required|string|max:120',
            'guest_email' => 'nullable|email|max:160',
            'guest_phone' => 'nullable|string|max:30',
            'guest_country' => 'nullable|string|size:2',
            'adults' => 'required|integer|min:1|max:30',
            'children' => 'nullable|integer|min:0|max:30',
            'is_foreigner' => 'nullable|boolean',
            'channel' => ['required', Rule::in([
                Booking::CHANNEL_DIRECT,
                Booking::CHANNEL_MARKETPLACE,
                Booking::CHANNEL_WALK_IN,
            ])],
            // Accommodation subtotal (room nights, before taxes + booking fee).
            // Pre-filled from the live PricingEngine quote but host-editable —
            // when omitted, CreateBooking falls back to the quote.
            'base_amount' => 'nullable|numeric|min:0|max:1000000',
            'deposit_amount' => 'required|numeric|min:0|max:1000000',
            'reminder_days' => 'nullable|integer|min:0|max:60',
            'special_requests' => 'nullable|string|max:1000',
            // Manual payment shortcut — when the guest paid the host directly
            // (cash / bank transfer) the host can record it right away instead
            // of issuing a Toyyibpay link.
            'payment_received' => ['nullable', Rule::in(['none', 'booking_fee', 'full'])],
        ]);

        // Confirm the room belongs to the current tenant (BelongsToTenant scope filters this).
        abort_unless(Room::find($validated['room_id']), 403);

        // Free-tier monthly booking cap. Paid / trialing tenants are unlimited.
        $tenant = app(\App\Support\Tenancy\TenantContext::class)->current();
        if ($tenant && ! \App\Support\Billing\PlanLimits::canAddBooking($tenant)) {
            return back()
                ->withInput()
                ->with('error', __('You\'ve reached your Free plan limit of :n bookings this month. Upgrade to Pro for unlimited bookings.', [
                    'n' => \App\Support\Billing\PlanLimits::maxBookingsPerMonth(),
                ]));
        }

        $validated['is_foreigner'] = (bool) ($validated['is_foreigner'] ?? false);
        $validated['guest_country'] = $validated['guest_country'] ?? 'MY';
        $paymentReceived = $validated['payment_received'] ?? 'none';

        try {
            $booking = $createBooking->execute($validated);
        } catch (\RuntimeException $e) {
            return back()
                ->withInput()
                ->with('status', __('Could not create booking: :error', ['error' => $e->getMessage()]));
        }

        // Record an upfront manual payment if the host says the guest already
        // paid. applyManualPayment() also fires the confirmation comms + syncs
        // Google Calendar, so we only push GCal separately when nothing's paid.
        if (in_array($paymentReceived, ['booking_fee', 'full'], true)) {
            $amount = $this->applyManualPayment($booking, $paymentReceived);
            $status = $paymentReceived === 'full'
                ? __('Booking :ref created — marked fully paid (RM :amt).', ['ref' => $booking->reference, 'amt' => number_format($amount, 2)])
                : __('Booking :ref created — booking fee marked paid (RM :amt).', ['ref' => $booking->reference, 'amt' => number_format($amount, 2)]);
        } else {
            // Sync to the tenant's connected Google Calendar. The job no-ops when
            // GCal isn't connected, so this is safe for every manual booking.
            \App\Jobs\PushBookingToGoogleCalendar::dispatch($booking->id);
            $status = __('Booking :ref created.', ['ref' => $booking->reference]);
        }

        return redirect()
            ->route('tenant.bookings.show', $booking->id)
            ->with('status', $status);
    }

    /**
     * Record a manual (cash / bank-transfer) payment against a booking and
     * advance its payment status — the single source of truth used by both the
     * create form's "Payment received" shortcut and the show page's
     * "Mark booking fee paid" / "Mark fully paid" actions.
     *
     * $kind is 'booking_fee' (just the deposit / booking fee) or 'full' (the
     * entire outstanding balance). Idempotent: only the not-yet-paid portion is
     * recorded and a paid date is never re-stamped, so calling it twice is safe.
     * Returns the RM amount recorded (may be 0 if already covered).
     */
    protected function applyManualPayment(Booking $booking, string $kind): float
    {
        $booking->loadMissing('payments');

        $now = now();
        $totalPaid = (float) $booking->payments->where('status', Payment::STATUS_SUCCEEDED)->sum('amount');

        // Target = booking fee for a fee-only payment, the full total otherwise.
        $target = $kind === 'booking_fee'
            ? round((float) $booking->deposit_amount, 2)
            : round((float) $booking->total_amount, 2);
        $amount = round(max(0, $target - $totalPaid), 2);

        $wasPending = $booking->status === Booking::STATUS_PENDING;

        $payment = null;
        if ($amount > 0) {
            $type = $kind === 'booking_fee'
                ? Payment::TYPE_DEPOSIT
                : ($booking->deposit_paid_at ? Payment::TYPE_BALANCE : Payment::TYPE_FULL);

            $payment = Payment::create([
                'tenant_id' => $booking->tenant_id,
                'public_id' => Str::ulid(),
                'booking_id' => $booking->id,
                'type' => $type,
                'method' => Payment::METHOD_MANUAL,
                'gateway_provider' => null,
                'currency' => $booking->currency ?? 'MYR',
                'amount' => $amount,
                'gateway_fee' => 0,
                'platform_fee' => 0,
                'net_to_tenant' => $amount,
                'status' => Payment::STATUS_SUCCEEDED,
                'paid_at' => $now,
            ]);
        }

        // Booking fee paid → stamp deposit_paid_at + confirm. Full → also stamp
        // balance_paid_at. Existing paid dates are preserved.
        $update = ['deposit_paid_at' => $booking->deposit_paid_at ?? $now];
        if ($kind === 'full') {
            $update['balance_paid_at'] = $booking->balance_paid_at ?? $now;
        }
        if ($wasPending) {
            $update['status'] = Booking::STATUS_CONFIRMED;
        }
        $booking->update($update);

        // First time we've recognized this booking as confirmed → fire the
        // confirmation comms (email + WhatsApp) + sync to Google Calendar +
        // auto-schedule housekeeping (turnover + laundry + pre-arrival dusting).
        if ($wasPending) {
            SendBookingConfirmation::dispatch($booking->id);
            \App\Jobs\PushBookingToGoogleCalendar::dispatch($booking->id);
            try {
                app(\App\Actions\Operations\GenerateOperationalTasksForBooking::class)
                    ->execute($booking->fresh(['property']));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Generate + send an official receipt for the money just recorded
        // (email + WhatsApp), mirroring the gateway settlement flow. So a
        // manually-paid booking fee yields a fee receipt, and marking the
        // balance / full payment yields the full receipt the guest expects.
        //
        // Receipt documents are a paid feature; on the free plan the payment is
        // still recorded and the booking still confirms, there's just no receipt.
        if ($payment && Feature::for($booking->tenant)->active('invoice_documents')) {
            try {
                $receipt = app(GenerateInvoice::class)->execute(
                    $booking->fresh(['property', 'tenant', 'bookingGuests']),
                    $payment,
                    Invoice::TYPE_RECEIPT,
                );
                SendBookingReceipt::dispatch($booking->id, $receipt->id, $payment->id);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $amount;
    }

    /**
     * Quick status change from the bookings list (inline dropdown). A direct
     * override — it stamps the matching timestamp (checked_in_at/out_at/
     * cancelled_at) for consistency but does NOT run the CancelBooking
     * side-effects (guest notice, task cancellation, GCal removal). Use the
     * "Cancel booking" button on the show page for a real, guest-notifying
     * cancellation.
     */
    public function updateStatus(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_NO_SHOW,
            ])],
        ]);

        $new = $validated['status'];
        $wasCheckedOut = $booking->status === Booking::STATUS_CHECKED_OUT;
        $now = now();
        $updates = ['status' => $new];

        switch ($new) {
            case Booking::STATUS_CHECKED_IN:
                $updates['checked_in_at'] = $booking->checked_in_at ?? $now;
                $updates['cancelled_at'] = null;
                break;
            case Booking::STATUS_CHECKED_OUT:
                $updates['checked_in_at'] = $booking->checked_in_at ?? $now;
                $updates['checked_out_at'] = $booking->checked_out_at ?? $now;
                $updates['cancelled_at'] = null;
                break;
            case Booking::STATUS_CANCELLED:
            case Booking::STATUS_NO_SHOW:
                $updates['cancelled_at'] = $booking->cancelled_at ?? $now;
                break;
            case Booking::STATUS_PENDING:
            case Booking::STATUS_CONFIRMED:
                // Re-activating a previously-cancelled booking clears the stamp.
                $updates['cancelled_at'] = null;
                break;
        }

        $booking->update($updates);

        // Auto-request a testimonial the first time this transitions to
        // checked-out. SendReviewRequest dedupes on review_requested_at, so the
        // guest is asked at most once across every checkout path.
        if ($new === Booking::STATUS_CHECKED_OUT && ! $wasCheckedOut) {
            SendReviewRequest::dispatch($booking->id);
        }

        // Reflect the status change on Google Calendar — the job creates,
        // updates, or removes the event based on the new status (e.g.
        // cancelled / no-show removes it). No-ops if GCal isn't connected.
        \App\Jobs\PushBookingToGoogleCalendar::dispatch($booking->id);

        return back()->with('status', __('Booking :ref status set to :s.', [
            'ref' => $booking->reference,
            's' => Booking::statusLabel($new),
        ]));
    }

    public function edit($id)
    {
        $booking = Booking::query()
            ->with(['guest:id,name,email,phone', 'property:id,name', 'room:id,name', 'bookingGuests'])
            ->findOrFail($id);

        $rooms = Room::query()
            ->with('property:id,name')
            ->where('status', '!=', 'archived')
            ->orderBy('property_id')
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'base_price', 'max_adults', 'max_children']);

        return view('tenant.bookings.edit', compact('booking', 'rooms'));
    }

    /**
     * Update an existing booking in place. Unlike store(), this does NOT
     * recompute pricing — the host edits amounts directly. That's deliberate:
     * imported / historical bookings carry exact agreed prices that must not
     * be overwritten by the room-rate engine. `nights` is re-derived from the
     * dates; `deposit_pct` is re-derived from deposit ÷ total.
     *
     * Note: setting payment status to `cancelled` here is a raw override — it
     * does NOT run the CancelBooking side-effects (free dates, cancel tasks,
     * notify guest). Use the dedicated "Cancel booking" button for a real
     * cancellation.
     */
    public function update(Request $request, $id)
    {
        $booking = Booking::with(['bookingGuests', 'guest'])->findOrFail($id);

        $validated = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')],
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'guest_name' => 'required|string|max:120',
            'guest_email' => 'nullable|email|max:160',
            'guest_phone' => 'nullable|string|max:30',
            'guest_country' => 'nullable|string|size:2',
            'adults' => 'required|integer|min:1|max:60',
            'children' => 'nullable|integer|min:0|max:60',
            'is_foreigner' => 'nullable|boolean',
            'channel' => ['required', Rule::in([
                Booking::CHANNEL_DIRECT,
                Booking::CHANNEL_MARKETPLACE,
                Booking::CHANNEL_WALK_IN,
                Booking::CHANNEL_BOOKING,
                Booking::CHANNEL_AIRBNB,
            ])],
            'payment_status' => ['required', Rule::in(array_keys(Booking::paymentStatusOptions()))],
            'base_amount' => 'required|numeric|min:0|max:1000000',
            'total_amount' => 'required|numeric|min:0|max:1000000',
            'deposit_amount' => 'nullable|numeric|min:0|max:1000000',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        // Accept locally-typed Malaysian phones ("0127964501") and store them
        // in E.164 so wa.me links + the WhatsApp sender work without a manual
        // +60 prefix. Unparseable input is kept verbatim.
        if (! empty($validated['guest_phone'])) {
            $validated['guest_phone'] = \App\Services\WhatsApp\PhoneNumber::normalize($validated['guest_phone']) ?? $validated['guest_phone'];
        }

        // Room must belong to the current tenant (BelongsToTenant scope filters this).
        $room = Room::find($validated['room_id']);
        abort_unless($room, 403);

        $checkIn = Carbon::parse($validated['check_in'])->startOfDay();
        $checkOut = Carbon::parse($validated['check_out'])->startOfDay();

        // Translate the chosen merged payment-status into concrete column
        // updates (lifecycle status + payment timestamps).
        $statusUpdates = $booking->paymentStatusUpdates($validated['payment_status']);
        $resultingStatus = $statusUpdates['status'] ?? $booking->status;
        $wasCheckedOut = $booking->status === Booking::STATUS_CHECKED_OUT;

        // Date-overlap guard — only for bookings that still hold the room
        // (pending/confirmed/checked-in). Past/cancelled bookings don't block.
        // Excludes THIS booking so editing its own dates never self-conflicts.
        if (in_array($resultingStatus, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN], true)) {
            $conflict = Booking::query()
                ->withoutGlobalScopes()
                ->where('room_id', $room->id)
                ->where('id', '!=', $booking->id)
                ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN])
                ->where('check_in', '<', $checkOut->toDateString())
                ->where('check_out', '>', $checkIn->toDateString())
                ->exists();

            if ($conflict) {
                return back()->withInput()->with('status', __('Those dates overlap another active booking on this room.'));
            }
        }

        $nights = max(1, $checkIn->diffInDays($checkOut));
        $isForeigner = (bool) ($validated['is_foreigner'] ?? false);
        $total = round((float) $validated['total_amount'], 2);
        $deposit = round((float) ($validated['deposit_amount'] ?? $booking->deposit_amount), 2);

        DB::transaction(function () use ($booking, $room, $validated, $checkIn, $checkOut, $nights, $isForeigner, $total, $deposit, $statusUpdates) {
            $booking->update(array_merge([
                'room_id' => $room->id,
                'property_id' => $room->property_id,
                'channel' => $validated['channel'],
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => $nights,
                'adults' => (int) $validated['adults'],
                'children' => (int) ($validated['children'] ?? 0),
                'is_foreigner' => $isForeigner,
                'base_amount' => round((float) $validated['base_amount'], 2),
                'total_amount' => $total,
                'deposit_amount' => $deposit,
                'deposit_pct' => $total > 0 ? round($deposit / $total * 100, 2) : 0,
                'special_requests' => $validated['special_requests'] ?? null,
            // Lifecycle status + payment timestamps from the merged payment-status.
            ], $statusUpdates));

            // Keep the lead BookingGuest row in step with the edited contact.
            $lead = $booking->bookingGuests->firstWhere('is_lead', true) ?? $booking->bookingGuests->first();
            if ($lead) {
                $lead->update([
                    'full_name' => $validated['guest_name'],
                    'email' => $validated['guest_email'] ?? null,
                    'phone' => $validated['guest_phone'] ?? null,
                    'country' => $validated['guest_country'] ?? 'MY',
                    'is_foreigner' => $isForeigner,
                ]);
            }

            // The bookings list + calendar display the linked User's name, so
            // sync name/phone there too. Email is only updated when it's new
            // and not already taken (the users.email column is unique).
            if ($guest = $booking->guest) {
                $changes = ['name' => $validated['guest_name']];
                if (! empty($validated['guest_phone'])) {
                    $changes['phone'] = $validated['guest_phone'];
                }
                $newEmail = $validated['guest_email'] ?? null;
                if ($newEmail && $newEmail !== $guest->email
                    && ! User::where('email', $newEmail)->where('id', '!=', $guest->id)->exists()) {
                    $changes['email'] = $newEmail;
                }
                $guest->update($changes);
            }
        });

        // Auto-request a testimonial the first time the edit transitions the
        // booking to checked-out. Deduped by SendReviewRequest so the guest is
        // asked at most once across every checkout path.
        if ($resultingStatus === Booking::STATUS_CHECKED_OUT && ! $wasCheckedOut) {
            SendReviewRequest::dispatch($booking->id);
        }

        // Push the edit through to Google Calendar — the job patches the
        // existing event (or creates/removes one to match the new status +
        // dates). No-ops if GCal isn't connected.
        \App\Jobs\PushBookingToGoogleCalendar::dispatch($booking->id);

        return redirect()
            ->route('tenant.bookings.show', $booking->id)
            ->with('status', __('Booking :ref updated.', ['ref' => $booking->reference]));
    }

    public function show($id)
    {
        $booking = Booking::query()
            ->with(['guest:id,name,email,phone', 'leadGuest', 'property:id,name,city', 'room:id,name', 'payments', 'refunds.processedBy:id,name', 'review'])
            ->findOrFail($id);

        return view('tenant.bookings.show', compact('booking'));
    }

    /**
     * Mark a booking as manually paid — the host collected the money directly
     * (cash / bank transfer). `kind=booking_fee` records just the booking fee
     * and confirms the booking; `kind=full` (default) settles the whole
     * outstanding balance. Both run through applyManualPayment().
     */
    public function markPaid(Request $request, $id)
    {
        $booking = Booking::with('payments')->findOrFail($id);

        $kind = $request->input('kind', 'full');
        if (! in_array($kind, ['booking_fee', 'full'], true)) {
            $kind = 'full';
        }

        $totalPaid = (float) $booking->payments->where('status', Payment::STATUS_SUCCEEDED)->sum('amount');

        if ($kind === 'booking_fee' && $booking->deposit_paid_at) {
            return back()->with('status', __('Booking fee is already recorded as paid.'));
        }
        if ($kind === 'full' && $booking->balance_paid_at && ((float) $booking->total_amount - $totalPaid) <= 0) {
            return back()->with('status', __('Booking is already fully paid.'));
        }

        $amount = $this->applyManualPayment($booking, $kind);

        $status = $kind === 'booking_fee'
            ? __('Booking fee marked as paid (RM :amount recorded).', ['amount' => number_format($amount, 2)])
            : __('Booking marked as fully paid (RM :amount recorded).', ['amount' => number_format($amount, 2)]);

        return back()->with('status', $status);
    }

    public function sendReminder(Request $request, $id)
    {
        $booking = Booking::with(['guest:id,name,email,phone', 'property:id,name'])->findOrFail($id);

        if (! $booking->guest?->email && ! $booking->guest?->phone) {
            return back()->with('status', __('No guest email or phone on file — cannot send reminder.'));
        }

        SendPaymentReminder::dispatch($booking->id);
        $booking->update(['full_payment_reminder_at' => now()]);

        return back()->with('status', __('Payment reminder queued (email + WhatsApp where available).'));
    }

    /**
     * Generate a payment link for this booking via the tenant's active gateway
     * (Toyyibpay or Billplz). Returns the URL via flash so the tenant can copy +
     * share it (or send via WA/email).
     *
     * Reuses an existing processing bill if one already exists rather than
     * double-billing the guest.
     */
    public function payLink(Request $request, CreateGatewayBill $action, $id)
    {
        $booking = Booking::with(['property', 'guest', 'bookingGuests', 'tenant', 'payments'])->findOrFail($id);

        $type = $request->input('type', Payment::TYPE_DEPOSIT);
        if (! in_array($type, [Payment::TYPE_DEPOSIT, Payment::TYPE_BALANCE, Payment::TYPE_FULL], true)) {
            $type = Payment::TYPE_DEPOSIT;
        }

        $totalPaid = (float) $booking->payments->where('status', Payment::STATUS_SUCCEEDED)->sum('amount');
        $amount = match ($type) {
            Payment::TYPE_DEPOSIT => (float) $booking->deposit_amount,
            Payment::TYPE_BALANCE => max(0, (float) $booking->total_amount - $totalPaid),
            Payment::TYPE_FULL    => max(0, (float) $booking->total_amount - $totalPaid),
        };

        if ($amount <= 0) {
            return back()->with('status', __('Nothing left to charge — booking is fully paid.'));
        }

        try {
            $result = $action->execute($booking, $type, $amount);
        } catch (PaymentGatewayException $e) {
            return back()->with('status', __('Payment gateway error: :err', ['err' => Str::limit($e->getMessage(), 200)]));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('status', __('Could not create payment link. See logs for details.'));
        }

        return back()
            ->with('status', __('Payment link ready: :url', ['url' => $result['payment_url']]))
            ->with('pay_link', $result['payment_url'])
            ->with('pay_link_reused', $result['reused']);
    }

    /**
     * Manual "Send via WhatsApp" — dispatches a booking confirmation message
     * via the tenant's connected WhatsApp session. Routed by /bookings/{id}/whatsapp.
     */
    public function sendWhatsapp(Request $request, $id)
    {
        // Load the full property so address/map_url/lat/lng/check_in_time are
        // available to the checkin + location templates (a bare id,name select
        // left those blank).
        $booking = Booking::with(['guest:id,name,phone', 'property', 'tenant'])->findOrFail($id);

        if (! $booking->tenant?->whatsappSession?->isConnected()) {
            return back()->with('status', __('Connect WhatsApp first under Integrations → WhatsApp.'));
        }

        if (! $booking->guest?->phone) {
            return back()->with('status', __('No guest phone on file — cannot send.'));
        }

        $kind = $request->input('kind', 'confirmation');
        $msg = match ($kind) {
            'reminder' => WhatsappMessenger::dispatchReminder($booking),
            'checkin'  => WhatsappMessenger::dispatchCheckin($booking),
            'location' => WhatsappMessenger::dispatchLocation($booking),
            default    => WhatsappMessenger::dispatchConfirmation($booking),
        };

        if (! $msg) {
            return back()->with('status', __('Message could not be queued (auto-send may be off, or the recipient is not a booked guest).'));
        }

        return back()->with('status', __('WhatsApp :kind queued.', ['kind' => $kind]));
    }

    /**
     * Cancel a booking (soft — flips status, doesn't delete the row).
     * Frees the room dates back to availability and cancels any
     * scheduled cleaning/laundry tasks linked to the booking.
     *
     * Hard rules:
     *   - Already-cancelled / no-show bookings: no-op (flash a note).
     *   - Checked-out bookings: refused. Once the guest has departed
     *     the booking is settled history; deleting it would orphan
     *     payments, invoices and commission records.
     *   - Refunds NOT auto-issued. Host arranges any refund outside
     *     the platform; the booking record stays for the audit trail.
     */
    public function cancel(Request $request, \App\Actions\Booking\CancelBooking $cancelBooking, $id)
    {
        $booking = Booking::findOrFail($id);

        if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW], true)) {
            return back()->with('status', __('Booking is already cancelled.'));
        }
        if ($booking->status === Booking::STATUS_CHECKED_OUT) {
            return back()->with('error', __('Cannot cancel — guest has already checked out. Past bookings are kept for the audit trail.'));
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Shared cancel logic: status flip + free dates + cancel tasks +
        // remove GCal event + notify the guest (email + WhatsApp).
        $cancelBooking->execute($booking, $validated['reason'] ?? null);

        return redirect()
            ->route('tenant.bookings.show', $booking->id)
            ->with('status', __('Booking :ref cancelled. Dates are now available again.', [
                'ref' => $booking->reference,
            ]));
    }

    /**
     * Mark guest as checked out. Stamps booking.checked_out_at and
     * status=checked_out. Auto-creates a pending Refund row for the
     * deposit amount (the property's booking fee) so the host has a
     * clear next action: transfer the money back + record the bank ref.
     *
     * Idempotent — re-POSTing on an already-checked-out booking
     * doesn't create duplicate refunds (we check for any open refund
     * row first).
     */
    public function checkOut(Request $request, $id, \App\Actions\Booking\CheckOutBooking $checkOutBooking)
    {
        $booking = Booking::findOrFail($id);

        if (! $checkOutBooking->execute($booking)) {
            return back()->with('error', __('Cannot check out — booking status is :s.', ['s' => $booking->status]));
        }

        return back()->with('status', __('Guest checked out. Refund prepared.'));
    }

    /**
     * Manually (re-)send the "leave a testimonial" request. Used from the
     * booking page when the host wants to nudge a guest who hasn't reviewed yet.
     * Re-sends are allowed (unlike the auto path); no-op once a review exists.
     */
    public function requestReview(Request $request, $id)
    {
        $booking = Booking::with(['guest:id,name,email,phone', 'review'])->findOrFail($id);

        if ($booking->status !== Booking::STATUS_CHECKED_OUT) {
            return back()->with('error', __('You can only request a testimonial after the guest has checked out.'));
        }
        if ($booking->review) {
            return back()->with('status', __('This guest has already left a testimonial.'));
        }
        if (! $booking->guestEmail() && ! $booking->guestPhone()) {
            return back()->with('error', __('No guest email or phone on file — cannot send the request.'));
        }

        // force: this is an explicit host re-send, so it bypasses the once-only
        // claim (but still no-ops above once a review exists).
        SendReviewRequest::dispatch($booking->id, force: true);

        return back()->with('status', __('Testimonial request sent (email + WhatsApp where available).'));
    }

    /**
     * Hard-delete a booking + all linked operational rows.
     * Intended for test/cleanup use. NOT a normal flow — `cancel()`
     * is the right action for real cancellations.
     *
     * Refuses if the booking has linked Review / IncidentReport /
     * Dispute / GuestBlacklistEntry rows — those are audit-grade and
     * destroying them would lose evidence the platform needs for
     * future complaints. Cancel instead.
     *
     * Cascade deletes (in one transaction):
     *   - BookingGuest, Payment, Invoice, Commission
     *   - CleaningTask, LaundryTask
     *   - WhatsappMessage where booking_id = this booking
     *   - The booking itself
     */
    public function destroy(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $auditBlockers = [
            'reviews'         => Review::where('booking_id', $booking->id)->count(),
            'incidents'       => IncidentReport::where('booking_id', $booking->id)->count(),
            'disputes'        => Dispute::where('booking_id', $booking->id)->count(),
            'blacklist'       => GuestBlacklistEntry::where('booking_id', $booking->id)->count(),
        ];
        $totalAudit = array_sum($auditBlockers);
        if ($totalAudit > 0) {
            $parts = [];
            foreach ($auditBlockers as $k => $n) {
                if ($n > 0) $parts[] = "{$n} {$k}";
            }
            return back()->with('error', __('Cannot delete — this booking has linked records (:items). Cancel it instead so the audit trail stays intact.', [
                'items' => implode(', ', $parts),
            ]));
        }

        $ref = $booking->reference;

        // Capture GCal event pointers BEFORE the delete — the row's gone
        // afterwards, so the smart-sync job can't lookup by booking_id.
        // We queue a raw-id delete instead.
        $gcalEventId    = $booking->meta['google_event_id'] ?? null;
        $gcalCalendarId = $booking->meta['google_calendar_id'] ?? null;
        $gcalTenantId   = $booking->tenant_id;

        DB::transaction(function () use ($booking) {
            // Order matters only loosely — none of these have FKs between
            // each other, just back-references to the booking row that
            // we delete last.
            BookingGuest::where('booking_id', $booking->id)->delete();
            Invoice::where('booking_id', $booking->id)->delete();
            Commission::where('booking_id', $booking->id)->delete();
            CleaningTask::where('booking_id', $booking->id)->delete();
            LaundryTask::where('booking_id', $booking->id)->delete();
            WhatsappMessage::where('booking_id', $booking->id)->delete();
            // Payment last (commission may FK to it).
            Payment::where('booking_id', $booking->id)->delete();
            $booking->delete();
        });

        // Now that the transaction is committed, fire the Google delete.
        if ($gcalEventId) {
            \App\Jobs\DeleteGoogleCalendarEvent::dispatch($gcalTenantId, $gcalEventId, $gcalCalendarId);
        }

        return redirect()
            ->route('tenant.bookings.index')
            ->with('status', __('Booking :ref permanently deleted along with its payments, invoices and tasks.', [
                'ref' => $ref,
            ]));
    }
}
