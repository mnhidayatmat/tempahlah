<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Mail\RefundBankRequestMail;
use App\Models\Booking;
use App\Models\Refund;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RefundController extends Controller
{
    /**
     * Update a refund's editable fields (amount, method, external ref,
     * notes, status). Used for both inline edits and transitions like
     * pending → processing → completed. Idempotent — re-PATCHing the
     * same payload is safe.
     */
    public function update(Request $request, $id)
    {
        $refund = Refund::findOrFail($id);

        $validated = $request->validate([
            'amount'             => 'nullable|numeric|min:0|max:999999.99',
            'method'             => ['nullable', 'string', 'in:'.implode(',', Refund::METHODS)],
            'external_reference' => 'nullable|string|max:120',
            'notes'              => 'nullable|string|max:1000',
            'status'             => ['required', 'string', 'in:'.implode(',', Refund::STATUSES)],
            'failure_reason'     => 'nullable|string|max:500',
        ]);

        // Status-transition guard rails. Block silly transitions (e.g.,
        // from completed back to pending) — those go through a fresh
        // refund row instead, so the audit trail stays clean.
        $allowedFrom = match ($validated['status']) {
            Refund::STATUS_PROCESSING => [Refund::STATUS_PENDING, Refund::STATUS_PROCESSING, Refund::STATUS_FAILED],
            Refund::STATUS_COMPLETED  => [Refund::STATUS_PENDING, Refund::STATUS_PROCESSING, Refund::STATUS_COMPLETED],
            Refund::STATUS_FAILED     => [Refund::STATUS_PROCESSING, Refund::STATUS_FAILED],
            Refund::STATUS_CANCELLED  => [Refund::STATUS_PENDING, Refund::STATUS_CANCELLED],
            Refund::STATUS_PENDING    => [Refund::STATUS_PENDING], // can't move backwards
            default                   => [],
        };
        if (! in_array($refund->status, $allowedFrom, true)) {
            return back()->with('error', __('Cannot move refund from :from to :to.', [
                'from' => $refund->status, 'to' => $validated['status'],
            ]));
        }

        // Completing requires a method + (for non-cash) an external
        // reference. Cash + Toyyibpay dashboard refunds skip the ref.
        if ($validated['status'] === Refund::STATUS_COMPLETED) {
            $method = $validated['method'] ?? $refund->method;
            if (! $method) {
                return back()->with('error', __('Pick a refund method before marking as completed.'));
            }
            $refRequired = ! in_array($method, [Refund::METHOD_CASH, Refund::METHOD_TOYYIBPAY_DASHBOARD], true);
            $extRef = $validated['external_reference'] ?? $refund->external_reference;
            if ($refRequired && ! $extRef) {
                return back()->with('error', __('Enter the bank / DuitNow reference number before marking as completed.'));
            }
        }

        DB::transaction(function () use ($refund, $validated, $request) {
            if (isset($validated['amount']))             $refund->amount = round((float) $validated['amount'], 2);
            if (array_key_exists('method', $validated))             $refund->method = $validated['method'] ?: null;
            if (array_key_exists('external_reference', $validated)) $refund->external_reference = $validated['external_reference'] ?: null;
            if (array_key_exists('notes', $validated))              $refund->notes = $validated['notes'] ?: null;
            if (array_key_exists('failure_reason', $validated))     $refund->failure_reason = $validated['failure_reason'] ?: null;

            $prevStatus = $refund->status;
            $refund->status = $validated['status'];

            // Stamp processed_at + processed_by on the first transition
            // INTO completed/failed/cancelled — don't overwrite on
            // re-saves of the same status.
            if (in_array($validated['status'], [Refund::STATUS_COMPLETED, Refund::STATUS_FAILED, Refund::STATUS_CANCELLED], true)
                && $prevStatus !== $validated['status']) {
                $refund->processed_at = now();
                $refund->processed_by_user_id = $request->user()?->id;
            }

            $refund->save();
        });

        return back()->with('status', __('Refund updated.'));
    }

    /**
     * Ask the guest for their bank account so the host can transfer the
     * deposit back. Mints a signed link to a public form and sends it to the
     * guest by email + WhatsApp (whichever is available), and returns the link
     * so the host can also copy/share it themselves. Stamps requested_at.
     */
    public function requestBankDetails(Request $request, $id)
    {
        $refund = Refund::with(['booking.tenant', 'booking.guest', 'booking.bookingGuests'])->findOrFail($id);
        $booking = $refund->booking;

        if (! $refund->isOpen()) {
            return back()->with('error', __('This refund is already closed — no need to request bank details.'));
        }

        $refund->forceFill(['bank_details_requested_at' => now()])->save();

        $url = $refund->bankFormUrl();
        $channels = [];

        // Email the guest the secure link.
        $email = $booking?->guestEmail();
        if ($email) {
            try {
                Mail::to($email)->queue(new RefundBankRequestMail($refund->id));
                $channels[] = __('email');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // WhatsApp the guest the secure link (only if the tenant's session is
        // connected + the guest has a phone).
        $phone = $booking?->guest?->phone ?? $booking?->resolveLeadGuest()?->phone;
        if ($booking?->tenant && $phone) {
            $body = $this->bankRequestMessage($refund, $url);
            if (WhatsappMessenger::dispatchManual($booking->tenant, $booking, $phone, $body)) {
                $channels[] = __('WhatsApp');
            }
        }

        $msg = $channels
            ? __('Bank-details request sent to the guest via :channels.', ['channels' => implode(' + ', $channels)])
            : __('Bank-details link ready — copy it below and send it to your guest.');

        return back()
            ->with('status', $msg)
            ->with('refund_bank_link', ['id' => $refund->id, 'url' => $url]);
    }

    /** BM/EN WhatsApp body for the bank-details request. */
    private function bankRequestMessage(Refund $refund, string $url): string
    {
        $business = $refund->booking?->tenant?->business_name ?? config('app.name');
        $isBM = ($refund->booking?->tenant?->default_locale ?? app()->getLocale()) === 'ms';
        $amount = 'RM '.number_format((float) $refund->amount, 2);

        return $isBM
            ? "Salam, terima kasih menginap bersama {$business}. Untuk pemulangan deposit sebanyak {$amount}, sila isikan maklumat akaun bank anda di pautan selamat ini:\n{$url}"
            : "Hi, thank you for staying with {$business}. To refund your deposit of {$amount}, please submit your bank account details at this secure link:\n{$url}";
    }

    /**
     * Manually create a new refund row. Rare — most refunds are
     * auto-created on checkout. Useful for ad-hoc / goodwill / damage
     * adjustments AFTER the auto-refund was already settled.
     */
    public function store(Request $request, $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0|max:999999.99',
            'reason' => ['required', 'string', 'in:'.implode(',', Refund::REASONS)],
            'notes'  => 'nullable|string|max:1000',
        ]);

        Refund::create([
            'public_id'   => (string) Str::ulid(),
            'tenant_id'   => $booking->tenant_id,
            'booking_id'  => $booking->id,
            'amount'      => round((float) $validated['amount'], 2),
            'currency'    => $booking->currency ?? 'MYR',
            'reason'      => $validated['reason'],
            'status'      => Refund::STATUS_PENDING,
            'notes'       => $validated['notes'] ?? null,
            'requested_at'=> now(),
        ]);

        return back()->with('status', __('Refund created.'));
    }
}
