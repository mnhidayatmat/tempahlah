<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

/**
 * Stripe against Tempahlah's OWN Stripe account, used solely to charge tenants
 * the RM 49/mo Pro subscription with true recurring auto-billing.
 *
 * Like PlatformBillplz, credentials come from config/env (never the DB) and
 * money lands in the platform's bank, not a tenant's. Unlike Billplz, Stripe
 * auto-charges each cycle and runs its own dunning — so there is NO daily charge
 * command; the webhooks drive our state.
 *
 * Raw Http (no stripe/stripe-php dependency): prod's auto-deploy is
 * `git reset --hard + migrate`, not `composer install`, so a new package would
 * fatal on deploy. Matches the Billplz/SecurePay/Toyyibpay clients, which all
 * use raw Http + manual signature verification.
 *
 * API reference: https://docs.stripe.com/api
 */
class StripeBilling
{
    public const BASE = 'https://api.stripe.com';

    // Reject webhook timestamps older than this (replay-window guard).
    private const SIGNATURE_TOLERANCE = 300;

    /**
     * True when the platform has Stripe credentials + a recurring price. When
     * false the subscription page hides the Stripe UI and the webhook 409s, so a
     * deploy without keys changes nothing.
     */
    public function enabled(): bool
    {
        return filled($this->config('secret_key')) && filled($this->config('price_id'));
    }

    public function priceId(): string
    {
        return (string) $this->config('price_id');
    }

    /**
     * Get-or-create the tenant's Stripe Customer, persisting the id on the
     * subscription so we never create a duplicate.
     */
    public function ensureCustomer(Tenant $tenant, Subscription $subscription): string
    {
        $this->assertEnabled();

        if (filled($subscription->stripe_customer_id)) {
            return (string) $subscription->stripe_customer_id;
        }

        // Tenant has no `email` column — it's business_email; the owner's address
        // is the billing contact.
        $email = $tenant->owner?->email ?: $tenant->business_email ?: 'billing@tempahlah.com';

        $json = $this->post('/v1/customers', [
            'email' => $email,
            'name' => mb_substr((string) ($tenant->business_name ?: 'Tempahlah tenant'), 0, 255),
            'metadata[tenant_id]' => (string) $tenant->id,
            'metadata[tenant_slug]' => (string) $tenant->slug,
        ]);

        $id = (string) ($json['id'] ?? '');
        if ($id === '') {
            throw StripeException::apiError('createCustomer returned no id', $json);
        }

        $subscription->update(['stripe_customer_id' => $id]);

        return $id;
    }

    /**
     * Create a hosted Checkout Session in subscription mode. Returns the URL to
     * redirect the tenant to; Stripe collects the card + 3DS and sets up the
     * recurring subscription.
     *
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(
        Tenant $tenant,
        string $customerId,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $this->assertEnabled();

        $json = $this->post('/v1/checkout/sessions', [
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items[0][price]' => $this->priceId(),
            'line_items[0][quantity]' => '1',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            // Correlation keys so the webhook can resolve the local subscription.
            'client_reference_id' => (string) $tenant->public_id,
            'subscription_data[metadata][tenant_id]' => (string) $tenant->id,
        ]);

        if (empty($json['id']) || empty($json['url'])) {
            throw StripeException::apiError('createCheckoutSession returned no url', $json);
        }

        return ['id' => (string) $json['id'], 'url' => (string) $json['url']];
    }

    /**
     * Create a Billing Customer Portal session — the hosted page where the tenant
     * updates their card or cancels. Requires the portal to be configured in the
     * Stripe Dashboard.
     *
     * @return array{url: string}
     */
    public function createPortalSession(string $customerId, string $returnUrl): array
    {
        $this->assertEnabled();

        $json = $this->post('/v1/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        if (empty($json['url'])) {
            throw StripeException::apiError('createPortalSession returned no url', $json);
        }

        return ['url' => (string) $json['url']];
    }

    /**
     * Retrieve a Subscription object server-side — the trustworthy source for
     * `invoice.*` events, which carry only the subscription id.
     */
    public function retrieveSubscription(string $subscriptionId): array
    {
        $this->assertEnabled();

        $response = Http::withBasicAuth((string) $this->config('secret_key'), '')
            ->timeout(15)
            ->connectTimeout(5)
            ->get(self::BASE.'/v1/subscriptions/'.rawurlencode($subscriptionId));

        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if (! $response->successful() || empty($json['id'])) {
            throw StripeException::apiError('retrieveSubscription failed', $json, $response->status());
        }

        return $json;
    }

    /**
     * Verify a Stripe webhook signature. Header: `t=<ts>,v1=<sig>[,v1=<sig>]`.
     * signed_payload = "{t}.{rawBody}", HMAC-SHA256 keyed by the webhook secret.
     * Rejects a timestamp outside the tolerance window (replay guard).
     *
     * `$now` is injectable for testing.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $sigHeader, ?int $now = null): bool
    {
        $secret = (string) $this->config('webhook_secret');
        if ($secret === '' || ! $sigHeader) {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $timestamp = $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }

        if ($timestamp === null || $timestamp === '' || $signatures === []) {
            return false;
        }

        $now ??= time();
        if (abs($now - (int) $timestamp) > self::SIGNATURE_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Form-encoded POST with basic auth (secret key as user, blank password).
     * No auto-retry on writes: creating a customer/session isn't idempotent
     * without an Idempotency-Key, and a retry after a network blip could
     * double-create.
     */
    private function post(string $path, array $payload): array
    {
        $response = Http::withBasicAuth((string) $this->config('secret_key'), '')
            ->asForm()
            ->timeout(20)
            ->connectTimeout(5)
            ->post(self::BASE.$path, $payload);

        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if (! $response->successful()) {
            $reason = $json['error']['message'] ?? ($response->body() ?: 'request failed');
            throw StripeException::apiError($reason.' ('.$path.')', $json, $response->status());
        }

        return $json;
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw StripeException::notConfigured();
        }
    }

    private function config(string $key): mixed
    {
        return config("homestay.platform_billing.stripe.{$key}");
    }
}
