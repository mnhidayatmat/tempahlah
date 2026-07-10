<?php

namespace App\Services\Payments\SecurePay;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TenantIntegration;
use App\Services\Payments\AttemptOutcome;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Per-tenant SecurePay client.
 *
 * Like the Toyyibpay and Billplz clients, tenants store their OWN credentials
 * in `tenant_integrations` (encrypted JSON), so bills are issued in the
 * tenant's own SecurePay account and payouts land in their bank — the platform
 * never holds a master key. SecurePay issues three values:
 *
 *   - API UID        (uid)            e.g. 2aaa1633-e63f-4371-9b85-91d936aa56a1
 *   - Auth Token     (token)          e.g. ZyUfF8EmyabcMWPcaocX
 *   - Checksum Token (checksum_token) 64-hex, used to sign/verify checksums
 *
 * Sandbox (sandbox.securepay.my) is selected per-tenant via `is_sandbox`.
 *
 * ---------------------------------------------------------------------------
 * Why the Payment Session API and not POST /api/v1/payments
 * ---------------------------------------------------------------------------
 * SecurePay exposes two ways to start a payment:
 *
 *   1. POST /api/v1/payments — the classic FPX endpoint. It expects a
 *      checksum-signed *browser* POST (`redirect_post=true` auto-submits the
 *      shopper onto the bank page) and its success response body is not
 *      documented.
 *   2. POST /api/apis/payments — the "Payment Session" endpoint. HTTP Basic
 *      auth (uid:token), and it returns JSON containing a durable
 *      `payment_url` pointing at a hosted payment form.
 *
 * Tempahlah needs a *shareable URL*: pay links are emailed, sent over
 * WhatsApp, printed on invoices, and re-opened days later by the balance
 * reminder. Only (2) yields that, and it matches the existing
 * Toyyibpay/Billplz contract, so we use it.
 *
 * The checksum token is still required — SecurePay signs the callback and
 * redirect payloads with it, and we verify them (see verifyChecksum).
 *
 * API reference: https://docs.securepay.my/
 */
class SecurePayClient
{
    // Host roots. Paths differ per endpoint family (/api/apis vs /api/v1),
    // so unlike the Billplz client these do NOT include a version segment.
    public const PRODUCTION_BASE = 'https://securepay.my';
    public const SANDBOX_BASE    = 'https://sandbox.securepay.my';

    public function __construct(
        public readonly string $uid,
        public readonly string $authToken,
        public readonly string $checksumToken,
        public readonly int $tenantId,
        public readonly bool $sandbox = true,
    ) {}

    /**
     * Build a client from a tenant's stored SecurePay integration.
     * Throws SecurePayException::notConfigured if the tenant hasn't set it up.
     */
    public static function forTenant(int $tenantId): self
    {
        $integration = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('provider', TenantIntegration::PROVIDER_SECUREPAY)
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            throw SecurePayException::notConfigured($tenantId);
        }

        $config = $integration->config ?? [];
        $uid = (string) ($config['uid'] ?? '');
        $authToken = (string) ($config['auth_token'] ?? '');
        $checksumToken = (string) ($config['checksum_token'] ?? '');

        if ($uid === '' || $authToken === '') {
            throw SecurePayException::notConfigured($tenantId);
        }

        return new self(
            uid: $uid,
            authToken: $authToken,
            checksumToken: $checksumToken,
            tenantId: $tenantId,
            sandbox: (bool) ($config['is_sandbox'] ?? true),
        );
    }

    public function baseUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_BASE : self::PRODUCTION_BASE;
    }

    /**
     * Create a payment session and return [bill_code, payment_url, order_number, ...].
     *
     * The Payment row is NOT mutated here — that's the caller's job (mirrors
     * the Toyyibpay/Billplz clients), so the audit log lands before the DB write.
     *
     * `order_number` is the Payment's public_id: it's the only merchant-owned
     * correlation key SecurePay echoes back on the callback, and it's also the
     * key for the status endpoint.
     */
    public function createPayment(
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

        $payload = [
            // Amount is a plain decimal string — SecurePay does NOT use cents.
            'transaction_amount'  => $this->formatAmount($payment->amount),
            'order_number'        => $payment->public_id,
            'product_description' => $description,
            'buyer_name'          => $name,
            'buyer_email'         => $lead?->email ?? $booking->guest?->email ?? 'noreply@tempahlah.com',
            'callback_url'        => $callbackUrl,
            'redirect_url'        => $returnUrl,
        ];

        $phone = $this->formatPhone($lead?->phone ?? $booking->guest?->phone);
        if ($phone !== null) {
            $payload['buyer_phone'] = $phone;
        }

        $response = Http::withBasicAuth($this->uid, $this->authToken)
            ->asForm()
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 500, throw: false)
            ->post($this->baseUrl().'/api/apis/payments', $payload);

        $json = $this->safeJson($response);

        if (! $response->successful() || empty($json['payment_url'])) {
            throw SecurePayException::apiError(
                $response->body() ?: 'createPayment returned no payment_url',
                $json,
                $response->status(),
            );
        }

        $slug = (string) ($json['data']['slug'] ?? $payment->public_id);

        return [
            'bill_code'    => $slug,
            'payment_url'  => (string) $json['payment_url'],
            'order_number' => $payment->public_id,
            'request'      => $this->scrub($payload),
            'response'     => $json,
            'http_status'  => $response->status(),
        ];
    }

    /**
     * Fetch the canonical state of a transaction by our order_number.
     * Used as the trustworthy server-side reconciliation on the return page,
     * and for callbacks whose checksum we can't verify — mirrors the Billplz
     * getBill / Toyyibpay getBillTransactions fallback.
     */
    public function getPaymentStatus(string $orderNumber): array
    {
        $response = Http::withBasicAuth($this->uid, $this->authToken)
            ->timeout(15)
            ->connectTimeout(5)
            ->get($this->baseUrl().'/api/v1/payments/status/'.rawurlencode($orderNumber));

        $json = $this->safeJson($response);

        if (! $response->successful() || ! is_array($json)) {
            throw SecurePayException::apiError(
                $response->body() ?: 'getPaymentStatus failed',
                $json,
                $response->status(),
            );
        }

        return [
            'transaction' => $json,
            'paid'        => $this->isPaid($json),
            'response'    => $json,
            'http_status' => $response->status(),
        ];
    }

    /**
     * Verify the checksum on a callback / redirect payload.
     *
     * Returns 'verified' | 'invalid' | 'missing'. 'missing' when the tenant
     * stored no checksum token, SecurePay sent no checksum, or the payload
     * carries a nested value (see verifyChecksum) — in every one of those
     * cases the caller falls back to a server-side status check, which is
     * trustworthy regardless.
     *
     * @param  array  $params  All POST params EXCEPT `checksum`.
     */
    public function callbackSignatureStatus(array $params, ?string $checksum): string
    {
        if ($this->checksumToken === '' || ! $checksum) {
            return 'missing';
        }

        // A nested value (e.g. the optional `params` bag) can't be flattened
        // back into SecurePay's pipe-joined source string unambiguously.
        // Rather than guess, declare it unverifiable and let the caller
        // confirm server-side.
        foreach ($params as $value) {
            if (is_array($value) || is_object($value)) {
                return 'missing';
            }
        }

        return self::verifyChecksum($params, $checksum, $this->checksumToken)
            ? 'verified'
            : 'invalid';
    }

    /**
     * SecurePay checksum algorithm for callback + redirect payloads:
     * sort the params by key ascending, join the VALUES with "|", then
     * HMAC-SHA256 the resulting string keyed by the checksum token.
     *
     * Verified byte-for-byte against the worked example in
     * https://docs.securepay.my/api/url.md.
     *
     * Note SORT_STRING: PHP's default ksort() would compare numeric-looking
     * keys numerically, diverging from SecurePay's plain string sort.
     */
    public static function verifyChecksum(array $params, string $checksum, string $token): bool
    {
        ksort($params, SORT_STRING);

        $values = array_map(static function ($v) {
            if (is_bool($v)) {
                return $v ? 'true' : 'false';
            }
            return $v === null ? '' : (string) $v;
        }, $params);

        $computed = hash_hmac('sha256', implode('|', $values), $token);

        return hash_equals($computed, $checksum);
    }

    /**
     * True if a status/callback payload represents a successful payment.
     * `payment_status` is a JSON bool from the status API but the string
     * "true" on form-encoded callbacks — accept either.
     */
    public function isPaid(array $data): bool
    {
        $status = $data['payment_status'] ?? null;

        return $status === true
            || $status === 'true'
            || $status === 1
            || $status === '1';
    }

    /**
     * Read the outcome of one attempt out of a CALLBACK or REDIRECT payload.
     *
     * Only valid for those two — SecurePay pushes them once the shopper has
     * been through the bank, so `payment_status: false` there means declined.
     * The status API returns the same false for an order nobody has attempted
     * yet, so callers must NOT route its response through here; map its
     * `paid` flag to Paid/Unknown instead.
     *
     * `fpx_debit_auth_code` 00 = approved, 99 = pending approval (B2B1, which
     * later transitions to 00). 99 is therefore Unknown, not a failure.
     */
    public function attemptOutcome(array $payload): AttemptOutcome
    {
        if ($this->isPaid($payload)) {
            return AttemptOutcome::Paid;
        }

        if ((string) ($payload['fpx_debit_auth_code'] ?? '') === '99') {
            return AttemptOutcome::Unknown;
        }

        return array_key_exists('payment_status', $payload)
            ? AttemptOutcome::Failed
            : AttemptOutcome::Unknown;
    }

    /**
     * Validate the tenant's credentials. SecurePay's /merchants/validate
     * endpoint checks the UID + auth token (Basic auth) AND the checksum
     * token in one call — so a green result means all three are correct.
     * Nothing is created, so there's no throwaway bill to clean up.
     */
    public function testConnection(): array
    {
        $response = Http::withBasicAuth($this->uid, $this->authToken)
            ->asForm()
            ->timeout(15)
            ->post($this->baseUrl().'/api/v1/merchants/validate', [
                'checksum_token' => $this->checksumToken,
            ]);

        $json = $this->safeJson($response);
        $ok = $response->successful() && ($json['credential'] ?? false) === true;

        return [
            'ok'            => $ok,
            'merchant_name' => $json['merchant_name'] ?? null,
            'api_name'      => $json['api_name'] ?? null,
            'raw_body'      => $response->body(),
            'http_status'   => $response->status(),
            'sandbox'       => $this->sandbox,
        ];
    }

    /**
     * SecurePay wants "1540.40", never "154040" and never "1,540.40".
     */
    protected function formatAmount(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * SecurePay documents buyer_phone as +60123121678. Normalise Malaysian
     * local numbers onto that shape; return null (omit the optional field)
     * when we have nothing usable rather than sending junk.
     */
    protected function formatPhone(?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '60')) {
            return '+'.$digits;
        }

        // Local format: 0123121678 → +60123121678
        if (str_starts_with($digits, '0')) {
            return '+60'.ltrim($digits, '0');
        }

        return '+'.$digits;
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

    /**
     * Strip secrets from a payload before logging it. The session payload
     * carries no credentials (they ride in the Basic auth header), so this
     * is a pass-through kept for parity with the other gateway clients.
     */
    protected function scrub(array $payload): array
    {
        return $payload;
    }
}
