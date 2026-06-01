<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantIntegration;
use App\Models\WhatsappSession;
use App\Services\Calendar\GoogleCalendarService;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use App\Services\Payments\Toyyibpay\ToyyibpayException;
use App\Services\Payments\Toyyibpay\ToyyibpayLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    public const SUPPORTED = ['toyyibpay', 'google_calendar', 'whatsapp', 'agent', 'ses', 'billplz'];

    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        $records = TenantIntegration::query()->get()->keyBy('provider');

        // WhatsApp doesn't use the tenant_integrations table — it has its own
        // whatsapp_sessions row (Baileys QR-scan flow). Synthesize a record-
        // shaped object so the index view's `$rec->enabled` / `$rec->connected_at`
        // checks light up the "Connected" pill when the session is live.
        $waSession = WhatsappSession::query()->where('tenant_id', $tenant?->id)->first();
        if ($waSession && $waSession->isConnected()) {
            $records['whatsapp'] = new TenantIntegration([
                'provider'     => 'whatsapp',
                'enabled'      => true,
                'connected_at' => $waSession->connected_at ?? $waSession->updated_at,
            ]);
        }

        return view('tenant.integrations.index', [
            'tenant' => $tenant,
            'records' => $records,
        ]);
    }

    public function show(string $provider)
    {
        abort_unless(in_array($provider, self::SUPPORTED, true), 404);

        // WhatsApp uses a QR-scan Baileys session, not a credential form.
        if ($provider === 'whatsapp') {
            return view('tenant.integrations.whatsapp', [
                'provider' => $provider,
                'meta' => $this->providerMeta($provider),
            ]);
        }

        // AI agent has its own dedicated Livewire panel — no credential form.
        if ($provider === 'agent') {
            return view('tenant.integrations.agent', [
                'provider' => $provider,
                'meta' => $this->providerMeta($provider),
            ]);
        }

        // Google Calendar uses platform-owned OAuth — no credential form.
        // Tenant clicks "Connect" → consent → tokens stored automatically.
        // Three render states: disconnected / needs-picker / connected.
        if ($provider === 'google_calendar') {
            $record = TenantIntegration::where('provider', 'google_calendar')->first();
            $calendars = null;
            $pickerError = null;

            // Decide if we should render the picker:
            //   - tokens present but no calendar chosen yet (fresh OAuth)
            //   - or tenant explicitly clicked "Change calendar" (?edit=1)
            $hasTokens = $record && ! empty($record->config['access_token']);
            $needsPicker = $hasTokens && (
                empty($record->config['calendar_id'])
                || request()->boolean('edit')
            );

            if ($needsPicker) {
                try {
                    $accessToken = app(GoogleCalendarService::class)->freshAccessToken($record);
                    $calendars = app(GoogleCalendarService::class)->listCalendars($accessToken);
                    // Sort: primary first, then by name.
                    usort($calendars, function ($a, $b) {
                        if (($a['primary'] ?? false) !== ($b['primary'] ?? false)) {
                            return ($a['primary'] ?? false) ? -1 : 1;
                        }
                        return strcasecmp($a['summary'] ?? '', $b['summary'] ?? '');
                    });
                } catch (\Throwable $e) {
                    $pickerError = $e->getMessage();
                }
            }

            return view('tenant.integrations.google_calendar', [
                'provider'    => $provider,
                'meta'        => $this->providerMeta($provider),
                'record'      => $record,
                'needsPicker' => $needsPicker,
                'calendars'   => $calendars,
                'pickerError' => $pickerError,
            ]);
        }

        $tenant = app(TenantContext::class)->current();
        $record = TenantIntegration::firstOrNew([
            'provider' => $provider,
        ], [
            'tenant_id' => $tenant->id,
            'enabled' => false,
            'config' => [],
        ]);

        return view('tenant.integrations.show', [
            'provider' => $provider,
            'record' => $record,
            'meta' => $this->providerMeta($provider),
        ]);
    }

    public function update(Request $request, string $provider)
    {
        abort_unless(in_array($provider, self::SUPPORTED, true), 404);

        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);

        $rules = $this->validationRulesFor($provider);
        $validated = $request->validate($rules);

        $integration = TenantIntegration::firstOrNew(['provider' => $provider]);
        $integration->tenant_id = $tenant->id;
        $integration->enabled = $request->boolean('enabled');
        $integration->config = $validated;
        if (! $integration->connected_at && $integration->enabled) {
            $integration->connected_at = now();
        }
        $integration->save();

        return redirect()
            ->route('tenant.integrations.index')
            ->with('status', __(':name :state.', [
                'name' => $this->providerMeta($provider)['name'],
                'state' => $integration->enabled ? __('connected') : __('saved (disabled)'),
            ]));
    }

    /**
     * "Test connection" for Toyyibpay. Creates a 1-cent bill against the
     * tenant's stored credentials and reports back the result without
     * persisting any Payment row.
     */
    public function testToyyibpay()
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);

        try {
            $client = ToyyibpayClient::forTenant($tenant->id);
        } catch (ToyyibpayException $e) {
            return redirect()
                ->route('tenant.integrations.show', 'toyyibpay')
                ->with('status', __('Save your credentials first, then click Test connection.'));
        }

        $result = $client->testConnection();

        ToyyibpayLog::recordApiCall(
            $tenant->id, null, 'testConnection',
            ['sandbox' => $client->sandbox],
            ['bill_code' => $result['bill_code'], 'raw_body' => $result['raw_body']],
            $result['http_status'], $result['ok'],
            $result['ok'] ? null : Str::limit($result['raw_body'], 200),
        );

        return redirect()
            ->route('tenant.integrations.show', 'toyyibpay')
            ->with('status', $result['ok']
                ? __('✓ Toyyibpay is working. Test BillCode: :code (:env)', [
                    'code' => $result['bill_code'],
                    'env'  => $client->sandbox ? 'sandbox' : 'production',
                  ])
                : __('✗ Toyyibpay rejected the credentials: :err', [
                    'err' => Str::limit($result['raw_body'], 200),
                  ]));
    }

    public function disconnect(string $provider)
    {
        abort_unless(in_array($provider, self::SUPPORTED, true), 404);

        $integration = TenantIntegration::where('provider', $provider)->first();

        // For Google Calendar, best-effort revoke the refresh_token at Google's
        // end so the tenant's permission grant in their Google account also
        // disappears. We don't fail the disconnect if revoke errors out.
        if ($provider === 'google_calendar' && $integration && ! empty($integration->config['refresh_token'])) {
            app(GoogleCalendarService::class)->revokeToken($integration->config['refresh_token']);
        }

        if ($integration) {
            $integration->update(['enabled' => false, 'config' => null, 'connected_at' => null]);
        }

        return redirect()
            ->route('tenant.integrations.index')
            ->with('status', __(':name disconnected.', ['name' => $this->providerMeta($provider)['name']]));
    }

    public function providerMeta(string $provider): array
    {
        $catalog = [
            'toyyibpay' => [
                'name' => 'Toyyibpay',
                'description' => 'Accept FPX, cards, and e-wallets. Tenant uses their own Toyyibpay account — payouts land directly in their bank. After saving, click Test connection to verify the credentials.',
                'pro' => true,
                'fields' => [
                    'user_secret_key' => ['label' => 'User secret key', 'type' => 'password', 'placeholder' => 'From Toyyibpay → Profile → User Secret Key'],
                    'category_code'   => ['label' => 'Category code',   'type' => 'text',     'placeholder' => 'From Toyyibpay → Categories → your category'],
                    'is_sandbox'      => ['label' => 'Use sandbox (dev.toyyibpay.com)', 'type' => 'checkbox'],
                    'payment_channel' => [
                        'label' => 'Payment methods',
                        'type' => 'select',
                        'options' => [
                            '0' => 'FPX only (online banking) — works for every merchant',
                            '1' => 'Credit/debit card only — requires card activation',
                            '2' => 'FPX + cards — requires card activation',
                        ],
                        'default' => '0',
                        'help' => 'If your bill page shows "selected payment channel is not available", your account has not activated cards yet — switch to FPX only.',
                    ],
                ],
            ],
            'google_calendar' => [
                'name' => 'Google Calendar',
                'description' => 'One-click connect with your Google account. Bookings flow into your calendar automatically — no double-bookings.',
                'pro' => true,
                'fields' => [],
            ],
            'whatsapp' => [
                'name' => 'WhatsApp',
                'description' => 'Scan a QR code with your phone to connect a WhatsApp account. Auto-send booking confirmations, reminders and check-in instructions.',
                'pro' => true,
                'fields' => [],
            ],
            'agent' => [
                'name' => 'AI Agent',
                'description' => 'When a guest messages your connected WhatsApp, an AI assistant replies on your behalf — checking availability, sending photos, sharing the location and quoting prices from your real data. Escalates to you for anything sensitive.',
                'pro' => true,
                'fields' => [],
            ],
            'ses' => [
                'name' => 'Amazon SES',
                'description' => 'Send confirmations and reminders from your domain.',
                'pro' => false,
                'fields' => [
                    'region' => ['label' => 'AWS region', 'type' => 'text', 'placeholder' => 'ap-southeast-1'],
                    'from_email' => ['label' => 'From email (verified)', 'type' => 'email'],
                    'from_name' => ['label' => 'From name', 'type' => 'text'],
                ],
            ],
            'billplz' => [
                'name' => 'Billplz (v2)',
                'description' => 'Recurring subscription billing.',
                'pro' => true,
                'soon' => true,
                'fields' => [],
            ],
        ];

        return $catalog[$provider];
    }

    protected function validationRulesFor(string $provider): array
    {
        return match ($provider) {
            'toyyibpay' => [
                'user_secret_key' => 'required|string|max:200',
                'category_code' => 'required|string|max:32',
                'is_sandbox' => 'sometimes|boolean',
                'payment_channel' => 'sometimes|in:0,1,2',
            ],
            // google_calendar uses OAuth (no form) — no validation rules.
            'whatsapp' => [
                'phone_number_id' => 'required|string|max:64',
                'access_token' => 'required|string|max:500',
                'business_account_id' => 'nullable|string|max:64',
            ],
            'ses' => [
                'region' => 'required|string|max:32',
                'from_email' => 'required|email|max:200',
                'from_name' => 'nullable|string|max:120',
            ],
            default => [],
        };
    }
}
