<?php

namespace App\Services\Channels;

use Illuminate\Support\Carbon;

/**
 * Minimal, dependency-free RFC 5545 reader for the subset Airbnb and
 * Booking.com actually publish: a VCALENDAR of all-day VEVENTs with UID,
 * DTSTART, DTEND and SUMMARY. See IcsWriter for why we don't use a library.
 *
 * Returns normalised events with dates as Y-m-d strings and a half-open
 * [start, end) range (DTEND exclusive), matching CalendarBlock semantics.
 */
class IcsParser
{
    /**
     * @return array<int, array{uid:string, start:string, end:string, summary:string}>
     */
    public function parse(string $ics): array
    {
        $lines = $this->unfold($ics);

        $events = [];
        $current = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === 'BEGIN:VEVENT') {
                $current = ['uid' => null, 'start' => null, 'end' => null, 'summary' => ''];
                continue;
            }

            if ($trimmed === 'END:VEVENT') {
                if ($current !== null) {
                    $normalised = $this->normalise($current);
                    if ($normalised !== null) {
                        $events[] = $normalised;
                    }
                }
                $current = null;
                continue;
            }

            if ($current === null) {
                continue;
            }

            [$name, $params, $value] = $this->splitLine($line);

            switch ($name) {
                case 'UID':
                    $current['uid'] = $value;
                    break;
                case 'DTSTART':
                    $current['start'] = $this->parseDate($value, $params);
                    break;
                case 'DTEND':
                    $current['end'] = $this->parseDate($value, $params);
                    break;
                case 'SUMMARY':
                    $current['summary'] = $this->unescapeText($value);
                    break;
            }
        }

        return $events;
    }

    /**
     * Turn a parsed VEVENT into a normalised event, or null if it's unusable
     * (no start date). A missing DTEND means a single night; a missing UID is
     * synthesised from the date range so re-syncs still dedupe deterministically.
     */
    private function normalise(array $event): ?array
    {
        if (empty($event['start'])) {
            return null;
        }

        $start = $event['start'];
        $end = $event['end'] ?: Carbon::parse($start)->addDay()->toDateString();

        // Guard against inverted/zero-length ranges from odd feeds.
        if (Carbon::parse($end)->lte(Carbon::parse($start))) {
            $end = Carbon::parse($start)->addDay()->toDateString();
        }

        $uid = trim((string) $event['uid']) !== ''
            ? trim((string) $event['uid'])
            : 'nouid-'.md5($start.'|'.$end);

        return [
            'uid'     => $uid,
            'start'   => $start,
            'end'     => $end,
            'summary' => (string) $event['summary'],
        ];
    }

    /**
     * Unfold RFC 5545 folded lines: a line beginning with a space or tab is a
     * continuation of the previous one. Handles CRLF, CR and LF endings.
     *
     * @return array<int, string>
     */
    private function unfold(string $ics): array
    {
        $raw = preg_split('/\r\n|\r|\n/', $ics) ?: [];
        $out = [];

        foreach ($raw as $line) {
            if ($line === '') {
                continue;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && ! empty($out)) {
                $out[count($out) - 1] .= substr($line, 1);
            } else {
                $out[] = $line;
            }
        }

        return $out;
    }

    /**
     * Split "NAME;PARAM=x:VALUE" into [name, params[], value].
     *
     * @return array{0:string, 1:array<string,string>, 2:string}
     */
    private function splitLine(string $line): array
    {
        $colon = strpos($line, ':');
        if ($colon === false) {
            return ['', [], ''];
        }

        $head = substr($line, 0, $colon);
        $value = substr($line, $colon + 1);

        $parts = explode(';', $head);
        $name = strtoupper(array_shift($parts));

        $params = [];
        foreach ($parts as $p) {
            if (str_contains($p, '=')) {
                [$k, $v] = explode('=', $p, 2);
                $params[strtoupper($k)] = $v;
            }
        }

        return [$name, $params, $value];
    }

    /**
     * Parse a DATE or DATE-TIME value into a Y-m-d string. Airbnb/Booking use
     * VALUE=DATE (YYYYMMDD); we also tolerate DATE-TIME (YYYYMMDDTHHMMSS[Z]).
     */
    private function parseDate(string $value, array $params): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Pure date: 20260713
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
            return $this->safeDate($m[1], $m[2], $m[3]);
        }

        // Date-time: 20260713T140000 or 20260713T140000Z (take the date part —
        // OTA availability is day-granular, so the calendar day is what matters).
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T\d{6}Z?$/', $value, $m)) {
            return $this->safeDate($m[1], $m[2], $m[3]);
        }

        return null;
    }

    private function safeDate(string $y, string $mo, string $d): ?string
    {
        if (! checkdate((int) $mo, (int) $d, (int) $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /** Reverse the RFC 5545 TEXT escaping. */
    private function unescapeText(string $value): string
    {
        return str_replace(
            ['\\n', '\\N', '\\,', '\\;', '\\\\'],
            ["\n", "\n", ',', ';', '\\'],
            $value
        );
    }
}
