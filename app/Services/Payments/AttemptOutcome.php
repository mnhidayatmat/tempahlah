<?php

namespace App\Services\Payments;

/**
 * The result of a single payment attempt, as reported by a gateway.
 *
 * Distinct from Payment::STATUS_* — a Payment can survive several attempts.
 * At all three gateways the bill stays payable after a decline, so a Failed
 * attempt does NOT close the Payment; the guest reopens the same payment_url.
 *
 * Unknown is the safe default. Only ever return Failed on a positive failure
 * signal from the gateway, never on the mere absence of a paid flag — an
 * unpaid bill that nobody has tried to pay yet is Unknown, not Failed.
 */
enum AttemptOutcome: string
{
    case Paid = 'paid';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
