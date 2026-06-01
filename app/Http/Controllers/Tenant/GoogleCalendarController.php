<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantIntegration;
use App\Services\Calendar\GoogleCalendarService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Tenant-side Google Calendar actions (Phase 2: calendar picker).
 *
 * The OAuth dance itself lives in App\Http\Controllers\OAuth\
 * GoogleCalendarOAuthController. Once tokens are stored, this controller
 * handles tenant choices: pick which calendar to sync to, or create a
 * brand-new "Tempahlah Bookings" calendar inside their Google account.
 */
class GoogleCalendarController extends Controller
{
    public function __construct(private GoogleCalendarService $google) {}

    /**
     * Save the tenant's calendar choice. Two paths:
     *
     *   { create_new: 1 }              → create a new calendar named
     *                                     "Tempahlah Bookings" and select it
     *   { calendar_id: 'xxx',
     *     calendar_name: 'Family' }    → select an existing calendar from
     *                                     the picker list
     */
    public function selectCalendar(Request $request): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);

        $integration = TenantIntegration::where('provider', 'google_calendar')->first();
        abort_unless($integration && ! empty($integration->config['access_token']), 404);

        $config = $integration->config;

        try {
            // Always refresh the access token before any API call — picker
            // can be loaded > 1h after OAuth.
            $accessToken = $this->google->freshAccessToken($integration);

            if ($request->boolean('create_new')) {
                $tz = $tenant->timezone ?? config('app.timezone', 'Asia/Kuala_Lumpur');
                $newCal = $this->google->createCalendar(
                    $accessToken,
                    __('Tempahlah Bookings'),
                    $tz,
                );

                if (empty($newCal['id'])) {
                    throw new \RuntimeException('Google did not return a calendar id.');
                }

                $config['calendar_id']   = $newCal['id'];
                $config['calendar_name'] = $newCal['summary'] ?? __('Tempahlah Bookings');
            } else {
                $validated = $request->validate([
                    'calendar_id'   => 'required|string|max:200',
                    'calendar_name' => 'nullable|string|max:200',
                ]);

                $config['calendar_id']   = $validated['calendar_id'];
                $config['calendar_name'] = $validated['calendar_name'] ?? $validated['calendar_id'];
            }

            $config['last_error'] = null;
            $integration->config  = $config;
            $integration->enabled = true;
            $integration->save();
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar selectCalendar failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            return redirect()
                ->route('tenant.integrations.show', 'google_calendar')
                ->with('status', __('Could not save calendar choice: :err', [
                    'err' => $e->getMessage(),
                ]));
        }

        return redirect()
            ->route('tenant.integrations.show', 'google_calendar')
            ->with('status', __('Now syncing bookings to :name.', [
                'name' => $config['calendar_name'],
            ]));
    }
}
