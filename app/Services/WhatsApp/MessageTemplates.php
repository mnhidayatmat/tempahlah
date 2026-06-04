<?php

namespace App\Services\WhatsApp;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Render outbound message bodies in the tenant's locale (BM or EN).
 *
 * Kept inline (not Blade) because:
 *  - WhatsApp body is plain text, not HTML
 *  - bodies are short (under 1500 chars), easy to read at a glance
 *  - per-tenant customization will land later as overrideable strings
 */
class MessageTemplates
{
    /**
     * Booking confirmation, fires after deposit is paid.
     */
    public static function confirmation(Booking $booking, ?string $invoiceUrl = null, ?string $portalUrl = null): string
    {
        $locale = $booking->tenant?->default_locale ?? app()->getLocale();
        $name = $booking->guest?->name ?? '';
        $property = $booking->property?->name ?? '';
        $business = $booking->tenant?->business_name ?? config('app.name');
        $ci = Carbon::parse($booking->check_in)->translatedFormat('D, j M Y');
        $co = Carbon::parse($booking->check_out)->translatedFormat('D, j M Y');
        $nights = Carbon::parse($booking->check_in)->diffInDays(Carbon::parse($booking->check_out));
        $total = self::rm($booking->total_amount);

        if ($locale === 'ms') {
            $body = "Salam {$name}!\n\n"
                  . "Tempahan anda di *{$business}* telah disahkan ✓\n\n"
                  . "📍 {$property}\n"
                  . "📅 {$ci} → {$co} ({$nights} malam)\n"
                  . "💰 Jumlah: {$total}\n"
                  . "🔖 Rujukan: {$booking->reference}\n";
            if ($invoiceUrl) {
                $body .= "\n📄 Invois: {$invoiceUrl}\n";
            }
            if ($portalUrl) {
                $body .= "\n🔗 Lihat tempahan: {$portalUrl}\n";
            }
            $body .= "\nKami nantikan kedatangan anda. Sebarang pertanyaan, balas mesej ini.";
            return $body;
        }

        $body = "Hi {$name}!\n\n"
              . "Your booking at *{$business}* is confirmed ✓\n\n"
              . "📍 {$property}\n"
              . "📅 {$ci} → {$co} ({$nights} night" . ($nights > 1 ? 's' : '') . ")\n"
              . "💰 Total: {$total}\n"
              . "🔖 Reference: {$booking->reference}\n";
        if ($invoiceUrl) {
            $body .= "\n📄 Invoice: {$invoiceUrl}\n";
        }
        if ($portalUrl) {
            $body .= "\n🔗 View booking: {$portalUrl}\n";
        }
        $body .= "\nWe're looking forward to hosting you. Reply here with any questions.";
        return $body;
    }

    /**
     * Payment reminder, fires X days before balance due.
     */
    public static function reminder(Booking $booking, ?string $paymentUrl = null): string
    {
        $locale = $booking->tenant?->default_locale ?? app()->getLocale();
        $name = $booking->guest?->name ?? '';
        $business = $booking->tenant?->business_name ?? config('app.name');
        $balance = self::rm($booking->total_amount - ($booking->deposit_amount ?? 0));
        $ci = Carbon::parse($booking->check_in)->translatedFormat('D, j M Y');

        if ($locale === 'ms') {
            $body = "Salam {$name},\n\n"
                  . "Peringatan dari *{$business}*: baki bayaran *{$balance}* "
                  . "perlu diselesaikan sebelum daftar masuk pada {$ci}.\n";
            if ($paymentUrl) {
                $body .= "\n💳 Bayar di sini: {$paymentUrl}\n";
            }
            $body .= "\nTerima kasih!";
            return $body;
        }

        $body = "Hi {$name},\n\n"
              . "Reminder from *{$business}*: outstanding balance of *{$balance}* "
              . "is due before your check-in on {$ci}.\n";
        if ($paymentUrl) {
            $body .= "\n💳 Pay here: {$paymentUrl}\n";
        }
        $body .= "\nThanks!";
        return $body;
    }

    /**
     * Check-in instructions, fires N hours before check-in.
     */
    public static function checkin(Booking $booking): string
    {
        $locale = $booking->tenant?->default_locale ?? app()->getLocale();
        $name = $booking->guest?->name ?? '';
        $property = $booking->property?->name ?? '';
        $business = $booking->tenant?->business_name ?? config('app.name');
        $checkInTime = $booking->property?->check_in_time
            ? substr((string) $booking->property->check_in_time, 0, 5)
            : '15:00';
        $address = trim(implode(', ', array_filter([
            $booking->property?->address_line1,
            $booking->property?->address_line2,
            $booking->property?->city,
            $booking->property?->state,
        ])));

        if ($locale === 'ms') {
            return "Salam {$name}!\n\n"
                 . "Hari esok masa daftar masuk ke *{$property}* di {$business}.\n\n"
                 . "🕒 Masuk selepas: {$checkInTime}\n"
                 . "📍 Alamat: {$address}\n\n"
                 . "Sebarang masalah, balas mesej ini. Selamat datang!";
        }

        return "Hi {$name}!\n\n"
             . "Check-in to *{$property}* at {$business} is tomorrow.\n\n"
             . "🕒 Check-in from: {$checkInTime}\n"
             . "📍 Address: {$address}\n\n"
             . "Reply here with any issues. Welcome!";
    }

    /**
     * Invoice / pay-link message — sent right after a public booking is
     * created on the tenant subdomain. Carries the Toyyibpay deposit link.
     */
    public static function invoice(Booking $booking, string $payUrl): string
    {
        $locale = $booking->tenant?->default_locale ?? app()->getLocale();
        $lead = $booking->bookingGuests()->where('is_lead', true)->first();
        $name = $lead?->full_name ?? $booking->guest?->name ?? '';
        $property = $booking->property?->name ?? '';
        $business = $booking->tenant?->business_name ?? config('app.name');
        $ci = Carbon::parse($booking->check_in)->translatedFormat('D, j M Y');
        $co = Carbon::parse($booking->check_out)->translatedFormat('D, j M Y');
        $nights = (int) Carbon::parse($booking->check_in)->diffInDays(Carbon::parse($booking->check_out));
        $deposit = self::rm($booking->deposit_amount);
        $total = self::rm($booking->total_amount);
        $guests = (int) ($booking->adults ?? 1);

        if ($locale === 'ms') {
            return "Salam {$name}!\n\n"
                 . "Terima kasih kerana memilih *{$business}*. Tempahan anda sedang menunggu bayaran deposit:\n\n"
                 . "📍 {$property}\n"
                 . "📅 {$ci} → {$co} ({$nights} malam)\n"
                 . "👥 {$guests} tetamu\n"
                 . "💰 Deposit: {$deposit} daripada {$total}\n"
                 . "🔖 Rujukan: {$booking->reference}\n\n"
                 . "💳 Bayar deposit di sini:\n{$payUrl}\n\n"
                 . "Pautan ini sah selama 7 hari. Setelah deposit dibayar, anda akan menerima pengesahan dan resit secara automatik.";
        }

        return "Hi {$name}!\n\n"
             . "Thanks for choosing *{$business}*. Your booking is pending payment of the deposit:\n\n"
             . "📍 {$property}\n"
             . "📅 {$ci} → {$co} ({$nights} night" . ($nights > 1 ? 's' : '') . ")\n"
             . "👥 {$guests} guest" . ($guests > 1 ? 's' : '') . "\n"
             . "💰 Deposit: {$deposit} of {$total}\n"
             . "🔖 Reference: {$booking->reference}\n\n"
             . "💳 Pay deposit here:\n{$payUrl}\n\n"
             . "This link is valid for 7 days. Once paid, you'll automatically receive a confirmation and receipt.";
    }

    /**
     * Payment receipt message — sent after the Toyyibpay webhook flips a
     * deposit/full payment to succeeded. Carries the formal receipt number.
     */
    public static function receipt(Booking $booking, Invoice $receipt, Payment $payment): string
    {
        $locale = $booking->tenant?->default_locale ?? app()->getLocale();
        $lead = $booking->bookingGuests()->where('is_lead', true)->first();
        $name = $lead?->full_name ?? $booking->guest?->name ?? '';
        $property = $booking->property?->name ?? '';
        $business = $booking->tenant?->business_name ?? config('app.name');
        $ci = Carbon::parse($booking->check_in)->translatedFormat('D, j M Y');
        $co = Carbon::parse($booking->check_out)->translatedFormat('D, j M Y');
        $nights = (int) Carbon::parse($booking->check_in)->diffInDays(Carbon::parse($booking->check_out));
        $paid = self::rm($payment->amount);
        $checkInTime = $booking->property?->check_in_time
            ? substr((string) $booking->property->check_in_time, 0, 5)
            : '15:00';

        if ($locale === 'ms') {
            return "Salam {$name}!\n\n"
                 . "Bayaran diterima ✓ Terima kasih!\n\n"
                 . "🧾 Resit: {$receipt->invoice_number}\n"
                 . "📍 {$property}\n"
                 . "📅 {$ci} → {$co} ({$nights} malam)\n"
                 . "💳 Dibayar: {$paid} melalui Toyyibpay\n"
                 . "🔖 Tempahan: {$booking->reference}\n\n"
                 . "Resit PDF juga dihantar ke emel anda. Jumpa lagi pada {$ci} (daftar masuk selepas {$checkInTime}). Selamat datang ke *{$business}*!";
        }

        return "Hi {$name}!\n\n"
             . "Payment received ✓ Thank you!\n\n"
             . "🧾 Receipt: {$receipt->invoice_number}\n"
             . "📍 {$property}\n"
             . "📅 {$ci} → {$co} ({$nights} night" . ($nights > 1 ? 's' : '') . ")\n"
             . "💳 Paid: {$paid} via Toyyibpay\n"
             . "🔖 Booking: {$booking->reference}\n\n"
             . "The receipt PDF has also been emailed to you. See you on {$ci} (check-in from {$checkInTime}). Welcome to *{$business}*!";
    }

    /**
     * Cancellation notice — sent when a booking is cancelled (most often by
     * the auto-cancel lifecycle when the booking fee or balance went unpaid).
     */
    public static function cancellation(Booking $booking, ?string $reason = null): string
    {
        $locale = $booking->tenant?->default_locale ?? app()->getLocale();
        $lead = $booking->bookingGuests()->where('is_lead', true)->first();
        $name = $lead?->full_name ?? $booking->guest?->name ?? '';
        $property = $booking->property?->name ?? '';
        $business = $booking->tenant?->business_name ?? config('app.name');
        $ci = Carbon::parse($booking->check_in)->translatedFormat('D, j M Y');

        if ($locale === 'ms') {
            $body = "Salam {$name},\n\n"
                  . "Tempahan anda di *{$business}* telah dibatalkan.\n\n"
                  . "📍 {$property}\n"
                  . "📅 {$ci}\n"
                  . "🔖 Rujukan: {$booking->reference}\n";
            if ($reason) {
                $body .= "\nSebab: {$reason}\n";
            }
            $body .= "\nJika anda masih berminat untuk menginap, sila buat tempahan baharu atau hubungi kami. Terima kasih.";
            return $body;
        }

        $body = "Hi {$name},\n\n"
              . "Your booking at *{$business}* has been cancelled.\n\n"
              . "📍 {$property}\n"
              . "📅 {$ci}\n"
              . "🔖 Reference: {$booking->reference}\n";
        if ($reason) {
            $body .= "\nReason: {$reason}\n";
        }
        $body .= "\nIf you'd still like to stay, please make a new booking or get in touch. Thank you.";
        return $body;
    }

    public static function test(?string $name = null): string
    {
        $locale = app()->getLocale();
        $nm = $name ?? 'there';
        return $locale === 'ms'
            ? "Salam {$nm}! Ini mesej ujian dari Tempahlah. Sambungan WhatsApp anda berfungsi. ✓"
            : "Hi {$nm}! This is a test message from Tempahlah. Your WhatsApp connection is working. ✓";
    }

    protected static function rm(float|int|null $amount): string
    {
        return 'RM '.number_format((float) ($amount ?? 0), 2);
    }
}
