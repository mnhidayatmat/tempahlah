<?php

namespace App\Services\Channels;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Minimal, dependency-free RFC 5545 (iCalendar) writer.
 *
 * We hand-roll this rather than pull in sabre/vobject because production
 * auto-deploys with `git reset --hard + migrate` and never runs
 * `composer install`, so a new package would fatal on deploy. The subset we
 * emit — a VCALENDAR of all-day VEVENTs — is exactly what Airbnb and
 * Booking.com publish and consume, so a small correct writer is enough.
 *
 * Dates are emitted as all-day events (VALUE=DATE). Per RFC 5545 an all-day
 * DTEND is the *exclusive* non-inclusive end, which lines up perfectly with
 * our half-open [check_in, check_out) booking convention — check-out day is
 * free for the next guest, exactly as the OTAs expect.
 */
class IcsWriter
{
    /**
     * Build a full VCALENDAR document.
     *
     * @param  string  $calendarName  Shown as the feed name in some clients.
     * @param  array<int, array{uid:string, start:CarbonInterface|string, end:CarbonInterface|string, summary:string}>  $events
     */
    public function calendar(string $calendarName, array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Tempahlah//Channel Sync//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeText($calendarName),
        ];

        $stamp = Carbon::now('UTC')->format('Ymd\THis\Z');

        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$this->escapeText((string) $event['uid']);
            $lines[] = 'DTSTAMP:'.$stamp;
            $lines[] = 'DTSTART;VALUE=DATE:'.$this->formatDate($event['start']);
            $lines[] = 'DTEND;VALUE=DATE:'.$this->formatDate($event['end']);
            $lines[] = 'SUMMARY:'.$this->escapeText((string) ($event['summary'] ?? 'Reserved'));
            $lines[] = 'TRANSP:OPAQUE';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // Fold every content line to 75 octets and join with CRLF per RFC 5545.
        return implode("\r\n", array_map([$this, 'fold'], $lines))."\r\n";
    }

    /** YYYYMMDD for an all-day DATE value. */
    private function formatDate(CarbonInterface|string $date): string
    {
        return ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->format('Ymd');
    }

    /** Escape TEXT values: backslash, semicolon, comma, and newlines. */
    private function escapeText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);

        return $value;
    }

    /**
     * Fold a content line so no line exceeds 75 octets. Continuation lines
     * begin with a single space. Folding is octet-based (RFC 5545 §3.1); our
     * content is ASCII in practice, so a byte walk is safe.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = '';
        $chunk = 75;
        $out .= substr($line, 0, $chunk);
        $rest = substr($line, $chunk);

        while (strlen($rest) > 0) {
            // 74 chars + the leading space = 75 octets on continuation lines.
            $out .= "\r\n ".substr($rest, 0, 74);
            $rest = substr($rest, 74);
        }

        return $out;
    }
}
