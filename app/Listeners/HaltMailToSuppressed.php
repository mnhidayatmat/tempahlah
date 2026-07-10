<?php

namespace App\Listeners;

use App\Models\EmailSuppression;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * Single choke point that stops outbound email to a suppressed address. Wired
 * to Laravel's MessageSending event in AppServiceProvider, so it covers every
 * mailable — invoices, receipts, reminders, cancellations, subscription bills —
 * with no per-call-site change, now and in future.
 *
 * SES's own account-level suppression already refuses these at the API, but
 * halting here means we never even queue the send: no wasted API call, no
 * bounce recorded against our reputation, and the door is open to surface the
 * dead address to the host later.
 *
 * Returning false from the listener aborts the send. We only abort when EVERY
 * "To" recipient is suppressed (our mailables are single-recipient, so in
 * practice that's "the guest's address is dead"); a message that still has a
 * deliverable recipient is left to go out.
 */
class HaltMailToSuppressed
{
    public function handle(MessageSending $event): bool
    {
        $to = $event->message->getTo();   // Symfony\Component\Mime\Address[]
        if ($to === []) {
            return true;
        }

        $suppressed = [];
        foreach ($to as $address) {
            if (EmailSuppression::isSuppressed($address->getAddress())) {
                $suppressed[] = $address->getAddress();
            }
        }

        // Nothing suppressed → let it send. Some but not all → let it send to
        // the remaining live recipients (SES will drop the suppressed ones).
        if (count($suppressed) < count($to)) {
            return true;
        }

        Log::warning('Mail halted — recipient(s) on the SES suppression list', [
            'to'      => $suppressed,
            'subject' => $event->message->getSubject(),
        ]);

        return false;
    }
}
