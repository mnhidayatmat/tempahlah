<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Invoicing\GenerateInvoice;
use App\Actions\Payments\CreateToyyibpayBill;
use App\Http\Controllers\Controller;
use App\Mail\BookingInvoiceMail;
use App\Mail\BookingReceiptMail;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\WhatsApp\WhatsappMessenger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Invoice + receipt documents for a booking, driven from the booking detail
 * page. The host can view the PDF, or send/share it to the guest by email or
 * WhatsApp — reusing the same GenerateInvoice action, mailables and WhatsApp
 * templates the automated flow uses, so a manual send is byte-identical to the
 * automatic one.
 *
 * Document rules:
 *   - Invoice   — always available (it's the bill for the stay).
 *   - Receipt   — available once ANY payment has succeeded (deposit OR full),
 *                 so a fully-paid booking can always share its receipt.
 *
 * Documents are get-or-created: the first view/send mints the Invoice record
 * (stable number + stored PDF); later actions reuse it so the number never
 * churns.
 */
class BookingDocumentController extends Controller
{
    public function __construct(protected GenerateInvoice $generateInvoice) {}

    /**
     * Stream the invoice/receipt PDF inline (opens in a new tab).
     * GET /dashboard/bookings/{id}/documents/{doc}
     */
    public function show(Request $request, $id, string $doc)
    {
        abort_unless(in_array($doc, [Invoice::TYPE_INVOICE, Invoice::TYPE_RECEIPT], true), 404);

        $booking = $this->loadBooking($id);
        $payment = $this->latestPaidPayment($booking);

        if ($doc === Invoice::TYPE_RECEIPT && ! $payment) {
            return redirect()
                ->route('tenant.bookings.show', $booking->id)
                ->with('error', __('No payment recorded yet — a receipt becomes available once the guest has paid.'));
        }

        $document = $this->getOrCreateDocument($booking, $doc, $payment);

        return $this->streamPdf($document, $booking, $payment);
    }

    /**
     * Send/share a document to the guest via email or WhatsApp.
     * POST /dashboard/bookings/{id}/documents/send  { doc, channel }
     */
    public function send(Request $request, $id)
    {
        $data = $request->validate([
            'doc'     => ['required', Rule::in([Invoice::TYPE_INVOICE, Invoice::TYPE_RECEIPT])],
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
        ]);

        $booking = $this->loadBooking($id);
        $doc     = $data['doc'];
        $channel = $data['channel'];
        $label   = $doc === Invoice::TYPE_RECEIPT ? __('Receipt') : __('Invoice');

        // Receipt needs a successful payment (deposit or full).
        $payment = $this->latestPaidPayment($booking);
        if ($doc === Invoice::TYPE_RECEIPT && ! $payment) {
            return back()->with('error', __('No payment recorded yet — a receipt becomes available once the guest has paid.'));
        }

        // Channel prerequisites.
        if ($channel === 'email' && ! $booking->guestEmail()) {
            return back()->with('error', __('No guest email on file — add one to email the :doc.', ['doc' => mb_strtolower($label)]));
        }
        if ($channel === 'whatsapp') {
            if (! optional(optional($booking->tenant)->whatsappSession)->isConnected()) {
                return back()->with('error', __('Connect WhatsApp first under Integrations → WhatsApp.'));
            }
            if (! $booking->guestPhone()) {
                return back()->with('error', __('No guest phone on file — add one to send via WhatsApp.'));
            }
        }

        $document = $this->getOrCreateDocument($booking, $doc, $payment);

        // Refresh the stored PDF from the current template/branding so the
        // attachment (email) + signed URL (WhatsApp) carry the latest design.
        $this->renderPdf($document, $booking, $payment);

        if ($doc === Invoice::TYPE_INVOICE) {
            $payUrl = $this->invoicePayUrl($booking);
            if ($channel === 'email') {
                Mail::to($booking->guestEmail())->queue(new BookingInvoiceMail($booking, $document, $payUrl));
            } elseif (! WhatsappMessenger::sendInvoiceManual($booking, $payUrl, $document)) {
                return back()->with('error', __('Could not queue the WhatsApp message (the guest may have opted out).'));
            }
        } else {
            if ($channel === 'email') {
                Mail::to($booking->guestEmail())->queue(new BookingReceiptMail($booking, $document, $payment));
            } elseif (! WhatsappMessenger::sendReceiptManual($booking, $document, $payment)) {
                return back()->with('error', __('Could not queue the WhatsApp message (the guest may have opted out).'));
            }
        }

        $via = $channel === 'email' ? __('email') : __('WhatsApp');

        return back()->with('status', __(':doc :number sent to the guest via :via.', [
            'doc'    => $label,
            'number' => $document->invoice_number,
            'via'    => $via,
        ]));
    }

    // ---------------------------------------------------------------------

    protected function loadBooking($id): Booking
    {
        return Booking::query()
            ->with(['bookingGuests', 'property', 'tenant', 'guest', 'payments'])
            ->findOrFail($id);
    }

    /** Latest succeeded payment (deposit or full), or null. */
    protected function latestPaidPayment(Booking $booking): ?Payment
    {
        return $booking->payments
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->sortByDesc('id')
            ->first();
    }

    /**
     * Return the booking's current invoice/receipt, generating (and storing
     * the PDF) once if none exists yet. A receipt is tied to the given
     * payment so its "Paid" figure is accurate.
     */
    protected function getOrCreateDocument(Booking $booking, string $type, ?Payment $payment = null): Invoice
    {
        $existing = Invoice::where('booking_id', $booking->id)
            ->where('document_type', $type)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->generateInvoice->execute(
            $booking->fresh(['property', 'tenant', 'bookingGuests']),
            $type === Invoice::TYPE_RECEIPT ? ($payment ?? $this->latestPaidPayment($booking)) : null,
            $type,
        );
    }

    /**
     * Render the document's PDF from the CURRENT template + tenant branding and
     * refresh the stored file, then return the binary. Always re-rendering (vs.
     * serving the stored file) means a template/branding change shows up
     * immediately on "View PDF" and on the next email/WhatsApp send — the
     * invoice number + line-item data stay as-issued (snapshotted on the
     * record), only the design/branding refreshes.
     */
    protected function renderPdf(Invoice $document, Booking $booking, ?Payment $payment = null): string
    {
        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice'  => $document,
            'tenant'   => $booking->tenant,
            'template' => $document->template,
            'booking'  => $booking,
            'payment'  => $payment ?? $document->payment ?? $this->latestPaidPayment($booking),
        ]);

        $binary = $pdf->output();

        // Overwrite the stored file so email attachments + the WhatsApp signed
        // URL carry the same fresh render. Non-fatal if storage hiccups.
        try {
            $path = $document->pdf_path
                ?: "tenants/{$document->tenant_id}/invoices/{$document->invoice_number}.pdf";
            Storage::disk(config('filesystems.default'))->put($path, $binary);
            if ($document->pdf_path !== $path) {
                $document->forceFill(['pdf_path' => $path])->save();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $binary;
    }

    protected function streamPdf(Invoice $document, Booking $booking, ?Payment $payment = null)
    {
        return response($this->renderPdf($document, $booking, $payment), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document->invoice_number.'.pdf"',
        ]);
    }

    /**
     * Resolve a pay link for an invoice send. Empty when the booking is fully
     * paid (nothing to collect). Reuses an existing Toyyibpay bill if one is
     * open, otherwise mints one for the outstanding amount — failing softly to
     * an empty string so the invoice (with its PDF) still goes out.
     */
    protected function invoicePayUrl(Booking $booking): string
    {
        $paid = (float) $booking->payments->where('status', Payment::STATUS_SUCCEEDED)->sum('amount');
        $outstanding = max(0, (float) $booking->total_amount - $paid);
        if ($outstanding <= 0) {
            return '';
        }

        $open = $booking->payments->first(fn ($p) => $p->status !== Payment::STATUS_SUCCEEDED
            && is_array($p->meta) && ! empty($p->meta['payment_url']));
        if ($open) {
            return $open->meta['payment_url'];
        }

        try {
            $type = $booking->deposit_paid_at ? Payment::TYPE_BALANCE : Payment::TYPE_DEPOSIT;
            $amount = $booking->deposit_paid_at
                ? $outstanding
                : min($outstanding, max((float) $booking->deposit_amount, 0) ?: $outstanding);
            $result = app(CreateToyyibpayBill::class)->execute($booking, $type, $amount);

            return $result['payment_url'] ?? '';
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }
}
