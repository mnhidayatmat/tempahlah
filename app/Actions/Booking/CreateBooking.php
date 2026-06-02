<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\Commission;
use App\Models\Room;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Pricing\PricingEngine;
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
        $room = Room::withoutGlobalScopes()->findOrFail($data['room_id']);
        $checkIn = CarbonImmutable::parse($data['check_in']);
        $checkOut = CarbonImmutable::parse($data['check_out']);

        if (! $this->availability->isAvailable($room, $checkIn, $checkOut)) {
            throw new \RuntimeException('Selected dates are not available.');
        }

        return DB::transaction(function () use ($data, $room, $checkIn, $checkOut) {
            $quote = $this->pricing->quoteRange($room, $checkIn, $checkOut);

            $tenant = $room->tenant;
            $sstRate = $room->sst_applicable && $tenant->sst_registered ? (float) $tenant->sst_rate : 0;
            $sstAmount = round($quote['total'] * $sstRate, 2);

            $isForeigner = (bool) ($data['is_foreigner'] ?? false);
            $tourismTax = $isForeigner ? round((float) config('homestay.tourism_tax_per_night_foreigner', 10) * $quote['count'], 2) : 0;

            // Per-booking flat fee, snapshotted from the property so future
            // changes to the property's fee don't retroactively alter
            // existing bookings. NULL/0 → no fee, line omitted everywhere.
            $bookingFee = round((float) ($room->property->booking_fee_amount ?? 0), 2);

            $total = round($quote['total'] + $sstAmount + $tourismTax + $bookingFee, 2);

            // Deposit / pay-now logic:
            // - When the caller passes `deposit_pct` explicitly (host
            //   creating a manual booking in the dashboard), we compute
            //   a percentage-based deposit as before.
            // - When NOT passed (public booking flow), the property's
            //   flat booking fee IS the pay-now amount — much friendlier
            //   for Malaysian homestay guests than "deposit (20%)".
            if (array_key_exists('deposit_pct', $data) && $data['deposit_pct'] !== null) {
                $depositPct = (float) $data['deposit_pct'];
                $depositAmt = round($total * ($depositPct / 100), 2);
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
            $commissionAmt = $channel === Booking::CHANNEL_MARKETPLACE
                ? round($quote['total'] * (float) config('homestay.marketplace_commission_rate', 0.03), 2)
                : 0;

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
                'base_amount' => $quote['total'],
                'sst_amount' => $sstAmount,
                'tourism_tax_amount' => $tourismTax,
                'booking_fee_amount' => $bookingFee,
                'total_amount' => $total,
                'deposit_pct' => $depositPct,
                'deposit_amount' => $depositAmt,
                'is_foreigner' => $isForeigner,
                'commission_amount' => $commissionAmt,
                'special_requests' => $data['special_requests'] ?? null,
                'full_payment_reminder_at' => $checkIn->subDays((int) ($data['reminder_days'] ?? 7)),
                'meta' => ['quote_breakdown' => $quote['nights']],
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

            if ($channel === Booking::CHANNEL_MARKETPLACE) {
                Commission::create([
                    'tenant_id' => $room->tenant_id,
                    'booking_id' => $booking->id,
                    'gross_amount' => $quote['total'],
                    'commission_rate' => (float) config('homestay.marketplace_commission_rate', 0.03),
                    'commission_amount' => $commissionAmt,
                    'payout_amount' => round($quote['total'] - $commissionAmt, 2),
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
