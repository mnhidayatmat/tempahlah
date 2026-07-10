{{-- Manual-payment instructions for a guest, shared by the invoice and
     booking-received emails.

     The tenant's bank details are the primary answer; the free-text
     instructions supplement them (DuitNow number, "WhatsApp the receipt", …).
     Either alone is enough — only when BOTH are missing do we fall back to
     telling the guest to contact the host, which is a dead end for them.

     Expects: $booking, and optionally $manualInstructions. --}}
@php
    $t = $booking->tenant;
    $bank = [];
    if (filled($t?->bank_name)) {
        $bank[__('Bank')] = $t->bank_name;
    }
    if (filled($t?->bank_account_holder)) {
        $bank[__('Account name')] = $t->bank_account_holder;
    }
    if (filled($t?->bank_account_number)) {
        $bank[__('Account no.')] = $t->bank_account_number;
    }
    $instructions = trim((string) ($manualInstructions ?? ''));
@endphp
@foreach($bank as $label => $value)
- **{{ $label }}:** {{ $value }}
@endforeach
@if($instructions !== '')

{{ $instructions }}
@endif
@if($bank === [] && $instructions === '')
{{ __('Please contact the host to arrange your payment.') }}
@endif

{{ __('Quote your booking reference :ref when you pay.', ['ref' => $booking->reference]) }}
