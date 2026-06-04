<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<title>{{ $invoice->invoice_number }}</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; color: #0f172a; font-size: 11pt; }
    .header { border-bottom: 3px solid {{ $template->color_primary }}; padding-bottom: 10px; margin-bottom: 16px; }
    .business { font-size: 18pt; font-weight: bold; }
    .doc-title { font-size: 16pt; color: {{ $template->color_primary }}; text-transform: uppercase; margin-top: 8px; }
    .meta-table, .lines-table, .totals-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .lines-table th, .lines-table td { border-bottom: 1px solid #e2e8f0; padding: 6px 4px; text-align: left; }
    .lines-table th { background: #f1f5f9; }
    .totals-table td { padding: 4px 6px; }
    .totals-table .label { text-align: right; }
    .totals-table .total { font-size: 14pt; font-weight: bold; color: {{ $template->color_primary }}; border-top: 2px solid #0f172a; }
    .footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 9pt; color: #475569; }
</style>
</head>
<body>
<div class="header">
    @if ($template->logo_path)
        <img src="{{ Storage::url($template->logo_path) }}" alt="logo" style="height:60px;">
    @endif
    <div class="business">{{ $tenant->business_name }}</div>
    @if ($tenant->ssm_number)
        <div>SSM: {{ $tenant->ssm_number }}</div>
    @endif
    <div class="doc-title">{{ ucfirst($invoice->document_type) }}</div>
</div>

<table class="meta-table">
    <tr>
        <td><strong>{{ __('Invoice No') }}:</strong> {{ $invoice->invoice_number }}</td>
        <td><strong>{{ __('Issued') }}:</strong> {{ $invoice->issued_on?->format('d M Y') }}</td>
    </tr>
    <tr>
        <td><strong>{{ __('Billed to') }}:</strong> {{ $invoice->billed_to['name'] }}</td>
        <td><strong>{{ __('Booking ref') }}:</strong> {{ $booking->reference }}</td>
    </tr>
</table>

<table class="lines-table">
    <thead><tr><th>{{ __('Description') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Unit') }}</th><th>{{ __('Total') }}</th></tr></thead>
    <tbody>
    @foreach ($invoice->line_items as $item)
        <tr>
            <td>{{ $item['description'] }}</td>
            <td>{{ $item['quantity'] }}</td>
            <td>RM {{ number_format($item['unit_price'], 2) }}</td>
            <td>RM {{ number_format($item['total'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals-table">
    <tr><td class="label">{{ __('Subtotal') }}</td><td style="text-align:right;width:120px;">RM {{ number_format($invoice->subtotal, 2) }}</td></tr>
    @if ((float) ($booking->booking_fee_amount ?? 0) > 0)
    <tr><td class="label">{{ $booking->property->booking_fee_label ?: __('Booking fee') }}</td><td style="text-align:right;">RM {{ number_format($booking->booking_fee_amount, 2) }}</td></tr>
    @endif
    @if ($invoice->sst_amount > 0)
    <tr><td class="label">{{ __('SST 8%') }}</td><td style="text-align:right;">RM {{ number_format($invoice->sst_amount, 2) }}</td></tr>
    @endif
    @if ($invoice->tourism_tax_amount > 0)
    <tr><td class="label">{{ __('Tourism Tax') }}</td><td style="text-align:right;">RM {{ number_format($invoice->tourism_tax_amount, 2) }}</td></tr>
    @endif
    <tr class="total"><td class="label">{{ __('Total') }}</td><td style="text-align:right;">RM {{ number_format($invoice->total, 2) }}</td></tr>
</table>

@if ($template->payment_instructions)
<div class="footer">
    <strong>{{ __('Payment instructions') }}:</strong><br>
    {!! nl2br(e($template->payment_instructions)) !!}
</div>
@endif

@if ($template->terms_text)
<div class="footer">
    {!! nl2br(e($template->terms_text)) !!}
</div>
@endif

@php
    // Refund/return policy snapshotted onto the booking at creation time so it
    // stays stable even if the tenant later edits their policy. Fall back to
    // the tenant's current policy text for older bookings without a snapshot.
    $refundPolicy = $booking->meta['refund_policy'] ?? $tenant->refundPolicyText();
@endphp
@if ($refundPolicy)
<div class="footer">
    <strong>{{ __('Refund / cancellation policy') }}:</strong><br>
    {!! nl2br(e($refundPolicy)) !!}
</div>
@endif
</body>
</html>
