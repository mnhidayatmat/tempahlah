<?php

namespace App\Services\Payments\Toyyibpay;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TenantIntegration;
use App\Services\Payments\AttemptOutcome;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Per-tenant Toyyibpay client.
 *
 * Tenants store their own credentials in `tenant_integrations` (encrypted
 * JSON). Each call uses THAT tenant's userSecretKey + categoryCode, so the
 * platform never holds a master key and bills are issued in the tenant's
 * own Toyyibpay account.
 *
 * Sandbox is selected per-tenant via `is_sandbox` in the config — useful
 * during tenant onboarding before they activate their production account.
 */
class ToyyibpayClient
{
    public const PRODUCTION_BASE = 'https://toyyibpay.com';
    public const SANDBOX_BASE    = 'https://dev.toyyibpay.com';

    // Toyyibpay billPaymentChannel codes.
    //   0 = FPX (online banking) only — universally available
    //   1 = Credit card only — requires merchant card activation
    //   2 = FPX + credit card — same activation requirement as 1
    // Default is 0 because (a) every Toyyibpay merchant has FPX from day
    // one, (b) sandbox accounts often don't have card processing wired,
    // (c) accounts that haven't completed the card-merchant onboarding
    // see "this payment channel is not available" on the bill page when
    // we send 1 or 2.
    public const CHANNEL_FPX  = 0;
    public const CHANNEL_CARD = 1;
    public const CHANNEL_BOTH = 2;

    public function __construct(
        public readonly string $baseUrl,
        public readonly string $secretKey,
        public readonly string $categoryCode,
        public readonly int $tenantId,
        public readonly bool $sandbox = true,
        public readonly int $paymentChannel = self::CHANNEL_FPX,
    ) {}

    /**
     * Build a client from a tenant's stored Toyyibpay integration.
     * Throws ToyyibpayException::notConfigured if the tenant hasn't set up
     * the integration yet.
     */
    public static function forTenant(int $tenantId): self
    {
        $integration = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('provider', TenantIntegration::PROVIDER_TOYYIBPAY)
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            throw ToyyibpayException::notConfigured($tenantId);
        }

        $config = $integration->config ?? [];
        $secret = (string) ($config['user_secret_key'] ?? '');
        $cat    = (string) ($config['category_code'] ?? '');

        if ($secret === '' || $cat === '') {
            throw ToyyibpayException::notConfigured($tenantId);
        }

        $sandbox = (bool) ($config['is_sandbox'] ?? true);

        // Coerce to int and clamp to the three valid channel codes. Anything
        // outside [0,1,2] falls back to FPX (safe universal default).
        $channelRaw = (int) ($config['payment_channel'] ?? self::CHANNEL_FPX);
        $channel = in_array($channelRaw, [self::CHANNEL_FPX, self::CHANNEL_CARD, self::CHANNEL_BOTH], true)
            ? $channelRaw
            : self::CHANNEL_FPX;

        return new self(
            baseUrl: $sandbox ? self::SANDBOX_BASE : self::PRODUCTION_BASE,
            secretKey: $secret,
            categoryCode: $cat,
            tenantId: $tenantId,
            sandbox: $sandbox,
            paymentChannel: $channel,
        );
    }

    /**
     * Create a bill and return [bill_code, payment_url, raw_response, request].
     *
     * The Payment row is NOT mutated here — that's the caller's job, so we
     * can be logged before the DB write happens (audit trail stays clean
     * even if the DB commit later fails).
     */
    public function createBill(
        Booking $booking,
        Payment $payment,
        string $returnUrl,
        string $callbackUrl,
    ): array {
        $lead = $booking->bookingGuests()->where('is_lead', true)->first();

        // Toyyibpay rejects long/special-char names. Sanitize before send.
        $billName = $this->sanitise("Booking {$booking->reference}", 30);
        $billDesc = $this->sanitise(
            "Stay at {$booking->property?->name} "
            ."({$booking->check_in?->toDateString()} - {$booking->check_out?->toDateString()})",
            100,
        );

        $payload = [
            'userSecretKey' => $this->secretKey,
            'categoryCode' => $this->categoryCode,
            'billName' => $billName,
            'billDescription' => $billDesc,
            'billPriceSetting' => 1,                 // fixed amount
            'billPayorInfo' => 1,                    // collect payer info
            'billAmount' => (int) round($payment->amount * 100), // cents
            'billReturnUrl' => $returnUrl,
            'billCallbackUrl' => $callbackUrl,
            'billExternalReferenceNo' => $payment->public_id,
            'billTo' => $lead?->full_name ?? ($booking->guest?->name ?? 'Guest'),
            'billEmail' => $lead?->email ?? ($booking->guest?->email ?? 'noreply@tempahlah.com'),
            'billPhone' => $this->cleanPhone($lead?->phone ?? $booking->guest?->phone),
            'billPaymentChannel' => $this->paymentChannel,
            'billContentEmail' => 'Thank you for booking with Tempahlah.',
            'billChargeToCustomer' => 1,             // customer pays gateway fee
            'billExpiryDays' => 7,
        ];

        $response = Http::asForm()
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 500, throw: false)
            ->post($this->baseUrl.'/index.php/api/createBill', $payload);

        $json = $this->safeJson($response);

        // Toyyibpay returns a plain string body on auth failures
        // (e.g. "[FALSE]Invalid User Secret Key") instead of JSON.
        if (! $response->successful() || empty($json[0]['BillCode'])) {
            throw ToyyibpayException::apiError(
                $response->body() ?: 'createBill returned no BillCode',
                $json,
                $response->status(),
            );
        }

        $billCode = (string) $json[0]['BillCode'];

        return [
            'bill_code'    => $billCode,
            'payment_url'  => $this->baseUrl.'/'.$billCode,
            'request'      => $this->scrub($payload),
            'response'     => $json,
            'http_status'  => $response->status(),
        ];
    }

    /**
     * Verify the documented MD5 signature on a callback payload.
     *
     * Formula (per https://toyyibpay.com/apireference/):
     *   md5(userSecretKey + status + order_id + refno + "ok")
     *
     * DuitNow QR callbacks don't include `hash` — those are treated as
     * unverified and Laravel falls back to a server-side getBillTransactions
     * check (slower but reliable). Caller decides what to do with that.
     *
     * Returns 'verified' | 'invalid' | 'missing'.
     */
    public function signatureStatus(array $payload): string
    {
        if (empty($payload['hash'])) {
            return 'missing';
        }

        $expected = md5(
            $this->secretKey
            .((string) ($payload['status'] ?? ''))
            .((string) ($payload['order_id'] ?? ''))
            .((string) ($payload['refno'] ?? ''))
            .'ok'
        );

        return hash_equals($expected, (string) $payload['hash']) ? 'verified' : 'invalid';
    }

    /**
     * Read the outcome of one attempt out of a CALLBACK payload.
     * `status`: 1 = success, 2 = pending, 3 = fail.
     */
    public function attemptOutcome(array $payload): AttemptOutcome
    {
        return match ((int) ($payload['status'] ?? 0)) {
            1 => AttemptOutcome::Paid,
            3 => AttemptOutcome::Failed,
            default => AttemptOutcome::Unknown,
        };
    }

    /**
     * Read the outcome of a server-side getBillTransactions() response.
     * A bill can carry several transactions (a decline, then a retry), so a
     * single success anywhere wins; otherwise the most recent one decides.
     *
     * `billpaymentStatus`: "1" = success, "2" = pending, "3" = fail.
     *
     * @param  array  $transactions  The `transactions` key of getBillTransactions().
     */
    public function transactionsOutcome(array $transactions): AttemptOutcome
    {
        $statuses = array_map(
            static fn ($t) => (string) ($t['billpaymentStatus'] ?? ''),
            array_values($transactions),
        );

        if (in_array('1', $statuses, true)) {
            return AttemptOutcome::Paid;
        }

        return end($statuses) === '3'
            ? AttemptOutcome::Failed
            : AttemptOutcome::Unknown;
    }

    /**
     * Pull the canonical transaction state for a bill from Toyyibpay's API.
     * Used as a fallback for unverifiable callbacks (e.g. DuitNow QR which
     * doesn't include a hash) — server-to-server is trustworthy regardless.
     */
    public function getBillTransactions(string $billCode): array
    {
        $response = Http::asForm()
            ->timeout(15)
            ->connectTimeout(5)
            ->post($this->baseUrl.'/index.php/api/getBillTransactions', [
                'userSecretKey' => $this->secretKey,
                'billCode' => $billCode,
            ]);

        $json = $this->safeJson($response);

        if (! $response->successful() || ! is_array($json)) {
            throw ToyyibpayException::apiError(
                $response->body() ?: 'getBillTransactions failed',
                $json,
                $response->status(),
            );
        }

        return [
            'transactions' => $json,
            'response'     => $json,
            'http_status'  => $response->status(),
        ];
    }

    /**
     * Ping Toyyibpay with a minimal createBill request to verify creds.
     * Used by the "Test connection" button on the integrations page.
     */
    public function testConnection(): array
    {
        $payload = [
            'userSecretKey' => $this->secretKey,
            'categoryCode' => $this->categoryCode,
            'billName' => 'Tempahlah ping',
            'billDescription' => 'Integration test - safe to ignore',
            'billPriceSetting' => 1,
            'billPayorInfo' => 0,
            'billAmount' => 100, // RM 1.00 (lowest sane integer)
            'billPaymentChannel' => $this->paymentChannel,
            'billExpiryDays' => 1,
        ];

        $response = Http::asForm()
            ->timeout(15)
            ->post($this->baseUrl.'/index.php/api/createBill', $payload);

        $json = $this->safeJson($response);
        $ok = $response->successful() && ! empty($json[0]['BillCode']);

        return [
            'ok' => $ok,
            'bill_code' => $json[0]['BillCode'] ?? null,
            'payment_url' => $ok ? $this->baseUrl.'/'.$json[0]['BillCode'] : null,
            'raw_body' => $response->body(),
            'http_status' => $response->status(),
            'sandbox' => $this->sandbox,
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

    protected function sanitise(string $text, int $max): string
    {
        // Toyyibpay only allows alphanumeric + space + underscore.
        $clean = preg_replace('/[^A-Za-z0-9 _]/u', ' ', $text) ?? '';
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? '';
        return mb_substr($clean, 0, $max);
    }

    protected function cleanPhone(?string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';
        // Toyyibpay requires a phone — fall back to a benign placeholder if
        // we have nothing. A real number is preferred but Toyyibpay still
        // accepts placeholders for B2C bills.
        return $digits !== '' ? $digits : '60000000000';
    }

    /**
     * Strip secrets from a payload before logging it.
     */
    protected function scrub(array $payload): array
    {
        $out = $payload;
        if (isset($out['userSecretKey'])) {
            $out['userSecretKey'] = substr($out['userSecretKey'], 0, 4).'…'.substr($out['userSecretKey'], -4);
        }
        return $out;
    }
}
