<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CalendarBlock;
use App\Models\ChannelIntegration;
use App\Models\Property;
use App\Models\Room;
use App\Services\Channels\ChannelCalendarSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Pennant\Feature;

/**
 * Two-way calendar sync with Airbnb + Booking.com over iCal — a Pro feature.
 *
 * Per room the host gets:
 *  - one public export feed URL to paste into both OTAs (so they block the
 *    room's booked dates), and
 *  - two import URL slots (Airbnb, Booking.com) that we poll to pull their
 *    reservations back in as availability blocks.
 */
class ChannelSyncController extends Controller
{
    /** The OTA channels we sync. Keys match ChannelIntegration::CHANNEL_* + CalendarBlock::SOURCE_*. */
    private const CHANNELS = [
        ChannelIntegration::CHANNEL_AIRBNB  => 'Airbnb',
        ChannelIntegration::CHANNEL_BOOKING => 'Booking.com',
    ];

    /** Redirect when the tenant isn't on a plan that includes channel sync. */
    private function blocked(): ?RedirectResponse
    {
        if (Feature::active('ical_channel_sync')) {
            return null;
        }

        return redirect()
            ->route('tenant.integrations.index')
            ->with('error', __('Airbnb & Booking.com calendar sync is a Pro feature. Upgrade to keep your channels in sync automatically.'));
    }

    public function index()
    {
        if ($redirect = $this->blocked()) {
            return $redirect;
        }

        $properties = Property::query()
            ->with(['rooms' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        // Ensure every room has an export token now (one-time), so the view is a
        // pure read — no lazy DB writes while rendering the feed URLs.
        $properties->each(fn ($p) => $p->rooms->each->icalExportToken());

        // Existing import links keyed by "room_id:channel" for quick lookup.
        $links = ChannelIntegration::query()
            ->where('mode', ChannelIntegration::MODE_ICAL)
            ->whereIn('channel', array_keys(self::CHANNELS))
            ->get()
            ->keyBy(fn ($l) => $l->room_id.':'.$l->channel);

        return view('tenant.integrations.channel_sync', [
            'properties' => $properties,
            'links'      => $links,
            'channels'   => self::CHANNELS,
        ]);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        if ($redirect = $this->blocked()) {
            return $redirect;
        }

        $validated = $request->validate([
            'airbnb_url'  => ['nullable', 'url', 'max:2000', 'starts_with:https://,http://'],
            'booking_url' => ['nullable', 'url', 'max:2000', 'starts_with:https://,http://'],
        ], [
            'airbnb_url.url'   => __('Enter a valid Airbnb calendar URL.'),
            'booking_url.url'  => __('Enter a valid Booking.com calendar URL.'),
        ]);

        $map = [
            ChannelIntegration::CHANNEL_AIRBNB  => $validated['airbnb_url'] ?? null,
            ChannelIntegration::CHANNEL_BOOKING => $validated['booking_url'] ?? null,
        ];

        foreach ($map as $channel => $url) {
            $url = $url ? trim($url) : null;

            if ($url) {
                ChannelIntegration::updateOrCreate(
                    ['room_id' => $room->id, 'channel' => $channel, 'property_id' => $room->property_id],
                    [
                        'mode'            => ChannelIntegration::MODE_ICAL,
                        'two_way'         => true,
                        'ical_import_url' => $url,
                        'ical_export_url' => $room->icalExportUrl(),
                        'active'          => true,
                    ]
                );
            } else {
                // Link cleared: deactivate and drop the imported holds so the
                // dates free up. Never touches manual/Google/other-OTA blocks.
                $existing = ChannelIntegration::where('room_id', $room->id)->where('channel', $channel)->first();
                if ($existing) {
                    $existing->update(['ical_import_url' => null, 'active' => false, 'last_sync_status' => null, 'last_sync_error' => null]);
                    CalendarBlock::where('room_id', $room->id)->where('source', $channel)->delete();
                }
            }
        }

        return redirect()
            ->route('tenant.integrations.channel-sync')
            ->with('status', __('Calendar links saved for :room.', ['room' => $room->name]));
    }

    public function syncNow(Room $room, ChannelCalendarSync $sync): RedirectResponse
    {
        if ($redirect = $this->blocked()) {
            return $redirect;
        }

        $links = ChannelIntegration::where('room_id', $room->id)
            ->where('mode', ChannelIntegration::MODE_ICAL)
            ->where('active', true)
            ->whereNotNull('ical_import_url')
            ->get();

        if ($links->isEmpty()) {
            return redirect()
                ->route('tenant.integrations.channel-sync')
                ->with('error', __('Add an Airbnb or Booking.com calendar URL for :room first.', ['room' => $room->name]));
        }

        $messages = [];
        foreach ($links as $link) {
            $result = $sync->importLink($link);
            $label = self::CHANNELS[$link->channel] ?? $link->channel;
            $messages[] = $label.': '.$result['message'];
        }

        return redirect()
            ->route('tenant.integrations.channel-sync')
            ->with('status', __('Synced :room — ', ['room' => $room->name]).implode('  ·  ', $messages));
    }

    public function rotate(Room $room): RedirectResponse
    {
        if ($redirect = $this->blocked()) {
            return $redirect;
        }

        $room->rotateIcalToken();

        // Keep any stored export URLs on this room's links in step with the new token.
        ChannelIntegration::where('room_id', $room->id)
            ->update(['ical_export_url' => $room->icalExportUrl()]);

        return redirect()
            ->route('tenant.integrations.channel-sync')
            ->with('status', __('New calendar link generated for :room. Update it in Airbnb and Booking.com.', ['room' => $room->name]));
    }
}
