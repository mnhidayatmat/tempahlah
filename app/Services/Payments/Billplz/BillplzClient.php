<?php

namespace App\Services\Payments\Billplz;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TenantIntegration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Per-tenant Billplz client.
 *
 * Like the Toyyibpay client, tenants store their OWN credentials in
 * `tenant_integrations` (encrypted JSON): the API secret key, the collection
 * ID that bills are created under, and the X-Signature key used to verify
 * callbacks + redirects. Bills are therefore issued in the tenant's own
 * Billplz account and payouts land in their bank — the platform never holds a
 * master key.
 *
 * Sandbox (billplz-sandbox.com) is selected per-tenant via `is_sandbox` so a
 * tenant can test before pointing at production.
 *
 * API reference: https://support.billplz.com/api
 */
class BillplzClient
{
    // Note: these are the API HOST roots. `/v3/...` is appended per endpoint.
    public const PRODUCTION_BASE = 'https://www.billplz.com/api';
    public const SANDBOX_BASE    = 'https://www.billplz-sandbox.com/api';

    public function __construct(
        public readonly string $apiKey,
        public readonly string $collectionId,
        public readonly string $xSignatureKey,
        public readonly int $tenantId,
        public readonly bool $sandbox = true,
    ) {}

    /**
     * Build a client from a tenant's stored Billplz integration.
     * Throws BillplzException::notConfigured if the tenant hasn't set it up.
     */
    public static function forTenant(int $tenantId): self
    {
        $integration = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('provider', TenantIntegration::PROVIDER_BILLPLZ)
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            throw BillplzException::notConfigured($tenantId);
        }

        $config = $integration->config ?? [];
        $apiKey = (string) ($config['api_key'] ?? '');
        $collectionId = (string) ($config['collection_id'] ?? '');
        $xSignatureKey = (string) ($config['x_signature_key'] ?? '');

        if ($apiKey === '' || $collectionId === '') {
            throw BillplzException::notConfigured($tenantId);
        }

        $sandbox = (bool) ($config['is_sandbox'] ?? true);

        return new self(
            apiKey: $apiKey,
            collectionId: $collectionId,
            xSignatureKey: $xSignatureKey,
            tenantId: $tenantId,
            sandbox: $sandbox,
        );
    }

    public function baseUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_BASE : self::PRODUCTION_BASE;
    }

    /**
     * Create a bill and return [bill_code, payment_url, request, response, http_status].
     *
     * The Payment row is NOT mutated here — that's the caller's job (mirrors
     * the Toyyibpay client), so the audit log lands before the DB write.
     */
    public function createBill(
        Booking $booking,
        Payment $payment,
        string $returnUrl,
        string $callbackUrl,
    ): array {
        $lead = $booking->bookingGuests()->where('is_lead', true)->first();

        $name = mb_substr((string) ($lead?->full_name ?? $booking->guest?->name ?? 'Guest'), 0, 255);
        $description = mb_substr(
            "Stay at {$booking->property?->name} "
            ."({$booking->check_in?->toDateString()} - {$booking->check_out?->toDateString()})",
            0, 200,
        );

        $email = $lead?->email ?? $booking->guest?->email;
        $mobile = $this->cleanPhone($lead?->phone ?? $booking->guest?->phone);

        $payload = [
            'collection_id'     => $this->collectionId,
            'description'       => $description,
            'name'              => $name,
            'amount'            => (int) round($payment->amount * 100), // cents
            'callback_url'      => $callbackUrl,
            'redirect_url'      => $returnUrl,
            // We resolve the Payment back from reference_1 (our public_id) as a
            // fallback if the callback ever omits the bill id.
            'reference_1_label' => 'Booking',
            'reference_1'       => $payment->public_id,
            'reference_2_label' => 'Reference',
            'reference_2'       => $booking->reference,
        ];

        // Billplz requires email OR mobile. Prefer email (near-always present +
        // never format-rejected); only fall back to mobile when there's no
        // email, and to a benign platform email when we have neither — so a
        // malformed mobile can never block a bill that already has a good email.
        if ($email) {
            $payload['email'] = $email;
        } elseif ($mobile !== null) {
            $payload['mobile'] = $mobile;
        } else {
            $payload['email'] = 'noreply@tempahlah.com';
        }

        $response = Http::withBasicAuth($this->apiKey, '')
            ->asForm()
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 500, throw: false)
            ->post($this->baseUrl().'/v3/bills', $payload);

        $json = $this->safeJson($response);

        if (! $response->successful() || empty($json['id']) || empty($json['url'])) {
            throw BillplzException::apiError(
                $response->body() ?: 'createBill returned no bill id',
                $json,
                $response->status(),
            );
        }

        return [
            'bill_code'   => (string) $json['id'],
            'payment_url' => (string) $json['url'],
            'request'     => $this->scrub($payload),
            'response'    => $json,
            'http_status' => $response->status(),
        ];
    }

    /**
     * Fetch the canonical state of a bill. Used as the trustworthy server-side
     * reconciliation on the return page (and for callbacks without a verifiable
     * signature) — mirrors Toyyibpay's getBillTransactions fallback.
     */
    public function getBill(string $billId): array
    {
        $response = Http::withBasicAuth($this->apiKey, '')
            ->timeout(15)
            ->connectTimeout(5)
            ->get($this->baseUrl().'/v3/bills/'.$billId);

        $json = $this->safeJson($response);

        if (! $response->successful() || ! is_array($json) || empty($json['id'])) {
            throw BillplzException::apiError(
                $response->body() ?: 'getBill failed',
                $json,
                $response->status(),
            );
        }

        return [
            'bill'        => $json,
            'paid'        => $this->isPaid($json),
            'response'    => $json,
            'http_status' => $response->status(),
        ];
    }

    /**
     * Verify the X-Signature on a callback payload.
     *
     * Returns 'verified' | 'invalid' | 'missing'. 'missing' when either the
     * tenant hasn't set an X-Signature key or the callback carries no
     * x_signature — in that case the caller falls back to a server-side
     * getBill() check (server-to-server is trustworthy regardless).
     *
     * @param  array  $params  All callback POST params EXCEPT x_signature.
     */
    public function callbackSignatureStatus(array $params, ?string $signature): string
    {
        if ($this->xSignatureKey === '' || ! $signature) {
            return 'missing';
        }

        return self::verifySignature($params, $signature, $this->xSignatureKey)
            ? 'verified'
            : 'invalid';
    }

    /**
     * Verify the X-Signature on the redirect_url query params.
     *
     * Billplz sends them nested as billplz[id], billplz[paid], billplz[paid_at]
     * + billplz[x_signature]. The signature source flattens each key to
     * "billplz{key}" (underscore preserved, e.g. billplzpaid_at). Returns the
     * same 'verified'|'invalid'|'missing' contract.
     *
     * @param  array  $billplz  The `billplz` query array (incl. x_signature).
     */
    public function redirectSignatureStatus(array $billplz): string
    {
        $signature = $billplz['x_signature'] ?? null;
        if ($this->xSignatureKey === '' || ! $signature) {
            return 'missing';
        }

        $params = [];
        foreach ($billplz as $key => $value) {
            if ($key === 'x_signature') {
                continue;
            }
            $params['billplz'.$key] = $value;
        }

        return self::verifySignature($params, (string) $signature, $this->xSignatureKey)
            ? 'verified'
            : 'invalid';
    }

    /**
     * Billplz X-Signature algorithm (matches the official billplz/billplz-php
     * library): encode each key-value pair as "{key}{value}", sort those
     * encoded strings in ascending case-insensitive order, join with "|",
     * then HMAC-SHA256 with the X-Signature key.
     */
    public static function verifySignature(array $params, string $signature, string $key): bool
    {
        $encoded = [];
        foreach ($params as $k => $v) {
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            $encoded[] = $k.((string) $v);
        }

        usort($encoded, fn ($a, $b) => strcasecmp($a, $b));
        $source = implode('|', $encoded);

        $computed = hash_hmac('sha256', $source, $key);

        return hash_equals($computed, $signature);
    }

    /**
     * True if a bill JSON (from getBill or a callback) represents a paid bill.
     * `paid` is a JSON bool from getBill but the string "true" on callbacks;
     * `state` is "paid" once settled — accept any of them.
     */
    public function isPaid(array $bill): bool
    {
        $paid = $bill['paid'] ?? null;

        return $paid === true
            || $paid === 'true'
            || $paid === 1
            || $paid === '1'
            || (string) ($bill['state'] ?? '') === 'paid';
    }

    /**
     * Ping Billplz by creating then deleting a RM 1.00 bill to verify creds.
     * Used by the "Test connection" button on the integrations page.
     */
    public function testConnection(): array
    {
        $response = Http::withBasicAuth($this->apiKey, '')
            ->asForm()
            ->timeout(15)
            ->post($this->baseUrl().'/v3/bills', [
                'collection_id' => $this->collectionId,
                'description'   => 'Tempahlah integration test - safe to ignore',
                'email'         => 'noreply@tempahlah.com',
                'name'          => 'Tempahlah ping',
                'amount'        => 100, // RM 1.00
                'callback_url'  => route('webhooks.billplz'), // required by Billplz
            ]);

        $json = $this->safeJson($response);
        $ok = $response->successful() && ! empty($json['id']);

        // Clean up the throwaway bill so it doesn't linger as an unpaid due bill.
        if ($ok) {
            try {
                Http::withBasicAuth($this->apiKey, '')
                    ->timeout(10)
                    ->delete($this->baseUrl().'/v3/bills/'.$json['id']);
            } catch (\Throwable) {
                // Best-effort cleanup — a stray sandbox test bill is harmless.
            }
        }

        return [
            'ok'          => $ok,
            'bill_id'     => $json['id'] ?? null,
            'raw_body'    => $response->body(),
            'http_status' => $response->status(),
            'sandbox'     => $this->sandbox,
        ];
    }

    protected function safeJson(Response $response): ?array
    {
        try {
            $decoded = $response->json();
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function cleanPhone(?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';
        // Unlike Toyyibpay, Billplz rejects obviously-bogus mobiles, so return
        // null (omit the field) rather than a placeholder when we have nothing.
        return $digits !== '' ? $digits : null;
    }

    /**
     * Strip secrets from a payload before logging it.
     */
    protected function scrub(array $payload): array
    {
        return $payload;
    }
}
