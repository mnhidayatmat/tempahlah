<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\WebhookEvent;
use App\Services\Billing\PlatformBillingException;
use App\Services\Billing\PlatformBillplz;
use App\Services\Billing\SubscriptionBillingService;
use App\Services\Payments\Billplz\BillplzException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Billplz card auto-renew (Tokenization) — Visa/Mastercard only.
 *
 * Two halves, mirroring SubscriptionCheckoutController + the subscription
 * webhook: the authenticated tenant starts enrollment (createCard → hosted 3DS
 * redirect), and Billplz posts the resulting token back to a public,
 * checksum-verified callback. The callback stores the token and — per the
 * "enroll + charge now" design — immediately charges the outstanding cycle so
 * enabling auto-renew also subscribes them.
 *
 * Direction of money: bills the TENANT into Tempahlah's OWN Billplz account,
 * exactly like the checkout controller. The tenant's own guest-payment gateway
 * is never touched here.
 */
class SubscriptionCardController extends Controller
{
    public function __construct(
        protected SubscriptionBillingService $billing,
        protected PlatformBillplz $billplz,
    ) {}

    /**
     * POST /dashboard/subscription/enroll-card
     * Create a Billplz card and send the tenant to the hosted 3DS page.
     */
    public function enroll(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $subscription = $tenant->subscription;
        abort_unless($subscription, 404, 'No subscription record');

        if ($subscription->isComped()) {
            return redirect()->route('tenant.subscription')
                ->with('status', __('Your account has complimentary Pro access — there is nothing to pay.'));
        }

        if (! $this->billplz->tokenizationEnabled()) {
            return redirect()->route('tenant.subscription')
                ->with('error', __('Card auto-renew is not available yet.'));
        }

        try {
            $card = $this->billplz->createCard(
                $tenant->loadMissing('owner'),
                route('subscription.card.callback'),
            );
        } catch (BillplzException|PlatformBillingException $e) {
            Log::error('Card enrollment failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return redirect()->route('tenant.subscription')
                ->with('error', __('We could not start card setup. Please try again in a moment.'));
        }

        return redirect()->away($card['redirect_url']);
    }

    /**
     * POST /api/webhooks/subscription-card  (public, CSRF-exempt, throttled)
     * Billplz posts the tokenized card here after 3DS.
     */
    public function callback(Request $request): JsonResponse
    {
        $body = $request->post();
        $signature = (string) ($body['x_signature'] ?? '');
        unset($body['x_signature']);

        $cardId = (string) ($body['id'] ?? '');
        if ($cardId === '') {
            return response()->json(['error' => 'missing_card_id'], 400);
        }

        // Distinct prefix from the 'sub:' bill callbacks — a card id and a bill
        // id must never collide in webhook_events.external_id.
        $externalId = 'card:'.$cardId;

        if (WebhookEvent::query()
            ->where('external_id', $externalId)
            ->whereNotNull('processed_at')
            ->exists()) {
            return response()->json(['status' => 'already_processed']);
        }

        if (! $this->billplz->tokenizationEnabled()) {
            Log::error('Card callback received while tokenization is off');

            return response()->json(['error' => 'not_configured'], 409);
        }

        $subscription = Subscription::query()->where('card_id', $cardId)->first()
            ?? $this->subscriptionFromReference($body);

        if (! $subscription) {
            Log::warning('Card callback: no matching subscription', ['card_id' => $cardId]);

            return response()->json(['error' => 'subscription_not_found'], 404);
        }

        $signatureStatus = $this->billplz->cardCallbackSignatureStatus($body, $signature);

        // The token charges money — unlike a payment callback there is no
        // server-side "ask Billplz" fallback for a card token, so an unverifiable
        // signature is refused outright. Still 200 so Billplz doesn't retry-storm.
        if ($signatureStatus !== 'verified') {
            $this->record($externalId, $request->post(), $signatureStatus);
            Log::warning('Card callback signature not verified — token discarded', [
                'card_id' => $cardId,
                'status' => $signatureStatus,
            ]);

            return response()->json(['error' => 'invalid_signature'], 200);
        }

        $event = $this->record($externalId, $request->post(), 'verified');

        // A card that failed 3DS is not usable — record and stop.
        $status = (string) ($body['status'] ?? '');
        if ($status !== '' && $status !== Subscription::CARD_ACTIVE) {
            $subscription->update(['card_status' => $status, 'auto_renew' => false]);
            $event->update(['processed_at' => now()]);

            return response()->json(['status' => 'card_not_active']);
        }

        try {
            $this->billing->storeCard($subscription, $body);

            // Enroll + charge now: settle whatever they currently owe (or open the
            // next cycle) against the fresh token, so enabling auto-renew also
            // subscribes them in one step.
            $invoice = $this->billing->openInvoiceFor($subscription)
                ?? $this->billing->issueInvoice($subscription, $this->billing->nextPeriodStart($subscription));

            $this->billing->chargeSavedCard($invoice, $subscription->fresh());
        } catch (\Throwable $e) {
            report($e);

            // Leave processed_at null so a Billplz retry can complete it.
            return response()->json(['error' => 'enrollment_failed'], 500);
        }

        $event->update(['processed_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * POST /dashboard/subscription/auto-renew
     * Turn auto-renew off (keeps serving until the period ends) or back on.
     */
    public function toggle(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);

        $subscription = $tenant->subscription;
        abort_unless($subscription, 404);

        $on = $request->boolean('auto_renew');

        // Can only turn it ON if there's actually a usable card on file.
        if ($on && ! ($subscription->card_status === Subscription::CARD_ACTIVE && filled($subscription->card_id))) {
            return redirect()->route('tenant.subscription')
                ->with('error', __('Add a card first to turn on auto-renew.'));
        }

        $subscription->update(['auto_renew' => $on]);

        return redirect()->route('tenant.subscription')->with('status', $on
            ? __('Auto-renew is on. Your card will be charged each month.')
            : __('Auto-renew is off. Your Pro access continues until the current period ends.'));
    }

    /**
     * Fallback subscription lookup for the rare callback that omits our card_id
     * (we always pass card_id back via reference, but be defensive).
     */
    private function subscriptionFromReference(array $body): ?Subscription
    {
        $ref = (string) ($body['reference_1'] ?? '');

        if ($ref === '') {
            return null;
        }

        $invoice = SubscriptionInvoice::query()->where('number', $ref)->first();

        return $invoice ? Subscription::find($invoice->subscription_id) : null;
    }

    private function record(string $externalId, array $payload, string $signatureStatus): WebhookEvent
    {
        return WebhookEvent::firstOrCreate(
            ['external_id' => $externalId],
            [
                'provider' => 'billplz_card',
                'event_type' => 'card.callback',
                'payload' => $payload,
                'signature_status' => $signatureStatus,
            ],
        );
    }
}
