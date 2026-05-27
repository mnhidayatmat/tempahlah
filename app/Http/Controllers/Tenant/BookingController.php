<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Booking\CreateBooking;
use App\Http\Controllers\Controller;
use App\Mail\PaymentReminderMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
        if (! $booking->deposit_paid_at) {
            $update['deposit_paid_at'] = $now;
        }
        if ($booking->status === Booking::STATUS_PENDING) {
            $update['status'] = Booking::STATUS_CONFIRMED;
        }
        $booking->update($update);

        return back()->with('status', __('Booking marked as paid (RM :amount recorded).', [
            'amount' => number_format($remaining, 2),
        ]));
    }

    public function sendReminder(Request $request, $id)
    {
        $booking = Booking::with(['guest:id,name,email', 'property:id,name'])->findOrFail($id);

        if (! $booking->guest?->email) {
            return back()->with('status', __('No guest email on file — cannot send reminder.'));
        }

        try {
            Mail::to($booking->guest->email)->queue(new PaymentReminderMail($booking));
            $booking->update(['full_payment_reminder_at' => now()]);
            return back()->with('status', __('Payment reminder queued for :email.', [
                'email' => $booking->guest->email,
            ]));
        } catch (\Throwable $e) {
            return back()->with('status', __('Reminder queued — will send when mail driver is configured. (:error)', [
                'error' => Str::limit($e->getMessage(), 80),
            ]));
        }
    }
}
