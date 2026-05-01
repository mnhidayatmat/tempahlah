<?php

namespace App\Services\Calendar;

use App\Models\Booking;
use App\Models\ChannelIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    protected string $base = 'https://www.googleapis.com/calendar/v3';

    public function authorizeUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
            'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$params;
    }

    public function exchangeCodeForTokens(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        return $response;
    }

    public function pushBooking(ChannelIntegration $integration, Booking $booking): void
    {
        $creds = $integration->credentials_encrypted ?? [];
        if (empty($creds['access_token']) || empty($creds['calendar_id'])) {
            Log::warning('GoogleCalendar not connected', ['integration' => $integration->id]);
            return;
        }

        Http::withToken($creds['access_token'])
            ->post("{$this->base}/calendars/{$creds['calendar_id']}/events", [
                'summary' => "Booking {$booking->reference}",
                'description' => "{$booking->property->name} · {$booking->bookingGuests->where('is_lead', true)->first()?->full_name}",
                'start' => ['date' => $booking->check_in->toDateString()],
                'end' => ['date' => $booking->check_out->toDateString()],
                'extendedProperties' => ['private' => ['booking_ref' => $booking->reference]],
            ]);
    }
}
