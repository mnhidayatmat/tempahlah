<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Billing\StripeBilling;
use App\Services\Billing\SubscriptionBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe webhook for PLATFORM subscription billing — a tenant's recurring
 * RM 49/mo into Tempahlah's own Stripe account.
 *
 * Stripe drives all subscription state (it auto-charges + runs dunning), so this
 * is the authoritative path. Signature-verified against the platform webhook
 * secret; idempotent via webhook_events with a `stripe:` prefix on the event id.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeBilling $stripe,
        protected SubscriptionBillingService $billing,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->stripe->enabled()) {
            Log::error('Stripe webhook received while Stripe billing is unconfigured');

            return response()->json(['error' => 'not_configured'], 409);
        }

        // Signature is computed over the EXACT raw body — never the parsed array.
        $raw = $request->getContent();

        if (! $this->stripe->verifyWebhookSignature($raw, $request->header('Stripe-Signature'))) {
            Log::warning('Stripe webhook signature verification failed');

            // 400 (not 200): Stripe surfaces failed deliveries in the dashboard,
            // and an unverified body must never settle anything.
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $event = json_decode($raw, true);
        if (! is_array($event) || empty($event['id']) || empty($event['type'])) {
            return response()->json(['error' => 'malformed'], 400);
        }

        $externalId = 'stripe:'.$event['id'];

        if (WebhookEvent::query()
            ->where('external_id', $externalId)
            ->whereNotNull('processed_at')
            ->exists()) {
            return response()->json(['status' => 'already_processed']);
        }

        $record = WebhookEvent::firstOrCreate(
            ['external_id' => $externalId],
            [
                'provider' => 'stripe',
                'event_type' => (string) $event['type'],
                'payload' => $event,
                'signature_status' => 'verified',
            ],
        );

        try {
            $handled = $this->dispatch((string) $event['type'], $event['data']['object'] ?? []);
        } catch (\Throwable $e) {
            report($e);

            // Leave processed_at null so Stripe's retry can complete it.
            return response()->json(['error' => 'processing_failed'], 500);
        }

        $record->update(['processed_at' => now()]);

        return response()->json(['status' => $handled ? 'handled' : 'ignored']);
    }

    /**
     * Route a Stripe event to the local state machine. For subscription events
     * the object IS the subscription; for checkout/invoice events it carries only
     * the subscription id, so we retrieve the full object server-side.
     */
    private function dispatch(string $type, array $object): bool
    {
        return match ($type) {
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->billing->applyStripeSubscription($object),

            'checkout.session.completed',
            'invoice.payment_failed' => $this->applyFromSubscriptionId($object),

            // Real money received — sync state AND accrue any affiliate
            // commission on the paid amount.
            'invoice.paid' => $this->handleInvoicePaid($object),

            default => false,
        };
    }

    /**
     * invoice.paid: apply the subscription state as usual, then record an
     * affiliate commission for the amount actually charged. The invoice id is
     * the idempotency key (`stripe:{id}`), and amount_paid is 0 on the
     * trial-start invoice, so trials accrue nothing. Best-effort: a commission
     * failure never fails the webhook.
     */
    private function handleInvoicePaid(array $object): bool
    {
        $handled = $this->applyFromSubscriptionId($object);

        try {
            $amountPaid = (int) ($object['amount_paid'] ?? 0);
            $invoiceId = (string) ($object['id'] ?? '');
            $subId = $object['subscription'] ?? null;

            if ($amountPaid > 0 && $invoiceId !== '' && is_string($subId)) {
                $subscription = \App\Models\Subscription::query()
                    ->where('stripe_subscription_id', $subId)
                    ->first();

                if ($subscription) {
                    app(\App\Services\Affiliate\AffiliateCommissionService::class)->recordSubscriptionPayment(
                        (int) $subscription->tenant_id,
                        $amountPaid / 100,
                        'stripe:'.$invoiceId,
                        'Stripe invoice '.($object['number'] ?? $invoiceId),
                    );
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $handled;
    }

    /**
     * checkout.session.completed / invoice.* carry a `subscription` id, not the
     * object — fetch the authoritative Subscription and apply it.
     */
    private function applyFromSubscriptionId(array $object): bool
    {
        $subId = $object['subscription'] ?? null;

        if (! $subId || ! is_string($subId)) {
            return false;
        }

        $stripeSub = $this->stripe->retrieveSubscription($subId);

        return $this->billing->applyStripeSubscription($stripeSub);
    }
}
