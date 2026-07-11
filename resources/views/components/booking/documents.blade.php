@props(['booking', 'compact' => false])
{{--
    Invoice & receipt quick-actions for a booking: View PDF · Email · WhatsApp,
    for both the invoice and the receipt. Receipt actions unlock once ANY
    payment has succeeded (deposit OR full) so a fully-paid booking can always
    share its receipt. Shared by the booking detail page and the calendar
    day-detail panel. Expects $booking to have `payments` loaded; `guestEmail()`
    / `guestPhone()` accessors resolve email/phone; tenant WhatsApp session
    gates the WhatsApp button.
--}}
@php
    // Issuing invoices/receipts is Pro. Scope to the booking's tenant rather than
    // the ambient one so the calendar's compact variant is always right.
    $canIssueDocuments = \Laravel\Pennant\Feature::for($booking->tenant)->active('invoice_documents');
    $hasPayment  = $booking->payments->where('status', 'succeeded')->isNotEmpty();
    $hasEmail    = (bool) $booking->guestEmail();
    $hasPhone    = (bool) $booking->guestPhone();
    $waConnected = (bool) optional(optional($booking->tenant)->whatsappSession)->isConnected();
    $canWa       = $waConnected && $hasPhone;
    $docs = [
        ['type' => 'invoice', 'title' => __('Invoice'), 'sub' => __('The bill for this booking'), 'ready' => true],
        ['type' => 'receipt', 'title' => __('Receipt'), 'sub' => $hasPayment ? __('Proof of payment') : __('Available after payment'), 'ready' => $hasPayment],
    ];
@endphp
@once
    <style>
        [x-cloak] { display:none !important; }
        .bk-doc {
            display:flex; align-items:center; justify-content:space-between; gap:14px;
            padding:14px 0; border-top:.5px solid var(--line); flex-wrap:wrap;
        }
        .bk-doc:first-child { border-top:0; }
        .bk-doc-info { display:flex; align-items:center; gap:12px; min-width:0; }
        .bk-doc-icon {
            width:38px; height:38px; border-radius:10px; flex-shrink:0;
            display:inline-flex; align-items:center; justify-content:center;
            background:var(--primary-tint); color:var(--primary);
        }
        .bk-doc-icon.is-muted { background:var(--bg-sunk); color:var(--ink-3); }
        .bk-doc-title { font-weight:600; font-size:14px; }
        .bk-doc-sub { font-size:12px; color:var(--ink-3); margin-top:1px; }
        .bk-doc-actions { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .bk-doc-actions form { margin:0; }
        .bk-doc-btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:6px 11px; border-radius:8px; border:1px solid var(--line);
            background:var(--bg-elev); color:var(--ink); font-size:12.5px;
            font-family:inherit; cursor:pointer; text-decoration:none; line-height:1.2;
        }
        .bk-doc-btn:hover:not([disabled]) { background:var(--bg-sunk); }
        .bk-doc-btn--wa:hover:not([disabled]) { border-color:var(--ok); color:var(--ok); }
        .bk-doc-btn[disabled] { opacity:.4; cursor:not-allowed; }

        /* Compact variant for the calendar day-detail panel */
        .bk-docs--compact .bk-doc { padding:9px 0; }
        .bk-docs--compact .bk-doc-icon { width:30px; height:30px; border-radius:8px; }
        .bk-docs--compact .bk-doc-title { font-size:12.5px; }
        .bk-docs--compact .bk-doc-sub { display:none; }
        .bk-docs--compact .bk-doc-btn { padding:5px 8px; font-size:11.5px; }

        @media (max-width: 768px) {
            .bk-doc { align-items:flex-start; }
            .bk-doc-actions { width:100%; }
            .bk-docs:not(.bk-docs--compact) .bk-doc-btn { flex:1; justify-content:center; }
        }
    </style>
@endonce
@if (! $canIssueDocuments)
    @if ($compact)
        {{-- Calendar day-detail: one line per booked room, so keep it to a hint. --}}
        <div style="font-size:12px; color:var(--ink-3);">
            {{ __('Invoices & receipts are a Pro feature.') }}
            <a href="{{ route('tenant.subscription') }}" style="color:var(--pro);">{{ __('Upgrade') }} →</a>
        </div>
    @else
        <x-pro-lock
            feature="invoice_documents"
            :title="__('Invoices & receipts')"
            :reason="__('Issue numbered invoices and receipts, download the branded PDF, and send it to your guest by email or WhatsApp. Your guests still receive their booking details on the free plan.')"/>
    @endif
@else
<div class="bk-docs {{ $compact ? 'bk-docs--compact' : '' }}">
    @foreach ($docs as $d)
        <div class="bk-doc">
            <div class="bk-doc-info">
                <div class="bk-doc-icon {{ $d['ready'] ? '' : 'is-muted' }}">
                    <x-icon name="receipt" :size="$compact ? 15 : 18"/>
                </div>
                <div style="min-width:0;">
                    <div class="bk-doc-title">{{ $d['title'] }}</div>
                    <div class="bk-doc-sub">{{ $d['sub'] }}</div>
                </div>
            </div>
            <div class="bk-doc-actions">
                @if ($d['ready'])
                    <x-btn-link class="bk-doc-btn" target="_blank" rel="noopener"
                       :href="route('tenant.bookings.documents.show', [$booking->id, $d['type']])">
                        <x-icon name="link" :size="13"/> {{ __('View PDF') }}
                    </x-btn-link>
                    <form method="POST" action="{{ route('tenant.bookings.documents.send', $booking->id) }}">
                        @csrf
                        <input type="hidden" name="doc" value="{{ $d['type'] }}">
                        <input type="hidden" name="channel" value="email">
                        <x-btn-submit class="bk-doc-btn"
                                :disabled="! $hasEmail"
                                title="{{ $hasEmail ? __('Email to :addr', ['addr' => $booking->guestEmail()]) : __('No guest email on file') }}">
                            <x-icon name="mail" :size="13"/> {{ __('Email') }}
                        </x-btn-submit>
                    </form>
                    <form method="POST" action="{{ route('tenant.bookings.documents.send', $booking->id) }}">
                        @csrf
                        <input type="hidden" name="doc" value="{{ $d['type'] }}">
                        <input type="hidden" name="channel" value="whatsapp">
                        <x-btn-submit class="bk-doc-btn bk-doc-btn--wa"
                                :disabled="! $canWa"
                                title="{{ $canWa ? __('Send via WhatsApp') : (! $waConnected ? __('Connect WhatsApp under Integrations') : __('No guest phone on file')) }}">
                            <x-icon name="message" :size="13"/> {{ __('WhatsApp') }}
                        </x-btn-submit>
                    </form>
                @else
                    <span style="font-size:12px; color:var(--ink-3);">{{ __('Available after payment') }}</span>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endif
