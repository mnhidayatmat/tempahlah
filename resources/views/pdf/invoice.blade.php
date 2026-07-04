@php
    $locale    = $invoice->locale ?: ($tenant->default_locale ?? 'ms');
    $isBM      = $locale === 'ms';
    $isReceipt = $invoice->document_type === \App\Models\Invoice::TYPE_RECEIPT;

    // Brand palette (with sensible fallbacks) + light tints for the bands.
    $primary   = method_exists($tenant, 'themePrimary')   ? $tenant->themePrimary()   : '#2596c6';
    $secondary = method_exists($tenant, 'themeSecondary') ? $tenant->themeSecondary() : '#2cb8c4';

    $hexToRgb = function ($h) {
        $h = ltrim((string) $h, '#');
        if (strlen($h) !== 6) { $h = '2596c6'; }
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    };
    $tint = function ($hex, $pct) use ($hexToRgb) {
        [$r, $g, $b] = $hexToRgb($hex);
        $m = fn ($c) => (int) round($c + (255 - $c) * $pct);
        return sprintf('#%02x%02x%02x', $m($r), $m($g), $m($b));
    };
    $shade = function ($hex, $pct) use ($hexToRgb) {
        [$r, $g, $b] = $hexToRgb($hex);
        $m = fn ($c) => (int) round($c * (1 - $pct));
        return sprintf('#%02x%02x%02x', $m($r), $m($g), $m($b));
    };

    $bandFrom = $tint($primary, 0.80);
    $bandTo   = $tint($secondary, 0.80);
    $headFrom = $tint($primary, 0.88);
    $headTo   = $tint($secondary, 0.88);
    $ink      = '#2b3440';
    $muted    = '#6b7683';
    $accentDark = $shade($primary, 0.15);

    // Embed images as base64 data URIs — robust in DomPDF regardless of disk.
    $imgData = function ($path) {
        if (! $path) { return null; }
        try {
            $disk = Storage::disk(config('filesystems.default'));
            if (! $disk->exists($path)) { return null; }
            $bytes = $disk->get($path);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
            return 'data:'.$mime.';base64,'.base64_encode($bytes);
        } catch (\Throwable $e) {
            return null;
        }
    };
    $logoData = $imgData($tenant->logo_path ?? null);
    $qrData   = $imgData($tenant->bank_qr_path ?? null);

    // Header address: tenant override, else the booking's property address.
    $address = trim((string) ($tenant->business_address ?? ''));
    if ($address === '' && $booking->property) {
        $address = trim(implode(', ', array_filter([
            $booking->property->address_line1 ?? null,
            $booking->property->address_line2 ?? null,
            trim(($booking->property->postcode ?? '').' '.($booking->property->city ?? '')),
            $booking->property->state ?? null,
        ])));
    }

    $rm = fn ($v) => 'RM '.number_format((float) $v, 2);

    $L = [
        'invoice'   => $isBM ? 'INVOIS' : 'INVOICE',
        'receipt'   => $isBM ? 'RESIT' : 'RECEIPT',
        'bill_to'   => $isBM ? 'KEPADA' : 'BILL TO',
        'no'        => $isBM ? 'NO.' : 'NO.',
        'date'      => $isBM ? 'TARIKH' : 'DATE',
        'ref'       => $isBM ? 'RUJUKAN' : 'REF',
        'desc'      => $isBM ? 'BUTIRAN' : 'DESCRIPTION',
        'price'     => $isBM ? 'HARGA' : 'PRICE',
        'qty'       => $isBM ? 'KUANTITI' : 'QTY',
        'amount'    => $isBM ? 'JUMLAH' : 'AMOUNT',
        'checkin'   => $isBM ? 'Daftar masuk' : 'Check-in',
        'checkout'  => $isBM ? 'Daftar keluar' : 'Check-out',
        'nights'    => $isBM ? 'Malam' : 'Nights',
        'guests'    => $isBM ? 'Tetamu' : 'Guests',
        'subtotal'  => $isBM ? 'Subjumlah' : 'Subtotal',
        'sst'       => $isBM ? 'SST' : 'SST',
        'tax'       => $isBM ? 'Cukai Pelancongan' : 'Tourism Tax',
        'fee'       => $isBM ? 'Yuran tempahan' : 'Booking fee',
        'total'     => $isBM ? 'JUMLAH' : 'TOTAL',
        'paidamt'   => $isBM ? 'JUMLAH DIBAYAR' : 'AMOUNT PAID',
        'terms'     => $isBM ? 'Terma' : 'Terms',
        'payinfo'   => $isBM ? 'MAKLUMAT PEMBAYARAN' : 'PAYMENT DETAILS',
        'thanks'    => $isBM ? 'Terima Kasih' : 'Thank You',
        'paid'      => $isBM ? 'DIBAYAR' : 'PAID',
        'acc_name'  => $isBM ? 'Nama Akaun' : 'Account Name',
        'acc_no'    => $isBM ? 'No. Akaun' : 'Account No.',
        'method'    => $isBM ? 'Kaedah' : 'Method',
        'paid_on'   => $isBM ? 'Tarikh bayar' : 'Paid on',
        'scan'      => $isBM ? 'Imbas untuk bayar' : 'Scan to pay',
    ];

    $methodLabels = [
        'toyyibpay' => 'Toyyibpay',
        'billplz'   => 'Billplz',
        'manual'    => $isBM ? 'Tunai / Pindahan bank' : 'Cash / Bank transfer',
    ];
    $payMethod = $payment ? ($methodLabels[$payment->method] ?? ucfirst((string) $payment->method)) : null;

    $guests = (int) ($booking->adults ?? 0) + (int) ($booking->children ?? 0);
    $docTitle = $isReceipt ? $L['receipt'] : $L['invoice'];
    $refundPolicy = $booking->meta['refund_policy'] ?? (method_exists($tenant, 'refundPolicyText') ? $tenant->refundPolicyText() : null);
    $termsText = method_exists($tenant, 'invoiceTermsText') ? $tenant->invoiceTermsText() : ($template->terms_text ?? null);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<title>{{ $invoice->invoice_number }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        color: {{ $ink }};
        font-size: 10pt;
        margin: 0;
        line-height: 1.45;
    }
    .page { padding: 0 42px 42px; }

    /* Header band */
    .band {
        background-color: {{ $bandFrom }};
        background-image: linear-gradient(to right, {{ $bandFrom }}, {{ $bandTo }});
        padding: 26px 42px 22px;
        text-align: center;
    }
    .logo { max-height: 72px; max-width: 260px; }
    .biz {
        font-size: 19pt;
        font-weight: bold;
        letter-spacing: 6px;
        color: {{ $accentDark }};
        margin-top: 10px;
        text-transform: uppercase;
    }
    .tagline {
        font-style: italic;
        font-size: 10pt;
        color: {{ $shade($secondary, 0.05) }};
        letter-spacing: 1px;
        margin-top: 3px;
    }
    .addr {
        font-size: 8.5pt;
        color: {{ $muted }};
        letter-spacing: 1px;
        margin-top: 8px;
    }

    /* Doc-title ribbon */
    .doctitle {
        text-align: center;
        font-size: 15pt;
        font-weight: bold;
        letter-spacing: 8px;
        color: {{ $primary }};
        text-transform: uppercase;
        margin: 22px 0 4px;
    }
    .paidstamp {
        display: inline-block;
        border: 2px solid {{ $primary }};
        color: {{ $primary }};
        font-size: 10pt;
        font-weight: bold;
        letter-spacing: 3px;
        padding: 3px 14px;
        border-radius: 4px;
    }

    .meta { width: 100%; border-collapse: collapse; margin-top: 14px; }
    .meta td { vertical-align: top; padding: 0; }
    .label {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 2px;
        color: {{ $primary }};
        text-transform: uppercase;
    }
    .billname { font-weight: bold; font-size: 11pt; margin-top: 3px; }
    .billline { color: {{ $muted }}; font-size: 9pt; }
    .metaright td { padding: 1px 0; }

    /* Line items */
    .items { width: 100%; border-collapse: collapse; margin-top: 22px; }
    .items thead td {
        background-color: {{ $headFrom }};
        background-image: linear-gradient(to right, {{ $headFrom }}, {{ $headTo }});
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 2px;
        color: {{ $accentDark }};
        text-transform: uppercase;
        padding: 9px 12px;
    }
    .items tbody td {
        padding: 11px 12px;
        border-bottom: 0.6px solid #e6ebf0;
        font-size: 9.5pt;
    }
    .r { text-align: right; }
    .c { text-align: center; }

    /* Stay strip */
    .stay { width: 100%; border-collapse: collapse; margin-top: 14px; }
    .stay td {
        width: 25%;
        background-color: {{ $tint($primary, 0.93) }};
        padding: 10px 12px;
        border-right: 3px solid #ffffff;
    }
    .stay .k { font-size: 7.5pt; letter-spacing: 1.5px; color: {{ $muted }}; text-transform: uppercase; }
    .stay .v { font-size: 10pt; font-weight: bold; color: {{ $ink }}; margin-top: 2px; }

    /* Totals */
    .totwrap { width: 100%; border-collapse: collapse; margin-top: 18px; }
    .totbox { width: 100%; border-collapse: collapse; }
    .totbox td { padding: 5px 12px; font-size: 9.5pt; }
    .totbox .tk { color: {{ $muted }}; text-align: right; }
    .totbox .tv { text-align: right; width: 110px; }
    .grand td {
        background-color: {{ $bandFrom }};
        background-image: linear-gradient(to right, {{ $bandFrom }}, {{ $bandTo }});
        font-weight: bold;
        font-size: 12pt;
        color: {{ $accentDark }};
        padding: 12px;
        letter-spacing: 1px;
    }

    /* Terms */
    .section { margin-top: 24px; }
    .sectionhead {
        font-size: 8.5pt; font-weight: bold; letter-spacing: 2px;
        color: {{ $primary }}; text-transform: uppercase; margin-bottom: 5px;
    }
    .terms { font-size: 9pt; color: {{ $muted }}; }

    /* Footer */
    .foot { width: 100%; border-collapse: collapse; margin-top: 30px; }
    .foot td { vertical-align: top; }
    .qr { width: 92px; height: 92px; border: 1px solid #e6ebf0; padding: 3px; }
    .payk { font-size: 7.5pt; letter-spacing: 1px; color: {{ $muted }}; }
    .payv { font-size: 8.5pt; color: {{ $ink }}; }
    .thanks {
        font-family: 'DejaVu Serif', serif;
        font-style: italic;
        font-size: 20pt;
        color: {{ $primary }};
        text-align: right;
    }
</style>
</head>
<body>

<div class="band">
    @if ($logoData)
        <img src="{{ $logoData }}" class="logo" alt="logo">
    @endif
    <div class="biz">{{ $tenant->business_name }}</div>
    @if (! empty($tenant->invoice_tagline))
        <div class="tagline">&ldquo;{{ $tenant->invoice_tagline }}&rdquo;</div>
    @endif
    @if ($address !== '')
        <div class="addr">{{ $address }}</div>
    @endif
</div>

<div class="page">

    <div class="doctitle">{{ $docTitle }}</div>
    @if ($isReceipt)
        <div style="text-align:center; margin-bottom: 6px;"><span class="paidstamp">✓ {{ $L['paid'] }}</span></div>
    @endif

    {{-- Bill-to + meta --}}
    <table class="meta">
        <tr>
            <td style="width: 58%;">
                <div class="label">{{ $L['bill_to'] }}</div>
                <div class="billname">{{ $invoice->billed_to['name'] ?? '—' }}</div>
                @if (! empty($invoice->billed_to['email']))<div class="billline">{{ $invoice->billed_to['email'] }}</div>@endif
                @if (! empty($invoice->billed_to['phone']))<div class="billline">{{ $invoice->billed_to['phone'] }}</div>@endif
            </td>
            <td style="width: 42%;">
                <table class="metaright" style="width:100%;">
                    <tr>
                        <td class="label">{{ $docTitle }} {{ $L['no'] }}</td>
                        <td class="r" style="font-weight:bold;">{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td class="label">{{ $L['date'] }}</td>
                        <td class="r">{{ optional($invoice->issued_on)->format('d.m.Y') ?? now()->format('d.m.Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">{{ $L['ref'] }}</td>
                        <td class="r">{{ $booking->reference }}</td>
                    </tr>
                    @if ($isReceipt && $payMethod)
                    <tr>
                        <td class="label">{{ $L['method'] }}</td>
                        <td class="r">{{ $payMethod }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Line items --}}
    <table class="items">
        <thead>
            <tr>
                <td>{{ $L['desc'] }}</td>
                <td class="r" style="width: 90px;">{{ $L['price'] }}</td>
                <td class="c" style="width: 70px;">{{ $L['qty'] }}</td>
                <td class="r" style="width: 100px;">{{ $L['amount'] }}</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->line_items as $item)
                <tr>
                    <td>{{ $item['description'] }}</td>
                    <td class="r">{{ $rm($item['unit_price']) }}</td>
                    <td class="c">{{ $item['quantity'] }}</td>
                    <td class="r">{{ $rm($item['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Stay summary --}}
    <table class="stay">
        <tr>
            <td>
                <div class="k">{{ $L['checkin'] }}</div>
                <div class="v">{{ optional($booking->check_in)->format('d M Y') }}</div>
            </td>
            <td>
                <div class="k">{{ $L['checkout'] }}</div>
                <div class="v">{{ optional($booking->check_out)->format('d M Y') }}</div>
            </td>
            <td>
                <div class="k">{{ $L['nights'] }}</div>
                <div class="v">{{ (int) ($booking->nights ?: 1) }}</div>
            </td>
            <td style="border-right: 0;">
                <div class="k">{{ $L['guests'] }}</div>
                <div class="v">{{ $guests > 0 ? $guests : '—' }}</div>
            </td>
        </tr>
    </table>

    {{-- Totals (right aligned) --}}
    <table class="totwrap">
        <tr>
            <td style="width: 52%;"></td>
            <td style="width: 48%;">
                <table class="totbox">
                    <tr><td class="tk">{{ $L['subtotal'] }}</td><td class="tv">{{ $rm($invoice->subtotal) }}</td></tr>
                    @if ((float) ($booking->booking_fee_amount ?? 0) > 0)
                        <tr><td class="tk">{{ optional($booking->property)->booking_fee_label ?: $L['fee'] }}</td><td class="tv">{{ $rm($booking->booking_fee_amount) }}</td></tr>
                    @endif
                    @if ((float) $invoice->sst_amount > 0)
                        <tr><td class="tk">{{ $L['sst'] }}</td><td class="tv">{{ $rm($invoice->sst_amount) }}</td></tr>
                    @endif
                    @if ((float) $invoice->tourism_tax_amount > 0)
                        <tr><td class="tk">{{ $L['tax'] }}</td><td class="tv">{{ $rm($invoice->tourism_tax_amount) }}</td></tr>
                    @endif
                    <tr class="grand">
                        <td>{{ $isReceipt ? $L['paidamt'] : $L['total'] }}</td>
                        <td class="r">{{ $rm($isReceipt && $payment ? $payment->amount : $invoice->total) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Terms --}}
    @if (! empty($termsText) || ! empty($refundPolicy))
        <div class="section">
            <div class="sectionhead">{{ $L['terms'] }}</div>
            @if (! empty($termsText))
                <div class="terms">{!! nl2br(e($termsText)) !!}</div>
            @endif
            @if (! empty($refundPolicy))
                <div class="terms" style="margin-top: 5px;">{!! nl2br(e($refundPolicy)) !!}</div>
            @endif
        </div>
    @endif

    {{-- Footer: payment details / thank you --}}
    <table class="foot">
        <tr>
            <td style="width: 62%;">
                @if ($isReceipt)
                    <div class="sectionhead">{{ $L['paid'] }} ✓</div>
                    @if ($payMethod)<div class="payv">{{ $L['method'] }}: {{ $payMethod }}</div>@endif
                    @if ($payment && $payment->paid_at)<div class="payv">{{ $L['paid_on'] }}: {{ optional($payment->paid_at)->format('d M Y') }}</div>@endif
                @elseif ($tenant->hasBankDetails())
                    <table style="border-collapse:collapse;">
                        <tr>
                            @if ($qrData)
                                <td style="padding-right: 12px; vertical-align: top;">
                                    <img src="{{ $qrData }}" class="qr" alt="QR">
                                    <div class="payk" style="text-align:center; margin-top:3px;">{{ $L['scan'] }}</div>
                                </td>
                            @endif
                            <td style="vertical-align: top;">
                                <div class="sectionhead" style="margin-bottom: 4px;">{{ $L['payinfo'] }}</div>
                                @if (! empty($tenant->bank_name))<div class="payv" style="font-weight:bold;">{{ $tenant->bank_name }}</div>@endif
                                @if (! empty($tenant->bank_account_holder))<div class="payv">{{ $L['acc_name'] }}: {{ $tenant->bank_account_holder }}</div>@endif
                                @if (! empty($tenant->bank_account_number))<div class="payv">{{ $L['acc_no'] }}: {{ $tenant->bank_account_number }}</div>@endif
                                @if (! empty($tenant->ssm_number))<div class="payk" style="margin-top:3px;">SSM: {{ $tenant->ssm_number }}</div>@endif
                            </td>
                        </tr>
                    </table>
                @endif
            </td>
            <td style="width: 38%; vertical-align: bottom;">
                <div class="thanks">{{ $L['thanks'] }}</div>
            </td>
        </tr>
    </table>

</div>
</body>
</html>
