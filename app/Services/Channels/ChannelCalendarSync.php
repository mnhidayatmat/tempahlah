<?php

namespace App\Services\Channels;

use App\Models\Booking;
use App\Models\CalendarBlock;
use App\Models\ChannelIntegration;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Two-way iCal sync between a Tempahlah room and OTA calendars
 * (Airbnb / Booking.com).
 *
 *  - Export: build a public busy-feed of the room's bookings + all blocks so
 *    the OTAs can import it and block those dates on their side.
 *  - Import: fetch an OTA's iCal URL, parse its reservations, and reconcile
 *    them into CalendarBlock rows so they block availability here — preventing
 *    double-bookings. Cancelled OTA reservations (vanished events) are removed.
 *
 * All date logic is in Malaysian local calendar days (bookings/blocks are
 * stored as plain dates representing KL days; the app itself runs in UTC).
 */
class ChannelCalendarSync
{
    private const TZ = 'Asia/Kuala_Lumpur';

    /** Cap on a fetched feed body so a hostile/broken URL can't exhaust memory. */
    private const MAX_FEED_BYTES = 2_000_000;

    public function __construct(
        private readonly IcsWriter $writer,
        private readonly IcsParser $parser,
    ) {}

    /**
     * Build the public export ICS for a room: every future-relevant booking
     * and calendar block, as all-day busy events. Imported OTA blocks reuse
     * their original UID so the originating OTA recognises its own reservation
     * (idempotent) while the other OTA sees it as a foreign block to honour.
     */
    public function exportFeed(Room $room): string
    {
        $today = Carbon::now(self::TZ)->toDateString();

        $bookings = Booking::query()
            ->withoutGlobalScopes()
            ->where('room_id', $room->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->where('check_out', '>=', $today)
            ->get(['id', 'public_id', 'channel', 'check_in', 'check_out']);

        $blocks = CalendarBlock::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($room) {
                $q->where('room_id', $room->id)
                    ->orWhere(fn ($q2) => $q2->whereNull('room_id')->where('property_id', $room->property_id));
            })
            ->where('ends_on', '>=', $today)
            ->get(['id', 'source', 'source_uid', 'starts_on', 'ends_on']);

        $events = [];

        foreach ($bookings as $b) {
            $events[] = [
                'uid'     => 'booking-'.$b->public_id.'@tempahlah.com',
                'start'   => $b->check_in,
                'end'     => $b->check_out,
                'summary' => $this->bookingSummary($b->channel),
            ];
        }

        foreach ($blocks as $block) {
            // Reuse the origin OTA's own UID so re-importing our feed is a no-op
            // for it; manual/local blocks get a stable Tempahlah UID.
            $uid = ! empty($block->source_uid)
                ? $block->source_uid
                : 'block-'.$block->id.'@tempahlah.com';

            $events[] = [
                'uid'     => $uid,
                'start'   => $block->starts_on,
                'end'     => $block->ends_on,
                'summary' => $this->blockSummary($block->source),
            ];
        }

        $name = trim(($room->name ?: 'Room').' — Tempahlah');

        return $this->writer->calendar($name, $events);
    }

    /**
     * Fetch + reconcile one import link. Returns a result array:
     * ['ok' => bool, 'count' => int, 'message' => string]. Never throws —
     * failures are captured onto the link so the UI can surface them.
     */
    public function importLink(ChannelIntegration $link): array
    {
        $url = trim((string) $link->ical_import_url);
        $room = $link->room;

        if ($url === '' || ! $room) {
            return $this->fail($link, __('No calendar URL set.'));
        }

        try {
            $response = Http::timeout(20)
                ->withOptions(['stream' => false])
                ->withHeaders(['Accept' => 'text/calendar, text/plain, */*'])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('channel iCal fetch failed', ['link' => $link->id, 'error' => $e->getMessage()]);

            return $this->fail($link, __('Could not reach the calendar URL.'));
        }

        if (! $response->successful()) {
            return $this->fail($link, __('Calendar URL returned :status.', ['status' => $response->status()]));
        }

        $body = (string) $response->body();

        if (strlen($body) > self::MAX_FEED_BYTES) {
            return $this->fail($link, __('Calendar feed is too large.'));
        }

        // A valid feed must actually be an iCalendar document. If it isn't
        // (an HTML error page, an empty transient response), bail WITHOUT
        // reconciling — otherwise a glitch would wipe every imported block.
        if (! str_contains($body, 'BEGIN:VCALENDAR')) {
            return $this->fail($link, __('That URL did not return a calendar. Check you copied the iCal export link.'));
        }

        $events = $this->parser->parse($body);
        $channel = $link->channel; // 'airbnb' | 'booking' — matches CalendarBlock::SOURCE_*

        $seenUids = [];

        foreach ($events as $event) {
            $seenUids[] = $event['uid'];

            CalendarBlock::withoutGlobalScopes()->updateOrCreate(
                ['source' => $channel, 'source_uid' => $event['uid']],
                [
                    'tenant_id'   => $room->tenant_id,
                    'property_id' => $room->property_id,
                    'room_id'     => $room->id,
                    'starts_on'   => $event['start'],
                    'ends_on'     => $event['end'],
                    'reason'      => CalendarBlock::REASON_BOOKING,
                    'notes'       => $this->importNote($channel, $event['summary']),
                ]
            );
        }

        // Remove blocks that vanished from the feed (OTA cancellations). Scoped
        // strictly to THIS room + THIS channel so manual/Google/other-OTA blocks
        // are never touched.
        $stale = CalendarBlock::withoutGlobalScopes()
            ->where('room_id', $room->id)
            ->where('source', $channel);

        if (! empty($seenUids)) {
            $stale->whereNotIn('source_uid', $seenUids);
        }

        $removed = $stale->delete();

        $count = count($seenUids);

        $meta = $link->credentials_encrypted ?? [];
        $meta['last_event_count'] = $count;

        $link->forceFill([
            'last_synced_at'        => now(),
            'last_sync_status'      => 'ok',
            'last_sync_error'       => null,
            'credentials_encrypted' => $meta,
        ])->save();

        return [
            'ok'      => true,
            'count'   => $count,
            'removed' => $removed,
            'message' => __(':count reservation(s) synced.', ['count' => $count]),
        ];
    }

    private function fail(ChannelIntegration $link, string $message): array
    {
        $link->forceFill([
            'last_synced_at'   => now(),
            'last_sync_status' => 'error',
            'last_sync_error'  => $message,
        ])->save();

        return ['ok' => false, 'count' => 0, 'removed' => 0, 'message' => $message];
    }

    private function bookingSummary(?string $channel): string
    {
        return match ($channel) {
            Booking::CHANNEL_AIRBNB  => 'Booked (Airbnb)',
            Booking::CHANNEL_BOOKING => 'Booked (Booking.com)',
            default                  => 'Booked (Tempahlah)',
        };
    }

    private function blockSummary(?string $source): string
    {
        return match ($source) {
            CalendarBlock::SOURCE_AIRBNB  => 'Booked (Airbnb)',
            CalendarBlock::SOURCE_BOOKING => 'Booked (Booking.com)',
            CalendarBlock::SOURCE_GOOGLE  => 'Busy (Google Calendar)',
            default                       => 'Blocked (Tempahlah)',
        };
    }

    private function importNote(string $channel, string $summary): string
    {
        $label = $channel === ChannelIntegration::CHANNEL_AIRBNB ? 'Airbnb' : 'Booking.com';
        $summary = trim($summary);

        return $summary !== '' ? "{$label}: {$summary}" : "{$label} reservation";
    }
}
