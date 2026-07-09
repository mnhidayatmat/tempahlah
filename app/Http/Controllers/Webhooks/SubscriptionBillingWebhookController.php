<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\WebhookEvent;
use App\Services\Billing\PlatformBillplz;
use App\Services\Billing\SubscriptionBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Billplz callback for a PLATFORM subscription bill (a tenant paying Tempahlah
 * RM 49). The tenant's own booking-payment callbacks land on
 * BillplzWebhookController instead — different merchant account, different
 * credentials, different table.
 *
 * Public route, rate-limited, and defended by X-Signature verification against
 * the platform key. When the signature is absent (no key set), we don't trust
 * the posted `paid` flag at all — we ask Billplz server-side.
 */
class SubscriptionBillingWebhookController extends Controller
{
    public function __construct(
        protected SubscriptionBillingService $billing,
        protected PlatformBillplz $billplz,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $body = $request->post();
        $signature = (string) ($body['x_signature'] ?? '');
        unset($body['x_signature']);

        $billId = (string) ($body['id'] ?? '');
        $reference1 = (string) ($body['reference_1'] ?? '');

        if ($billId === '' && $reference1 === '') {
            return response()->json(['error' => 'missing_external_id'], 400);
        }

        // Namespaced: webhook_events.external_id is globally unique, and a
        // platform bill id must never collide with a tenant's booking bill id.
        $externalId = 'sub:'.($billId !== '' ? $billId : $reference1);

        if (WebhookEvent::query()
            ->where('external_id', $externalId)
            ->whereNotNull('processed_at')
            ->exists()) {
            return response()->json(['status' => 'already_processed']);
        }

        $invoice = $this->resolveInvoice($billId, $reference1);

        if (! $invoice) {
            Log::warning('Subscription billing webhook: no matching invoice', [
                'bill_id' => $billId,
                'reference_1' => $reference1,
            ]);

            return response()->json(['error' => 'invoice_not_found'], 404);
        }

        if (! $this->billplz->configured()) {
            Log::error('Subscription billing webhook received while platform billing is unconfigured');

            return response()->json(['error' => 'not_configured'], 409);
        }

        $signatureStatus = $this->billplz->callbackSignatureStatus($body, $signature);

        if ($signatureStatus === 'invalid') {
            $this->record($externalId, $request->post(), 'invalid');
            Log::warning('Subscription billing webhook: X-Signature mismatch', ['invoice' => $invoice->number]);

            // 200, not 401 — Billplz retries on non-2xx and would flood the log.
            return response()->json(['error' => 'invalid_signature'], 200);
        }

        $event = $this->record($externalId, $request->post(), $signatureStatus);

        try {
            if ($signatureStatus === 'verified' && $this->billplz->isPaid($body)) {
                // Signature proves the payload came from Billplz; trust `paid`.
                $settled = $this->billing->settle($invoice, $body);
            } else {
                // No verifiable signature — never trust the posted flag. Ask.
                $settled = $this->billing->reconcile($invoice);
            }
        } catch (\Throwable $e) {
            report($e);

            // Leave processed_at null so a Billplz retry can settle it.
            return response()->json(['error' => 'settlement_failed'], 500);
        }

        $event->update(['processed_at' => now()]);

        return response()->json(['status' => $settled ? 'settled' : 'noop']);
    }

    private function resolveInvoice(string $billId, string $reference1): ?SubscriptionInvoice
    {
        if ($billId !== '') {
            $found = SubscriptionInvoice::query()->where('gateway_bill_id', $billId)->first();

            if ($found) {
                return $found;
            }
        }

        // reference_1 carries the invoice number — the fallback when a callback
        // arrives without the bill id.
        return $reference1 !== ''
            ? SubscriptionInvoice::query()->where('number', $reference1)->first()
            : null;
    }

    private function record(string $externalId, array $payload, string $signatureStatus): WebhookEvent
    {
        return WebhookEvent::firstOrCreate(
            ['external_id' => $externalId],
            [
                'provider' => 'billplz_subscription',
                'event_type' => 'subscription.callback',
                'payload' => $payload,
                'signature_status' => $signatureStatus,
            ],
        );
    }
}
