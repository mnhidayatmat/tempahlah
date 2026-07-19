<?php

namespace App\Console\Commands;

use App\Actions\Payments\SettlePaymentSuccess;
use App\Models\Payment;
use App\Services\Payments\AttemptOutcome;
use App\Services\Payments\Billplz\BillplzClient;
use App\Services\Payments\SecurePay\SecurePayClient;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for gateway payments that got stuck on `processing`.
 *
 * A gateway payment (Toyyibpay / Billplz / SecurePay) is normally settled the
 * instant it succeeds — either by the gateway's webhook or, as a backup, by the
 * return page when the guest lands back. But BOTH can miss:
 *
 *   - the webhook can be delayed, never delivered, or dropped (e.g. a real FPX
 *     callback whose checksum diverges from ours), and
 *   - the guest can close the tab at the bank's success screen and never return
 *     to the return page.
 *
 * When both miss, the money has reached the tenant but the booking never gets
 * marked paid, and nothing else ever re-checks it. This hourly sweep closes
 * that gap: for every payment still `processing`, it asks the gateway directly
 * and, when the gateway confirms payment, settles it through the same shared
 * SettlePaymentSuccess action the webhook + return page use.
 *
 * Idempotent + safe: SettlePaymentSuccess is a no-op on an already-succeeded
 * payment, and the server-side status API is authoritative (can only ever
 * CONFIRM payment), so this can never wrongly settle an unpaid booking.
 */
class ReconcileProcessingPayments extends Command
{
    protected $signature = 'payments:reconcile-processing
        {--minutes=10 : Skip payments touched more recently than this (guest may still be paying)}
        {--days=14 : Ignore payments older than this many days (abandoned)}
        {--dry-run : Report what would settle without changing anything}';

    protected $description = 'Reconcile stuck "processing" gateway payments against the gateway and settle any that are actually paid';

    public function handle(SettlePaymentSuccess $settle): int
    {
        $now = Carbon::now();
        $freshCutoff = $now->copy()->subMinutes((int) $this->option('minutes'));
        $oldCutoff = $now->copy()->subDays((int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        $settled = 0;
        $checked = 0;

        Payment::withoutGlobalScopes()
            ->where('status', Payment::STATUS_PROCESSING)
            ->whereIn('gateway_provider', ['toyyibpay', 'billplz', 'securepay'])
            ->whereNotNull('gateway_ref')
            ->where('updated_at', '<=', $freshCutoff)
            ->where('updated_at', '>=', $oldCutoff)
            ->orderBy('id')
            ->chunkById(200, function ($payments) use (&$settled, &$checked, $settle, $dryRun) {
                foreach ($payments as $payment) {
                    $checked++;

                    try {
                        $outcome = $this->gatewayOutcome($payment);
                    } catch (\Throwable $e) {
                        // Gateway unreachable / creds rotated — leave it for the
                        // next run rather than touching the payment.
                        report($e);
                        continue;
                    }

                    if ($outcome !== AttemptOutcome::Paid) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("Would settle payment #{$payment->id} ({$payment->gateway_provider}, {$payment->type}) for booking {$payment->booking_id}");
                        $settled++;
                        continue;
                    }

                    $settle->execute($payment);
                    $settled++;
                    Log::info('Reconciled stuck gateway payment', [
                        'payment_id' => $payment->id,
                        'gateway_provider' => $payment->gateway_provider,
                        'type' => $payment->type,
                        'booking_id' => $payment->booking_id,
                    ]);
                }
            });

        $this->info(($dryRun ? '[dry-run] ' : '')."Checked {$checked} processing payment(s); settled {$settled}.");

        return self::SUCCESS;
    }

    /**
     * Ask the gateway, server-to-server, whether this payment is actually paid.
     *
     * Only ever returns Paid or Unknown — a decline and an unattempted bill are
     * indistinguishable on Billplz/SecurePay's status endpoints, so this never
     * returns Failed (this sweep only settles, never fails a payment).
     */
    protected function gatewayOutcome(Payment $payment): AttemptOutcome
    {
        $ref = (string) $payment->gateway_ref;

        return match ($payment->gateway_provider) {
            'toyyibpay' => (function () use ($payment, $ref) {
                $client = ToyyibpayClient::forTenant($payment->tenant_id);

                return $client->transactionsOutcome(
                    $client->getBillTransactions($ref)['transactions'] ?? []
                ) === AttemptOutcome::Paid ? AttemptOutcome::Paid : AttemptOutcome::Unknown;
            })(),
            'billplz' => (function () use ($payment, $ref) {
                $client = BillplzClient::forTenant($payment->tenant_id);

                return $client->billOutcome($client->getBill($ref)['bill'] ?? []) === AttemptOutcome::Paid
                    ? AttemptOutcome::Paid
                    : AttemptOutcome::Unknown;
            })(),
            'securepay' => SecurePayClient::forTenant($payment->tenant_id)
                ->getPaymentStatus($ref)['paid']
                    ? AttemptOutcome::Paid
                    : AttemptOutcome::Unknown,
            default => AttemptOutcome::Unknown,
        };
    }
}
