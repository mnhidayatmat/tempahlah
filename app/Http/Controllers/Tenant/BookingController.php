<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Booking\CreateBooking;
use App\Actions\Payments\CreateToyyibpayBill;
use App\Http\Controllers\Controller;
use App\Jobs\SendBookingConfirmation;
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
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\DB;
use App\Services\Payments\Toyyibpay\ToyyibpayException;
use App\Services\WhatsApp\WhatsappMessenger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('status', 'all');
        $valid = ['all', 'upcoming', 'checked-in', 'past'];
        if (! in_array($filter, $valid, true)) {
            $filter = 'all';
        }

        $today = Carbon::today();

        $bookings = Booking::query()
            ->with(['guest:id,name,email,phone', 'property:id,name,city'])
            ->when($filter === 'upcoming', fn ($q) => $q
                ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
                ->where('check_in', '>=', $today))
            ->when($filter === 'checked-in', fn ($q) => $q
                ->where('status', Booking::STATUS_CHECKED_IN))
            ->when($filter === 'past', fn ($q) => $q
                ->where('check_out', '<', $today))
            ->orderByDesc('check_in')
            ->paginate(20)
            ->withQueryString();

        return view('tenant.bookings.index', [
            'bookings' => $bookings,
            'filter' => $filter,
        ]);
    }

    public function create()
    {
        $rooms = Room::query()
            ->with('property:id,name,booking_fee_amount')
            ->where('status', '!=', 'archived')
            ->orderBy('property_id')
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'base_price', 'max_adults', 'max_children']);

        // room_id => the property's booking fee, so the form can pre-fill the
        // "Booking fee" field (the pay-now amount) when a room is chosen.
        $roomFees = $rooms->mapWithKeys(fn ($room) => [
            $room->id => round((float) ($room->property?->booking_fee_amount ?? 0), 2),
        ]);

        return view('tenant.bookings.create', [
            'rooms' => $rooms,
            'roomFees' => $roomFees,
            'today' => Carbon::today()->toDateString(),
            'tomorrow' => Carbon::tomorrow()->toDateString(),
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
            'deposit_amount' => 'required|numeric|min:0|max:1000000',
            'reminder_days' => 'nullable|integer|min:0|max:60',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        // Confirm the room belongs to the current tenant (BelongsToTenant scope filters this).
        abort_unless(Room::find($validated['room_id']), 403);

        $validated['is_foreigner'] = (bool) ($validated['is_foreigner'] ?? false);
        $validated['guest_country'] = $validated['guest_country'] ?? 'MY';

        try {
            $booking = $createBooking->execute($validated);
        } catch (\RuntimeException $e) {
            return back()
                ->withInput()
                ->with('status', __('Could not create booking: :error', ['error' => $e->getMessage()]));
        }

        return redirect()
            ->route('tenant.bookings.show', $booking->id)
            ->with('status', __('Booking :ref created.', ['ref' => $booking->reference]));
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
     * Note: setting status to `cancelled` here is a raw override — it does NOT
     * run the CancelBooking side-effects (free dates, cancel tasks, notify
     * guest). Use the dedicated "Cancel booking" button for a real cancellation.
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
            'status' => ['required', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_NO_SHOW,
            ])],
            'base_amount' => 'required|numeric|min:0|max:1000000',
            'total_amount' => 'required|numeric|min:0|max:1000000',
            'deposit_amount' => 'nullable|numeric|min:0|max:1000000',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        // Room must belong to the current tenant (BelongsToTenant scope filters this).
        $room = Room::find($validated['room_id']);
        abort_unless($room, 403);

        $checkIn = Carbon::parse($validated['check_in'])->startOfDay();
        $checkOut = Carbon::parse($validated['check_out'])->startOfDay();

        // Date-overlap guard — only for bookings that still hold the room
        // (pending/confirmed/checked-in). Past/cancelled bookings don't block.
        // Excludes THIS booking so editing its own dates never self-conflicts.
        if (in_array($validated['status'], [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN], true)) {
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

        DB::transaction(function () use ($booking, $room, $validated, $checkIn, $checkOut, $nights, $isForeigner, $total, $deposit) {
            $booking->update([
                'room_id' => $room->id,
                'property_id' => $room->property_id,
                'channel' => $validated['channel'],
                'status' => $validated['status'],
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
            ]);

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

        return redirect()
            ->route('tenant.bookings.show', $booking->id)
            ->with('status', __('Booking :ref updated.', ['ref' => $booking->reference]));
    }

    public function show($id)
    {
        $booking = Booking::query()
            ->with(['guest:id,name,email,phone', 'property:id,name,city', 'room:id,name', 'payments', 'refunds.processedBy:id,name'])
            ->findOrFail($id);

        return view('tenant.bookings.show', compact('booking'));
    }

    public function markPaid(Request $request, $id)
    {
        $booking = Booking::with('payments')->findOrFail($id);

        $now = now();
        $totalPaid = (float) $booking->payments->where('status', Payment::STATUS_SUCCEEDED)->sum('amount');
        $remaining = max(0, (float) $booking->total_amount - $totalPaid);

        if ($remaining <= 0) {
            return back()->with('status', __('Booking is already fully paid.'));
        }

        $type = $booking->deposit_paid_at ? Payment::TYPE_BALANCE : Payment::TYPE_FULL;

        Payment::create([
            'tenant_id' => $booking->tenant_id,
            'public_id' => Str::ulid(),
            'booking_id' => $booking->id,
            'type' => $type,
            'method' => Payment::METHOD_MANUAL,
            'gateway_provider' => null,
            'currency' => $booking->currency ?? 'MYR',
            'amount' => $remaining,
            'gateway_fee' => 0,
            'platform_fee' => 0,
            'net_to_tenant' => $remaining,
            'status' => Payment::STATUS_SUCCEEDED,
            'paid_at' => $now,
        ]);

        $update = ['balance_paid_at' => $now];
        $wasPending = $booking->status === Booking::STATUS_PENDING;
        if (! $booking->deposit_paid_at) {
            $update['deposit_paid_at'] = $now;
        }
        if ($wasPending) {
            $update['status'] = Booking::STATUS_CONFIRMED;
        }
        $booking->update($update);

        // First time we've recognized this booking as confirmed → fire the
        // confirmation comms (email + WhatsApp) + sync to Google Calendar.
        if ($wasPending) {
            SendBookingConfirmation::dispatch($booking->id);
            \App\Jobs\PushBookingToGoogleCalendar::dispatch($booking->id);
        }

        return back()->with('status', __('Booking marked as paid (RM :amount recorded).', [
            'amount' => number_format($remaining, 2),
        ]));
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
     * Generate a Toyyibpay payment link for this booking. Returns the URL
     * via flash so the tenant can copy + share it (or send via WA/email).
     *
     * Reuses an existing processing bill if one already exists rather than
     * double-billing the guest.
     */
    public function payLink(Request $request, CreateToyyibpayBill $action, $id)
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
        } catch (ToyyibpayException $e) {
            return back()->with('status', __('Toyyibpay error: :err', ['err' => Str::limit($e->getMessage(), 200)]));
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
        $booking = Booking::with(['guest:id,name,phone', 'property:id,name', 'tenant'])->findOrFail($id);

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
    public function checkOut(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $allowedFrom = [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN];
        if (! in_array($booking->status, $allowedFrom, true)) {
            return back()->with('error', __('Cannot check out — booking status is :s.', ['s' => $booking->status]));
        }

        DB::transaction(function () use ($booking) {
            $booking->update([
                'status'          => Booking::STATUS_CHECKED_OUT,
                'checked_out_at'  => now(),
                // If they never explicitly checked-in, stamp it now too
                // so the timeline isn't missing a step.
                'checked_in_at'   => $booking->checked_in_at ?? now(),
            ]);

            // Auto-create the refund record. Only when a deposit was
            // actually paid AND there's no existing open refund row.
            $depositPaid = (float) ($booking->deposit_amount ?? 0);
            if ($depositPaid > 0 && $booking->deposit_paid_at) {
                $hasOpen = \App\Models\Refund::where('booking_id', $booking->id)
                    ->whereIn('status', [
                        \App\Models\Refund::STATUS_PENDING,
                        \App\Models\Refund::STATUS_PROCESSING,
                        \App\Models\Refund::STATUS_COMPLETED,
                    ])->exists();

                if (! $hasOpen) {
                    // Match the deposit payment row (typically the booking
                    // fee) so the refund is traceable to the original txn.
                    $depositPayment = $booking->payments
                        ->where('status', 'succeeded')
                        ->where('type', \App\Models\Payment::TYPE_DEPOSIT)
                        ->first();

                    \App\Models\Refund::create([
                        'public_id'    => (string) \Illuminate\Support\Str::ulid(),
                        'tenant_id'    => $booking->tenant_id,
                        'booking_id'   => $booking->id,
                        'payment_id'   => $depositPayment?->id,
                        'amount'       => $depositPaid,
                        'currency'     => $booking->currency ?? 'MYR',
                        'reason'       => \App\Models\Refund::REASON_CHECKOUT_COMPLETE,
                        'status'       => \App\Models\Refund::STATUS_PENDING,
                        'requested_at' => now(),
                    ]);
                }
            }
        });

        return back()->with('status', __('Guest checked out. Refund prepared.'));
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
