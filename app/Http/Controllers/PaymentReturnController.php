<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SettlePaymentSuccess;
use App\Models\Payment;
use App\Services\Payments\AttemptOutcome;
use App\Services\Payments\Billplz\BillplzClient;
use App\Services\Payments\SecurePay\SecurePayClient;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use Illuminate\Http\Request;

/**
 * Lands the guest back on Tempahlah after they complete (or abandon) a gateway
 * payment flow (Toyyibpay, Billplz or SecurePay).
 *
 * Reached by GET for Toyyibpay/Billplz and by POST for SecurePay, which posts
 * its result to `redirect_url`. An unsigned posted body is never trusted.
 *
 * The canonical state change normally arrives server-to-server via the
 * gateway's webhook. But that async callback can be delayed or never
 * delivered, so here we ALSO reconcile against the gateway directly — if the
 * bill is actually paid, the booking is confirmed right away (same shared
 * SettlePaymentSuccess action the webhook uses) instead of sitting on
 * "pending" until a human marks it.
 *
 * A payment that neither succeeded nor visibly failed is polled for a bounded
 * window and then given up on, with an actionable page. It must never spin
 * forever: the guest has usually already had a failure email from the gateway
 * by then, and a page that keeps saying "confirming…" is worse than one that
 * admits it doesn't know.
 */
class PaymentReturnController extends Controller
{
    /** Auto-refreshes before we stop waiting, at REFRESH_SECONDS apart. */
    protected const MAX_POLLS = 10;
    protected const REFRESH_SECONDS = 6;

    public function show(Request $request, string $payment, SettlePaymentSuccess $settle)
    {
        $row = Payment::withoutGlobalScopes()
            ->with('booking.property', 'booking.tenant')
            ->where('public_id', $payment)
            ->firstOrFail();

        $outcome = $this->resolveOutcome($request, $row);

        if ($outcome === AttemptOutcome::Paid && $row->status !== Payment::STATUS_SUCCEEDED) {
            $settle->execute($row);
            $row->refresh()->load('booking.property', 'booking.tenant');
        }

        if ($outcome === AttemptOutcome::Failed) {
            // Persist it so a plain refresh (which carries no signed gateway
            // payload) still shows the failure rather than reverting to
            // "confirming…". Payment stays retryable on the same link.
            $row->markAttemptFailed();
        }

        $polls = max(0, (int) $request->query('poll', 0));
        $waiting = $outcome === AttemptOutcome::Unknown;

        return view('payments.return', [
            'payment' => $row,
            'booking' => $row->booking,
            'state' => match (true) {
                $row->status === Payment::STATUS_SUCCEEDED => 'paid',
                $outcome === AttemptOutcome::Failed => 'failed',
                $polls >= self::MAX_POLLS => 'stalled',
                default => 'pending',
            },
            'refreshSeconds' => self::REFRESH_SECONDS,
            'nextUrl' => $waiting
                ? route('payments.return', ['payment' => $row->public_id, 'poll' => $polls + 1])
                : null,
            'retryUrl' => $row->payUrl(),
            'hostPhone' => $row->booking?->tenant?->business_phone,
        ]);
    }

    /**
     * What the gateway says about this payment, right now.
     *
     * Precedence matters. A paid claim from the guest's browser can't settle a
     * payment (it's forgeable, and even when signed it can outrun the gateway's
     * own records), but it MUST suppress a stale failure from an earlier
     * attempt — otherwise a guest who fails once, retries and succeeds gets
     * told their payment failed while it settles.
     */
    protected function resolveOutcome(Request $request, Payment $row): AttemptOutcome
    {
        if ($row->status === Payment::STATUS_SUCCEEDED) {
            return AttemptOutcome::Paid;
        }

        if (! $row->gateway_ref) {
            return $row->attemptFailed() ? AttemptOutcome::Failed : AttemptOutcome::Unknown;
        }

        try {
            $server = $this->serverOutcome($row);
        } catch (\Throwable $e) {
            // Couldn't reach the gateway. Fall back to whatever the webhook
            // already recorded; a later poll will reach it.
            report($e);
            $server = AttemptOutcome::Unknown;
        }

        if ($server === AttemptOutcome::Paid) {
            return AttemptOutcome::Paid;
        }

        $claim = $this->redirectClaim($request, $row);

        if ($claim === AttemptOutcome::Paid) {
            return AttemptOutcome::Unknown;
        }

        if ($claim === AttemptOutcome::Failed || $server === AttemptOutcome::Failed) {
            return AttemptOutcome::Failed;
        }

        return $row->attemptFailed() ? AttemptOutcome::Failed : AttemptOutcome::Unknown;
    }

    /**
     * Ask the gateway server-to-server. Trustworthy, but only Toyyibpay reports
     * a decline this way: a failed FPX transaction leaves a Billplz bill `due`
     * and a SecurePay order `payment_status: false`, neither distinguishable
     * from one nobody has attempted. So those two answer Paid or Unknown, and
     * their declines reach us via the webhook or the signed redirect instead.
     */
    protected function serverOutcome(Payment $row): AttemptOutcome
    {
        if ($row->gateway_provider === 'toyyibpay') {
            $client = ToyyibpayClient::forTenant($row->tenant_id);

            return $client->transactionsOutcome(
                $client->getBillTransactions((string) $row->gateway_ref)['transactions'] ?? []
            );
        }

        if ($row->gateway_provider === 'billplz') {
            $client = BillplzClient::forTenant($row->tenant_id);

            return $client->billOutcome($client->getBill((string) $row->gateway_ref)['bill']);
        }

        if ($row->gateway_provider === 'securepay') {
            // gateway_ref holds the order_number we sent (= payment public_id).
            $client = SecurePayClient::forTenant($row->tenant_id);

            return $client->getPaymentStatus((string) $row->gateway_ref)['paid']
                ? AttemptOutcome::Paid
                : AttemptOutcome::Unknown;
        }

        return AttemptOutcome::Unknown;
    }

    /**
     * What the gateway told the guest's browser to tell us — read ONLY when we
     * can verify the signature over it, so it can't be forged by hand.
     *
     * SecurePay POSTs a checksummed result to `redirect_url`, which is how a
     * decline surfaces on this page before its webhook lands. Toyyibpay and
     * Billplz return unsigned or differently-signed query params, so we don't
     * read theirs; their declines come from serverOutcome or the webhook.
     */
    protected function redirectClaim(Request $request, Payment $row): AttemptOutcome
    {
        if ($row->gateway_provider !== 'securepay' || ! $request->isMethod('post')) {
            return AttemptOutcome::Unknown;
        }

        $body = $request->post();
        $checksum = (string) ($body['checksum'] ?? '');
        unset($body['checksum']);

        if ($body === [] || $checksum === '') {
            return AttemptOutcome::Unknown;
        }

        try {
            $client = SecurePayClient::forTenant($row->tenant_id);
        } catch (\Throwable $e) {
            return AttemptOutcome::Unknown;
        }

        return $client->callbackSignatureStatus($body, $checksum) === 'verified'
            ? $client->attemptOutcome($body)
            : AttemptOutcome::Unknown;
    }
}
