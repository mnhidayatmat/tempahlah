<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantIntegration;
use App\Models\WhatsappSession;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use App\Services\Payments\Toyyibpay\ToyyibpayException;
use App\Services\Payments\Toyyibpay\ToyyibpayLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    public const SUPPORTED = ['toyyibpay', 'google_calendar', 'whatsapp', 'ses', 'billplz'];

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
                ],
            ],
            'google_calendar' => [
                'name' => 'Google Calendar',
                'description' => 'Two-way iCal/CalDAV sync. Prevents double-bookings.',
                'pro' => true,
                'fields' => [
                    'client_id' => ['label' => 'OAuth Client ID', 'type' => 'text'],
                    'client_secret' => ['label' => 'OAuth Client secret', 'type' => 'password'],
                    'calendar_id' => ['label' => 'Calendar ID', 'type' => 'text', 'placeholder' => 'primary'],
                ],
            ],
            'whatsapp' => [
                'name' => 'WhatsApp',
                'description' => 'Scan a QR code with your phone to connect a WhatsApp account. Auto-send booking confirmations, reminders and check-in instructions.',
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
            ],
            'google_calendar' => [
                'client_id' => 'required|string|max:200',
                'client_secret' => 'required|string|max:200',
                'calendar_id' => 'nullable|string|max:200',
            ],
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
