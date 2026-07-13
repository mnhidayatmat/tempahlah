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

    public function priceId(string $plan = Subscription::PLAN_PRO): string
    {
        return (string) ($plan === Subscription::PLAN_ULTRA
            ? $this->config('price_id_ultra')
            : $this->config('price_id'));
    }

    /**
     * Can this plan be checked out? Ultra needs its own Stripe Price
     * (STRIPE_PRICE_ID_ULTRA) on top of the base Stripe keys.
     */
    public function planAvailable(string $plan): bool
    {
        return $this->enabled() && filled($this->priceId($plan));
    }

    /**
     * Which local plan a Stripe subscription's price represents. Defaults to
     * pro for the base price and anything unrecognized — an operator swapping
     * prices in the Stripe Dashboard must never accidentally grant Ultra.
     */
    public function planForPriceId(?string $priceId): string
    {
        $ultra = (string) $this->config('price_id_ultra');

        return filled($ultra) && $priceId === $ultra
            ? Subscription::PLAN_ULTRA
            : Subscription::PLAN_PRO;
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
     * When $trialDays > 0 the card is still captured up front but no charge is
     * taken until the trial ends — then Stripe auto-charges the recurring price.
     * Cancelling before the trial ends (cancel_at_period_end) avoids the charge.
     *
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(
        Tenant $tenant,
        string $customerId,
        string $successUrl,
        string $cancelUrl,
        ?int $trialDays = null,
        string $plan = Subscription::PLAN_PRO,
    ): array {
        $this->assertEnabled();

        $payload = [
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items[0][price]' => $this->priceId($plan),
            'line_items[0][quantity]' => '1',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            // Card only. A recurring subscription needs a REUSABLE payment method;
            // FPX / bank debits (Malaysian online banking) are single-use and can't
            // be saved for auto-renewal, so if they're enabled in the Stripe
            // Dashboard they'd surface here and then fail with "unable to
            // authenticate your payment method". Pinning to card avoids that.
            // (Bank/FPX hosts use the one-off Billplz FPX pay-link fallback.)
            'payment_method_types[0]' => 'card',
            // Correlation keys so the webhook can resolve the local subscription.
            'client_reference_id' => (string) $tenant->public_id,
            'subscription_data[metadata][tenant_id]' => (string) $tenant->id,
        ];

        if ($trialDays !== null && $trialDays > 0) {
            // Native Stripe trial: subscription-mode Checkout still collects the
            // card (payment_method_collection defaults to `always`), so this is a
            // card-required free trial.
            $payload['subscription_data[trial_period_days]'] = (string) $trialDays;
        }

        $json = $this->post('/v1/checkout/sessions', $payload);

        if (empty($json['id']) || empty($json['url'])) {
            throw StripeException::apiError('createCheckoutSession returned no url', $json);
        }

        return ['id' => (string) $json['id'], 'url' => (string) $json['url']];
    }

    /**
     * Schedule cancellation at the end of the current period. During a trial this
     * means the tenant keeps Pro until the trial end date and is never charged;
     * on an active subscription they keep Pro until the paid period they've
     * already covered runs out. The webhook (customer.subscription.updated, then
     * .deleted at period end) reconciles our local state either way.
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        $this->assertEnabled();

        return $this->post('/v1/subscriptions/'.rawurlencode($subscriptionId), [
            'cancel_at_period_end' => 'true',
        ]);
    }

    /**
     * Undo a scheduled cancellation while the subscription is still live.
     */
    public function resumeSubscription(string $subscriptionId): array
    {
        $this->assertEnabled();

        return $this->post('/v1/subscriptions/'.rawurlencode($subscriptionId), [
            'cancel_at_period_end' => 'false',
        ]);
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
     * "Test connection" for the super-admin settings screen. Hits GET /v1/account
     * with the stored secret key. Read-only, creates nothing. Also sanity-checks
     * that the configured price exists + is recurring.
     *
     * @return array{ok: bool, account: ?string, country: ?string, currency: ?string, price_ok: bool, error: ?string}
     */
    public function testConnection(): array
    {
        $secret = (string) $this->config('secret_key');
        if ($secret === '') {
            return ['ok' => false, 'account' => null, 'country' => null, 'currency' => null, 'price_ok' => false, 'error' => 'No secret key set.'];
        }

        $acct = Http::withBasicAuth($secret, '')->timeout(15)->get(self::BASE.'/v1/account');
        $ajson = is_array($acct->json()) ? $acct->json() : [];

        if (! $acct->successful() || empty($ajson['id'])) {
            return [
                'ok' => false, 'account' => null, 'country' => null, 'currency' => null, 'price_ok' => false,
                'error' => $ajson['error']['message'] ?? 'Secret key rejected by Stripe.',
            ];
        }

        // Confirm the price id resolves + is recurring (so checkout won't 400 later).
        $priceOk = false;
        if (filled($this->priceId())) {
            $price = Http::withBasicAuth($secret, '')->timeout(15)
                ->get(self::BASE.'/v1/prices/'.rawurlencode($this->priceId()));
            $pjson = is_array($price->json()) ? $price->json() : [];
            $priceOk = $price->successful() && ! empty($pjson['recurring']);
        }

        return [
            'ok' => true,
            'account' => (string) $ajson['id'],
            'country' => $ajson['country'] ?? null,
            'currency' => $ajson['default_currency'] ?? null,
            'price_ok' => $priceOk,
            'error' => null,
        ];
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

    /**
     * A key configured by the super-admin in the UI (encrypted in
     * platform_settings) wins; otherwise fall back to the .env/config value. So
     * either way of configuring Stripe works, and the DB never has to be
     * pre-seeded for an env-only deploy.
     */
    private function config(string $key): mixed
    {
        return \App\Models\PlatformSetting::get(
            "stripe.{$key}",
            config("homestay.platform_billing.stripe.{$key}"),
        );
    }
}
