<?php

namespace App\Actions\Invoicing;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerateInvoice
{
    public function execute(Booking $booking, ?Payment $payment = null, string $type = Invoice::TYPE_INVOICE): Invoice
    {
        $tenant = $booking->tenant;
        $template = InvoiceTemplate::where('tenant_id', $tenant->id)
            ->where('document_type', $type)
            ->where('is_default', true)
            ->first()
            ?? InvoiceTemplate::create([
                'tenant_id' => $tenant->id,
                'name' => 'Default '.$type,
                'document_type' => $type,
                'is_default' => true,
                'number_prefix' => strtoupper(substr($tenant->slug, 0, 3)).'-'.($type === 'receipt' ? 'RCP' : 'INV'),
            ]);

        $lead = $booking->bookingGuests()->where('is_lead', true)->first();
        $billedTo = [
            'name' => $lead?->full_name ?? 'Guest',
            'email' => $lead?->email,
            'phone' => $lead?->phone,
        ];

        $lineItems = [
            [
                'description' => __('Stay at :prop (:nights nights)', ['prop' => $booking->property->name, 'nights' => $booking->nights]),
                'quantity' => $booking->nights,
                'unit_price' => $booking->base_amount / max($booking->nights, 1),
                'total' => $booking->base_amount,
            ],
        ];

        // Per-booking flat fee (cleaning fee, service fee, etc.). Uses the
        // host-defined label snapshotted from the property at booking time;
        // falls back to a generic label if the host cleared it later.
        if ((float) $booking->booking_fee_amount > 0) {
            $feeLabel = $booking->property->booking_fee_label
                ?: __('Booking fee');
            $lineItems[] = [
                'description' => $feeLabel,
                'quantity' => 1,
                'unit_price' => $booking->booking_fee_amount,
                'total' => $booking->booking_fee_amount,
            ];
        }

        if ($booking->sst_amount > 0) {
            $lineItems[] = [
                'description' => __('SST 8%'),
                'quantity' => 1, 'unit_price' => $booking->sst_amount, 'total' => $booking->sst_amount,
            ];
        }
        if ($booking->tourism_tax_amount > 0) {
            $lineItems[] = [
                'description' => __('Tourism Tax'),
                'quantity' => 1, 'unit_price' => $booking->tourism_tax_amount, 'total' => $booking->tourism_tax_amount,
            ];
        }

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'payment_id' => $payment?->id,
            'template_id' => $template->id,
            'document_type' => $type,
            'invoice_number' => $template->nextInvoiceNumber(),
            'locale' => $tenant->default_locale,
            'billed_to' => $billedTo,
            'line_items' => $lineItems,
            'subtotal' => $booking->base_amount,
            'sst_amount' => $booking->sst_amount,
            'tourism_tax_amount' => $booking->tourism_tax_amount,
            'discount_amount' => $booking->discount_amount,
            'total' => $booking->total_amount,
            'currency' => $booking->currency,
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => now()->toDateString(),
        ]);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'template' => $template,
            'booking' => $booking,
            'payment' => $payment,
        ]);

        $path = "tenants/{$tenant->id}/invoices/{$invoice->invoice_number}.pdf";
        Storage::disk(config('filesystems.default'))->put($path, $pdf->output());
        $invoice->update(['pdf_path' => $path]);

        return $invoice;
    }
}
