<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Booking\CreateBooking;
use App\Actions\Payments\CreateToyyibpayBill;
use App\Http\Controllers\Controller;
use App\Jobs\SendBookingConfirmation;
use App\Jobs\SendPaymentReminder;
use App\Models\Booking;
use App\Models\CleaningTask;
use App\Models\LaundryTask;
use App\Models\Payment;
use App\Models\Room;
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
            ->with('property:id,name')
            ->where('status', '!=', 'archived')
            ->orderBy('property_id')
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'base_price', 'max_adults', 'max_children']);

        return view('tenant.bookings.create', [
            'rooms' => $rooms,
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
            'deposit_pct' => 'required|numeric|min:0|max:100',
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

    public function show($id)
    {
        $booking = Booking::query()
            ->with(['guest:id,name,email,phone', 'property:id,name,city', 'room:id,name', 'payments'])
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
        // confirmation comms (email + WhatsApp).
        if ($wasPending) {
            SendBookingConfirmation::dispatch($booking->id);
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
    public function cancel(Request $request, $id)
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

        DB::transaction(function () use ($booking, $validated) {
            $booking->update([
                'status'              => Booking::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => $validated['reason'] ?? null,
            ]);

            // Cancel any scheduled cleaning + laundry tasks for this
            // booking — they're no longer needed. Tasks already in
            // progress / completed are left alone (work was done).
            CleaningTask::where('booking_id', $booking->id)
                ->whereIn('status', ['pending', 'scheduled'])
                ->update(['status' => 'cancelled']);

            LaundryTask::where('booking_id', $booking->id)
                ->whereIn('status', ['pending', 'scheduled'])
                ->update(['status' => 'cancelled']);
        });

        return redirect()
            ->route('tenant.bookings.show', $booking->id)
            ->with('status', __('Booking :ref cancelled. Dates are now available again.', [
                'ref' => $booking->reference,
            ]));
    }
}
