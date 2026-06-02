<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
