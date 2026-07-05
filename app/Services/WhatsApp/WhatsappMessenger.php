<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsappMessage;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        // Signed magic-link to the guest portal — the customer taps it from
        // the WhatsApp message to view their booking again without a password.
        $portalUrl = $booking->guestPortalUrl();

        return self::autoDispatch(
            $booking,
            'auto_confirmation',
            WhatsappMessage::KIND_CONFIRMATION,
            fn () => MessageTemplates::confirmation($booking, $invoiceUrl, $portalUrl),
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

    /**
     * Pre-checkout reminder — fires N hours before checkout (per-tenant lead
     * time) carrying the host's checkout guidelines (clean up, take out the
     * rubbish, lock up, etc.). Gated by the `auto_checkout` session pref.
     */
    public static function dispatchCheckoutReminder(Booking $booking): ?WhatsappMessage
    {
        return self::autoDispatch(
            $booking,
            'auto_checkout',
            WhatsappMessage::KIND_CHECKOUT,
            fn () => MessageTemplates::checkout($booking),
        );
    }

    /**
     * Pay-link invoice — sent right after a public booking is created on
     * the tenant subdomain. Gated by the `auto_invoice` session pref
     * (defaults to true since this is core booking-flow comms, not
     * marketing). Attaches the invoice PDF as a WhatsApp document.
     */
    public static function dispatchInvoice(Booking $booking, string $payUrl, ?Invoice $invoice = null, bool $manual = false): ?WhatsappMessage
    {
        return self::autoDispatch(
            $booking,
            'auto_invoice',
            WhatsappMessage::KIND_INVOICE,
            fn () => MessageTemplates::invoice($booking, $payUrl, $manual),
            $invoice ? self::pdfMedia($invoice) : null,
        );
    }

    /**
     * Payment receipt — sent after the Toyyibpay webhook confirms a
     * deposit/full payment. Carries the formal receipt number AND the
     * receipt PDF as a WhatsApp document.
     */
    public static function dispatchReceipt(Booking $booking, Invoice $receipt, Payment $payment): ?WhatsappMessage
    {
        return self::autoDispatch(
            $booking,
            'auto_receipt',
            WhatsappMessage::KIND_RECEIPT,
            fn () => MessageTemplates::receipt($booking, $receipt, $payment),
            self::pdfMedia($receipt),
        );
    }

    /**
     * Manual invoice send — the host explicitly clicked "WhatsApp" on the
     * booking's invoice. Bypasses the `auto_invoice` auto-send preference (the
     * host is asking for it) but still honours the connected-session, resolvable
     * -phone and guest-opt-out guards.
     */
    public static function sendInvoiceManual(Booking $booking, string $payUrl, Invoice $invoice): ?WhatsappMessage
    {
        // No pay link (gateway off / fully paid) → use the manual template,
        // which carries the tenant's bank details instead of an empty link.
        $manual = trim($payUrl) === '';

        return self::manualDispatch(
            $booking,
            WhatsappMessage::KIND_INVOICE,
            fn () => MessageTemplates::invoice($booking, $payUrl, $manual),
            self::pdfMedia($invoice),
        );
    }

    /**
     * Manual receipt send — host explicitly shares the receipt (e.g. a fully-
     * paid booking). Bypasses the `auto_receipt` pref but keeps the same guards.
     */
    public static function sendReceiptManual(Booking $booking, Invoice $receipt, Payment $payment): ?WhatsappMessage
    {
        return self::manualDispatch(
            $booking,
            WhatsappMessage::KIND_RECEIPT,
            fn () => MessageTemplates::receipt($booking, $receipt, $payment),
            self::pdfMedia($receipt),
        );
    }

    /**
     * Cancellation notice — sent when a booking is cancelled (auto-cancel for
     * unpaid fee/balance, or a host-initiated cancel). Gated by the
     * `auto_cancellation` session pref (default true).
     */
    public static function dispatchCancellation(Booking $booking, ?string $reason = null): ?WhatsappMessage
    {
        return self::autoDispatch(
            $booking,
            'auto_cancellation',
            WhatsappMessage::KIND_CANCELLATION,
            fn () => MessageTemplates::cancellation($booking, $reason),
        );
    }

    /**
     * Build the `['url' => ..., 'kind' => 'pdf', 'filename' => ...]` media
     * payload for an Invoice or Receipt. Uses a 7-day temporary signed URL
     * from the configured filesystem so PDFs stay private even though the
     * sidecar needs to download them.
     *
     * Returns null on any failure — the dispatch then sends the WA text
     * body without an attachment rather than throwing.
     */
    protected static function pdfMedia(Invoice $invoice): ?array
    {
        if (! $invoice->pdf_path) return null;

        try {
            $disk = Storage::disk(config('filesystems.default'));
            if (! $disk->exists($invoice->pdf_path)) return null;

            // S3 / DO Spaces support temporaryUrl natively. Local-driver
            // dev environments throw — fall through to no-media.
            $url = $disk->temporaryUrl($invoice->pdf_path, now()->addDays(7));
            return [
                'url'      => $url,
                'kind'     => 'pdf',
                'filename' => $invoice->invoice_number.'.pdf',
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp PDF media: failed to build signed URL', [
                'invoice_id' => $invoice->id,
                'pdf_path'   => $invoice->pdf_path,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Outbound agent reply — used by the AI agent feature. Bypasses the
     * BookingGuest requirement (see RecipientGuard agent_reply context).
     */
    public static function dispatchAgentReply(
        Tenant $tenant,
        string $recipientPhone,
        string $body,
        ?array $media = null,
    ): ?WhatsappMessage {
        $session = self::sessionFor($tenant);
        if (! $session?->isConnected()) return null;

        return self::queue($tenant, null, $recipientPhone, $body, WhatsappMessage::KIND_AGENT_REPLY, $media);
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

    /**
     * Like autoDispatch() but without the auto-send preference gate — for
     * host-initiated (manual) sends. Still requires a connected session, a
     * resolvable guest phone, and respects the guest's opt-out.
     */
    protected static function manualDispatch(
        Booking $booking,
        string $kind,
        \Closure $bodyFactory,
        ?array $media = null,
    ): ?WhatsappMessage {
        $tenant = $booking->tenant;
        if (! $tenant) return null;

        $session = self::sessionFor($tenant);
        if (! $session?->isConnected()) return null;

        $phone = self::resolveGuestPhone($booking);
        if (! $phone) return null;

        if (self::guestOptedOut($booking->guest, $tenant)) return null;

        return self::queue($tenant, $booking, $phone, $bodyFactory(), $kind, $media);
    }

    protected static function autoDispatch(
        Booking $booking,
        string $prefKey,
        string $kind,
        \Closure $bodyFactory,
        ?array $media = null,
    ): ?WhatsappMessage {
        $tenant = $booking->tenant;
        if (! $tenant) {
            Log::info('WhatsApp dispatch skipped: no tenant on booking', [
                'booking_id' => $booking->id, 'kind' => $kind,
            ]);
            return null;
        }

        $session = self::sessionFor($tenant);
        if (! $session) {
            Log::info('WhatsApp dispatch skipped: no session configured', [
                'tenant_id' => $tenant->id, 'kind' => $kind,
            ]);
            return null;
        }
        if (! $session->isConnected()) {
            // Most common cause of silent WA failures. Logged at info-level
            // so it shows up under "why didn't my customer get a WA?".
            Log::info('WhatsApp dispatch skipped: session not connected', [
                'tenant_id'   => $tenant->id,
                'kind'        => $kind,
                'status'      => $session->status,
                'last_error'  => $session->last_error,
            ]);
            return null;
        }
        if (! $session->pref($prefKey)) {
            Log::info('WhatsApp dispatch skipped: auto-send pref off', [
                'tenant_id' => $tenant->id, 'pref' => $prefKey,
            ]);
            return null;
        }

        $phone = self::resolveGuestPhone($booking);
        if (! $phone) {
            Log::info('WhatsApp dispatch skipped: no resolvable guest phone', [
                'booking_id' => $booking->id, 'kind' => $kind,
            ]);
            return null;
        }

        if (self::guestOptedOut($booking->guest, $tenant)) {
            Log::info('WhatsApp dispatch skipped: guest opted out', [
                'tenant_id' => $tenant->id, 'guest_id' => $booking->guest_id,
            ]);
            return null;
        }

        return self::queue($tenant, $booking, $phone, $bodyFactory(), $kind, $media);
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
