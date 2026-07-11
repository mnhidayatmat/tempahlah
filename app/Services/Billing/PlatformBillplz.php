<?php

namespace App\Services\Billing;

use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Services\Payments\Billplz\BillplzClient;
use App\Services\Payments\Billplz\BillplzException;
use Illuminate\Support\Facades\Http;

/**
 * Billplz against Tempahlah's OWN merchant account, used solely to charge
 * tenants the RM 49/mo subscription.
 *
 * This is the mirror image of BillplzClient::forTenant(): credentials come from
 * config, not from `tenant_integrations`, and money lands in the platform's bank
 * rather than the host's. Keeping them in separate classes is the point — a bug
 * that crossed the two would bill a guest into the platform account, or a tenant
 * into their own.
 *
 * Billplz has no recurring/subscription/mandate endpoint (see
 * https://support.billplz.com/api), so each cycle is a plain one-off bill plus a
 * callback. Signature verification, bill lookup and paid-detection are reused
 * verbatim from BillplzClient — only bill CREATION differs, because that one is
 * shaped around a Booking.
 */
class PlatformBillplz
{
    /**
     * True when the platform has credentials. When false the subscription page
     * hides checkout and `subscriptions:bill-cycle` no-ops, so a deploy without
     * credentials changes nothing.
     */
    public function configured(): bool
    {
        return filled($this->config('api_key')) && filled($this->config('collection_id'));
    }

    /**
     * Card auto-renew (Tokenization) is a strict superset of configured(): it
     * ALSO needs the platform-wide tokenization switch, because Billplz keeps
     * Tokenization off by default (paid plan, Visa/MC only, no FPX). While this
     * is false, card enrollment/checkout stays hidden and the daily command
     * never auto-charges — so the feature ships inert.
     */
    public function tokenizationEnabled(): bool
    {
        return $this->configured()
            && (bool) config('homestay.platform_billing.tokenization', false);
    }

    public function sandbox(): bool
    {
        return (bool) $this->config('sandbox', true);
    }

    public function baseUrl(): string
    {
        return $this->sandbox() ? BillplzClient::SANDBOX_BASE : BillplzClient::PRODUCTION_BASE;
    }

    /**
     * Mint the bill for one subscription cycle.
     *
     * @return array{bill_id: string, payment_url: string, response: array}
     */
    public function createBill(
        SubscriptionInvoice $invoice,
        Tenant $tenant,
        string $redirectUrl,
        string $callbackUrl,
    ): array {
        $this->assertConfigured();

        $payload = [
            'collection_id' => (string) $this->config('collection_id'),
            'description' => mb_substr(
                'Tempahlah Pro — '.$invoice->period_start->format('d M Y').' to '.$invoice->period_end->format('d M Y'),
                0, 200,
            ),
            'name' => mb_substr((string) ($tenant->business_name ?: 'Tempahlah tenant'), 0, 255),
            'amount' => (int) round(((float) $invoice->amount) * 100), // cents
            'callback_url' => $callbackUrl,
            'redirect_url' => $redirectUrl,
            // Resolve the invoice back from reference_1 if a callback ever omits
            // the bill id — same fallback the booking gateway uses.
            'reference_1_label' => 'Invoice',
            'reference_1' => $invoice->number,
            'reference_2_label' => 'Tenant',
            'reference_2' => (string) $tenant->slug,
        ];

        // Billplz requires email OR mobile. The owner's address is the billing
        // contact; fall back to the tenant record, then to a platform address so
        // a tenant with no email on file can still be billed.
        $email = $tenant->owner?->email ?: $tenant->email;
        $payload['email'] = $email ?: 'billing@tempahlah.com';

        $response = Http::withBasicAuth((string) $this->config('api_key'), '')
            ->asForm()
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 500, throw: false)
            ->post($this->baseUrl().'/v3/bills', $payload);

        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if (! $response->successful() || empty($json['id']) || empty($json['url'])) {
            throw BillplzException::apiError(
                $response->body() ?: 'platform createBill returned no bill id',
                $json,
                $response->status(),
            );
        }

        return [
            'bill_id' => (string) $json['id'],
            'payment_url' => (string) $json['url'],
            'response' => $json,
        ];
    }

    /**
     * Begin card enrollment: create a Billplz card and return the hosted 3DS
     * page the tenant is redirected to. Billplz POSTs the resulting token to
     * $callbackUrl once the cardholder completes 3DS.
     *
     * @return array{card_id: string, redirect_url: string, response: array}
     */
    public function createCard(Tenant $tenant, string $callbackUrl): array
    {
        $this->assertTokenization();

        // Cardholder contact. Tenant has no `email` column — it's business_email;
        // the owner's address is the real billing contact. (createBill's
        // `$tenant->email` fallback silently resolves null — don't copy it.)
        $email = $tenant->owner?->email ?: $tenant->business_email ?: 'billing@tempahlah.com';

        $payload = [
            'name' => mb_substr((string) ($tenant->business_name ?: 'Tempahlah tenant'), 0, 255),
            'email' => $email,
            'callback_url' => $callbackUrl,
        ];

        $phone = $tenant->owner?->phone ?: $tenant->business_phone;
        if (filled($phone)) {
            $payload['phone'] = (string) $phone;
        }

        $response = Http::withBasicAuth((string) $this->config('api_key'), '')
            ->asForm()
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 500, throw: false)
            ->post($this->baseUrl().'/v4/cards', $payload);

        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if (! $response->successful() || empty($json['id']) || empty($json['authentication_redirect_url'])) {
            throw BillplzException::apiError(
                $response->body() ?: 'createCard returned no authentication_redirect_url',
                $json,
                $response->status(),
            );
        }

        return [
            'card_id' => (string) $json['id'],
            'redirect_url' => (string) $json['authentication_redirect_url'],
            'response' => $json,
        ];
    }

    /**
     * Charge a tokenized card against an existing bill. Synchronous: Billplz
     * returns success/failure in the response body. The caller must still
     * reconcile via getBill before trusting it — never settle on this body alone.
     *
     * @return array{success: bool, status: string, reference_id: ?string, response: array, http_status: int}
     */
    public function chargeCard(string $billId, string $cardId, string $token): array
    {
        $this->assertTokenization();

        $response = Http::withBasicAuth((string) $this->config('api_key'), '')
            ->asForm()
            ->timeout(20)
            ->connectTimeout(5)
            // No auto-retry: a charge is not idempotent on Billplz's side, and a
            // network blip after they charged would double-bill on retry.
            ->post($this->baseUrl().'/v4/bills/'.rawurlencode($billId).'/charge', [
                'card_id' => $cardId,
                'token' => $token,
            ]);

        $json = $response->json();
        $json = is_array($json) ? $json : [];

        $status = (string) ($json['status'] ?? '');

        return [
            'success' => $response->successful() && $status === 'success',
            'status' => $status,
            'reference_id' => isset($json['reference_id']) ? (string) $json['reference_id'] : null,
            'response' => $json,
            'http_status' => $response->status(),
        ];
    }

    /**
     * 'verified' | 'invalid' | 'missing' for a card-enrollment callback. Billplz
     * signs the card callback with the SAME X-Signature algorithm as bill
     * callbacks, so this delegates to the exact same verifier.
     *
     * @param  array  $params  All callback params EXCEPT x_signature.
     */
    public function cardCallbackSignatureStatus(array $params, ?string $signature): string
    {
        return $this->callbackSignatureStatus($params, $signature);
    }

    /**
     * Canonical server-side state of a bill. This is the trustworthy check —
     * used on the return page, and on any callback whose signature we could not
     * verify.
     *
     * @return array{bill: array, paid: bool}
     */
    public function getBill(string $billId): array
    {
        $this->assertConfigured();

        $result = $this->client()->getBill($billId);

        return ['bill' => $result['bill'], 'paid' => (bool) $result['paid']];
    }

    /**
     * 'verified' | 'invalid' | 'missing' for a callback POST body.
     *
     * @param  array  $params  All callback params EXCEPT x_signature.
     */
    public function callbackSignatureStatus(array $params, ?string $signature): string
    {
        $key = (string) $this->config('x_signature_key', '');

        if ($key === '' || ! $signature) {
            return 'missing';
        }

        return BillplzClient::verifySignature($params, $signature, $key) ? 'verified' : 'invalid';
    }

    /**
     * 'verified' | 'invalid' | 'missing' for the `billplz` query array on the
     * redirect back from Billplz.
     */
    public function redirectSignatureStatus(array $billplz): string
    {
        return $this->client()->redirectSignatureStatus($billplz);
    }

    public function isPaid(array $bill): bool
    {
        return $this->client()->isPaid($bill);
    }

    /**
     * A BillplzClient bound to the PLATFORM credentials. tenantId 0 marks it as
     * platform-scoped: it must never be confused with a tenant's own client.
     */
    private function client(): BillplzClient
    {
        $this->assertConfigured();

        return new BillplzClient(
            apiKey: (string) $this->config('api_key'),
            collectionId: (string) $this->config('collection_id'),
            xSignatureKey: (string) $this->config('x_signature_key', ''),
            tenantId: 0,
            sandbox: $this->sandbox(),
        );
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw PlatformBillingException::notConfigured();
        }
    }

    private function assertTokenization(): void
    {
        if (! $this->tokenizationEnabled()) {
            throw PlatformBillingException::notConfigured();
        }
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("homestay.platform_billing.billplz.{$key}", $default);
    }
}
