<?php

namespace App\Services\WhatsApp;

use App\Models\Booking;
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
    public static function confirmation(Booking $booking, ?string $invoiceUrl = null): string
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
