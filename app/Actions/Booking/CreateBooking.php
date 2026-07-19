<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\Commission;
use App\Models\Room;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Pricing\PricingEngine;
use App\Services\WhatsApp\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateBooking
{
    public function __construct(
        protected AvailabilityService $availability,
        protected PricingEngine $pricing,
    ) {}

    public function execute(array $data): Booking
    {
        // Accept guest phones typed the easy Malaysian way ("0127964501") and
        // store them in E.164 ("+60127964501") so every downstream use — the
        // WhatsApp sender and the wa.me links — works without the host having
        // to prefix +60 by hand. Unparseable input is left as-is.
        if (! empty($data['guest_phone'])) {
            $data['guest_phone'] = PhoneNumber::normalize($data['guest_phone']) ?? $data['guest_phone'];
        }

        $room = Room::withoutGlobalScopes()->findOrFail($data['room_id']);
        $checkIn = CarbonImmutable::parse($data['check_in']);
        $checkOut = CarbonImmutable::parse($data['check_out']);

        if (! $this->availability->isAvailable($room, $checkIn, $checkOut)) {
            throw new \RuntimeException('Selected dates are not available.');
        }

        return DB::transaction(function () use ($data, $room, $checkIn, $checkOut) {
            $quote = $this->pricing->quoteRange($room, $checkIn, $checkOut);

            // Accommodation subtotal (room nights, before SST / tourism tax /
            // booking fee). Defaults to the PricingEngine quote — which already
            // applies the tenant's pricing rules — but a host creating a manual
            // booking may override it with an agreed price (the "Accommodation
            // price" field). Everything downstream (SST, total, marketplace
            // commission, payout) is derived from this one number.
            $priceOverridden = array_key_exists('base_amount', $data) && $data['base_amount'] !== null;
            $accommodation = $priceOverridden
                ? round(max(0, (float) $data['base_amount']), 2)
                : $quote['total'];

            $tenant = $room->tenant;
            $sstRate = $room->sst_applicable && $tenant->sst_registered ? (float) $tenant->sst_rate : 0;
            $sstAmount = round($accommodation * $sstRate, 2);

            $isForeigner = (bool) ($data['is_foreigner'] ?? false);
            $tourismTax = $isForeigner ? round((float) config('homestay.tourism_tax_per_night_foreigner', 10) * $quote['count'], 2) : 0;

            // Per-booking flat fee, snapshotted from the property so future
            // changes to the property's fee don't retroactively alter
            // existing bookings. NULL/0 → no fee, line omitted everywhere.
            // A caller may override it with an agreed amount (the "Send booking
            // form" custom booking fee) — it's added to the total and, unless a
            // deposit is set explicitly, becomes the pay-now amount, exactly
            // like the property default.
            $bookingFee = array_key_exists('booking_fee', $data) && $data['booking_fee'] !== null
                ? round(max(0, (float) $data['booking_fee']), 2)
                : round((float) ($room->property->booking_fee_amount ?? 0), 2);

            $total = round($accommodation + $sstAmount + $tourismTax + $bookingFee, 2);

            // Last-minute guard: a booking made INSIDE the tenant's
            // full-payment lead time (default 7 days before check-in) must be
            // paid IN FULL to confirm — there's no runway to send the
            // "X days before check-in" balance reminder and collect the
            // balance before the guest arrives. Applies only to the public /
            // default pay-now path; a host entering a manual booking with an
            // explicit deposit_amount/deposit_pct keeps full control.
            $leadDays = $tenant->fullPaymentDaysBefore();
            $daysToCheckIn = CarbonImmutable::now()->startOfDay()->diffInDays($checkIn->startOfDay(), false);
            $requiresFullPayment = $daysToCheckIn < $leadDays;

            // Deposit / pay-now logic:
            // - When the caller passes a fixed `deposit_amount` (host
            //   creating a manual booking in the dashboard — the
            //   "Booking fee" field), that flat RM amount IS the pay-now
            //   amount and the percentage is back-derived from it.
            // - When the caller passes `deposit_pct` explicitly, we compute
            //   a percentage-based deposit as before (legacy callers).
            // - When the booking is last-minute (see above), the WHOLE total
            //   is the pay-now amount — full payment required for confirmation.
            // - Otherwise (public booking flow with runway), the property's
            //   flat booking fee IS the pay-now amount — much friendlier
            //   for Malaysian homestay guests than "deposit (20%)".
            if (array_key_exists('deposit_amount', $data) && $data['deposit_amount'] !== null) {
                $depositAmt = round((float) $data['deposit_amount'], 2);
                $depositPct = $total > 0 ? round(($depositAmt / $total) * 100, 2) : 0;
            } elseif (array_key_exists('deposit_pct', $data) && $data['deposit_pct'] !== null) {
                $depositPct = (float) $data['deposit_pct'];
                $depositAmt = round($total * ($depositPct / 100), 2);
            } elseif ($requiresFullPayment) {
                $depositAmt = $total;
                $depositPct = 100.0;
            } elseif ($bookingFee > 0) {
                $depositAmt = $bookingFee;
                $depositPct = $total > 0 ? round(($bookingFee / $total) * 100, 2) : 0;
            } else {
                // Fallback for properties without a fee — keep the
                // historical 20% so callers that rely on a non-zero
                // deposit_amount still work.
                $depositPct = 20.0;
                $depositAmt = round($total * 0.20, 2);
            }

            $channel = $data['channel'] ?? Booking::CHANNEL_DIRECT;
            // 0% on every tier by default (3-tier pricing charges subscriptions,
            // not commissions) — the rate stays configurable as an escape hatch.
            $commissionAmt = $channel === Booking::CHANNEL_MARKETPLACE
                ? round($accommodation * (float) config('homestay.marketplace_commission_rate', 0.0), 2)
                : 0;

            // Payment-lifecycle deadlines, driven by the tenant's policy:
            //  - balance/full-payment reminder + due date: X days before check-in
            //    (caller may override the lead time via reminder_days).
            //  - booking-fee deadline: now + fee_payment_hours (after which an
            //    unpaid pending booking auto-cancels).
            $reminderDays = array_key_exists('reminder_days', $data) && $data['reminder_days'] !== null
                ? (int) $data['reminder_days']
                : $tenant->fullPaymentDaysBefore();
            $balanceDueAt = $checkIn->subDays($reminderDays);
            $feeDueAt = CarbonImmutable::now()->addHours($tenant->feePaymentHours());

            $guest = $this->resolveGuest($data);

            $booking = Booking::create([
                'tenant_id' => $room->tenant_id,
                'property_id' => $room->property_id,
                'room_id' => $room->id,
                'guest_id' => $guest?->id,
                'channel' => $channel,
                'status' => Booking::STATUS_PENDING,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'nights' => $quote['count'],
                'adults' => $data['adults'] ?? 1,
                'children' => $data['children'] ?? 0,
                'currency' => 'MYR',
                'base_amount' => $accommodation,
                'sst_amount' => $sstAmount,
                'tourism_tax_amount' => $tourismTax,
                'booking_fee_amount' => $bookingFee,
                'total_amount' => $total,
                'deposit_pct' => $depositPct,
                'deposit_amount' => $depositAmt,
                'is_foreigner' => $isForeigner,
                'commission_amount' => $commissionAmt,
                'special_requests' => $data['special_requests'] ?? null,
                'balance_due_at' => $balanceDueAt,
                'full_payment_reminder_at' => $balanceDueAt,
                'fee_due_at' => $feeDueAt,
                'meta' => [
                    'quote_breakdown' => $quote['nights'],
                    // Snapshot the refund policy the guest agreed to at booking
                    // time so it stays stable on the invoice even if the tenant
                    // edits their policy later.
                    'refund_policy' => $tenant->refundPolicyText(),
                    // Flag last-minute bookings that had to be paid in full to
                    // confirm — lets the invoice/receipt copy say "full payment"
                    // instead of "booking fee / deposit".
                    'requires_full_payment' => $requiresFullPayment,
                    // Host agreed a price that differs from the PricingEngine
                    // quote — keep the original so the difference is auditable.
                    'price_overridden' => $priceOverridden && $accommodation !== $quote['total'],
                    'quoted_accommodation' => $quote['total'],
                ],
            ]);

            BookingGuest::create([
                'booking_id' => $booking->id,
                'is_lead' => true,
                'full_name' => $data['guest_name'],
                'email' => $data['guest_email'] ?? null,
                'phone' => $data['guest_phone'] ?? null,
                'country' => $data['guest_country'] ?? 'MY',
                'is_foreigner' => $isForeigner,
            ]);

            // Only record a commission when one is actually charged — at the
            // standard 0% rate marketplace bookings create no Commission row,
            // so payouts/settlement never imply a fee.
            if ($channel === Booking::CHANNEL_MARKETPLACE && $commissionAmt > 0) {
                Commission::create([
                    'tenant_id' => $room->tenant_id,
                    'booking_id' => $booking->id,
                    'gross_amount' => $accommodation,
                    'commission_rate' => (float) config('homestay.marketplace_commission_rate', 0.0),
                    'commission_amount' => $commissionAmt,
                    'payout_amount' => round($accommodation - $commissionAmt, 2),
                    'status' => Commission::STATUS_PENDING,
                ]);
            }

            return $booking->fresh(['property', 'room', 'bookingGuests', 'commission']);
        });
    }

    protected function resolveGuest(array $data): ?User
    {
        if (! empty($data['guest_user_id'])) {
            return User::find($data['guest_user_id']);
        }

        if (! empty($data['guest_email']) || ! empty($data['guest_phone'])) {
            return User::firstOrCreate(
                ['email' => $data['guest_email'] ?? 'guest+'.uniqid().'@example.invalid'],
                [
                    'name' => $data['guest_name'],
                    'phone' => $data['guest_phone'] ?? null,
                    'user_type' => User::TYPE_GUEST,
                    // Guest users authenticate via OTP, not password — set a random
                    // unguessable hash so the NOT NULL constraint is satisfied.
                    'password' => Hash::make(Str::random(32)),
                ],
            );
        }

        return null;
    }
}
