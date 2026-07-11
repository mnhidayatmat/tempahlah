<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\Channels\ChannelCalendarSync;
use Illuminate\Http\Response;

/**
 * Public, unauthenticated iCal busy-feed for one room, addressed by an
 * unguessable rotatable token. This is the URL a host pastes into Airbnb and
 * Booking.com so those platforms import the room's booked/blocked dates.
 *
 * Machine-consumed by OTA crawlers — no session, no auth, no PII in the body
 * (only generic "Booked"/"Blocked" summaries).
 */
class ChannelFeedController extends Controller
{
    public function show(string $token, ChannelCalendarSync $sync): Response
    {
        // Public token lookup — bypass the tenant global scope (no tenant
        // context on a crawler request) and resolve the room directly.
        $room = Room::withoutGlobalScopes()
            ->where('ical_export_token', $token)
            ->first();

        abort_if($room === null, 404);

        $ics = $sync->exportFeed($room);

        return response($ics, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="tempahlah-'.$room->public_id.'.ics"',
            'Cache-Control'       => 'no-cache, max-age=0',
        ]);
    }
}
