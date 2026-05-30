<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsappMessage;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;

/**
 * Public entry point for queueing outbound WhatsApp messages.
 *
 *   WhatsappMessenger::dispatchManual($tenant, $booking, 'Hi, just checking in');
 *   WhatsappMessenger::dispatchConfirmation($booking, $invoiceUrl);
 *   WhatsappMessenger::dispatchReminder($booking, $payUrl);
 *   WhatsappMessenger::dispatchCheckin($booking);
 *
 * Each method:
 *   1. No-ops if the tenant has no connected session, or has the relevant
 *      auto-send pref turned off (manual is always allowed).
 *   2. Resolves the recipient phone from the booking guest.
 *   3. Renders the body via MessageTemplates.
 *   4. Creates a queued WhatsappMessage row.
 *   5. Dispatches SendWhatsappMessage onto the queue.
 *
 * Returns the WhatsappMessage row, or null when skipped.
 */
class WhatsappMessenger
{
    public static function dispatchManual(
        Tenant $tenant,
        ?Booking $booking,
        string $recipientPhone,
        string $body,
        ?array $media = null,
    ): ?WhatsappMessage {
        $session = self::sessionFor($tenant);
        if (! $session?->isConnected()) return null;

        return self::queue($tenant, $booking, $recipientPhone, $body, WhatsappMessage::KIND_MANUAL, $media);
    }

    public static function dispatchConfirmation(Booking $booking, ?string $invoiceUrl = null): ?WhatsappMessage
    {
        return self::autoDispatch(
            $booking,
            'auto_confirmation',
            WhatsappMessage::KIND_CONFIRMATION,
            fn () => MessageTemplates::confirmation($booking, $invoiceUrl),
        );
    }

    public static function dispatchReminder(Booking $booking, ?string $paymentUrl = null): ?WhatsappMessage
    {
        return self::autoDispatch(
            $booking,
            'auto_reminder',
            WhatsappMessage::KIND_REMINDER,
            fn () => MessageTemplates::reminder($booking, $paymentUrl),
        );
    }

    public static function dispatchCheckin(Booking $booking): ?WhatsappMessage
    {
        return self::autoDispatch(
            $booking,
            'auto_checkin',
            WhatsappMessage::KIND_CHECKIN,
            fn () => MessageTemplates::checkin($booking),
        );
    }

    public static function dispatchTest(Tenant $tenant, string $recipientPhone, ?string $name = null): ?WhatsappMessage
    {
        $session = self::sessionFor($tenant);
        if (! $session?->isConnected()) return null;

        return self::queue(
            $tenant,
            null,
            $recipientPhone,
            MessageTemplates::test($name),
            WhatsappMessage::KIND_TEST,
        );
    }

    protected static function autoDispatch(
        Booking $booking,
        string $prefKey,
        string $kind,
        \Closure $bodyFactory,
    ): ?WhatsappMessage {
        $tenant = $booking->tenant;
        if (! $tenant) return null;

        $session = self::sessionFor($tenant);
        if (! $session?->isConnected()) return null;
        if (! $session->pref($prefKey)) return null;

        $phone = self::resolveGuestPhone($booking);
        if (! $phone) return null;

        if (self::guestOptedOut($booking->guest, $tenant)) return null;

        return self::queue($tenant, $booking, $phone, $bodyFactory(), $kind);
    }

    protected static function queue(
        Tenant $tenant,
        ?Booking $booking,
        string $recipientPhone,
        string $body,
        string $kind,
        ?array $media = null,
    ): WhatsappMessage {
        $msg = WhatsappMessage::create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking?->id,
            'user_id' => $booking?->guest_id ?? null,
            'direction' => WhatsappMessage::DIRECTION_OUT,
            'kind' => $kind,
            'recipient_phone' => $recipientPhone,
            'recipient_name' => $booking?->guest?->name,
            'body' => $body,
            'media_url' => $media['url'] ?? null,
            'media_kind' => $media['kind'] ?? null,
            'status' => WhatsappMessage::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        SendWhatsappMessage::dispatch($msg->id)
            ->onQueue('default');

        return $msg;
    }

    protected static function sessionFor(Tenant $tenant): ?WhatsappSession
    {
        return WhatsappSession::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->first();
    }

    protected static function resolveGuestPhone(Booking $booking): ?string
    {
        // Prefer the BookingGuest row (denormalized at booking time), fall
        // back to the User row (in case the guest signed up later).
        $bg = $booking->guestRecord ?? $booking->bookingGuest ?? null;
        $candidates = array_filter([
            $bg?->phone ?? null,
            $booking->guest?->phone,
        ]);
        foreach ($candidates as $candidate) {
            $normalized = PhoneNumber::normalize($candidate);
            if ($normalized) return $normalized;
        }
        return null;
    }

    protected static function guestOptedOut(?User $guest, Tenant $tenant): bool
    {
        if (! $guest) return false;
        $list = (array) data_get($guest->meta, 'wa_opted_out_for_tenants', []);
        return in_array($tenant->id, $list, true);
    }
}
