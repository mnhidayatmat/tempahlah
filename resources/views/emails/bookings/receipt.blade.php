<x-mail::message>
# {{ __('Payment received') }}

{{ __('Hi :name,', ['name' => $booking->bookingGuests->where('is_lead', true)->first()?->full_name ?? __('there')]) }}

{{ __('Thank you! Your deposit has been received and your booking at :property is confirmed.', [
    'property' => $booking->property->name,
]) }}

- **{{ __('Receipt number') }}:** {{ $receipt->invoice_number }}
- **{{ __('Booking reference') }}:** {{ $booking->reference }}
- **{{ __('Property') }}:** {{ $booking->property->name }}
- **{{ __('Check-in') }}:** {{ $booking->check_in->format('D, d M Y') }} ({{ \Illuminate\Support\Str::limit($booking->property->check_in_time, 5, '') }})
- **{{ __('Check-out') }}:** {{ $booking->check_out->format('D, d M Y') }} ({{ \Illuminate\Support\Str::limit($booking->property->check_out_time, 5, '') }})
- **{{ __('Nights') }}:** {{ $booking->nights }}
- **{{ __('Amount paid') }}:** **RM {{ number_format($payment->amount, 2) }}** ({{ ucfirst($payment->type) }})
- **{{ __('Outstanding balance') }}:** RM {{ number_format(max(0, $booking->total_amount - $payment->amount), 2) }}
- **{{ __('Paid via') }}:** Toyyibpay
@if(($payment->meta['callback']['transaction_id'] ?? null))
- **{{ __('Transaction ID') }}:** {{ $payment->meta['callback']['transaction_id'] }}
@endif

{{ __('The full receipt PDF (:num) is attached to this email for your records.', ['num' => $receipt->invoice_number]) }}

{{ __('See you on :date! Reply to this email or message us on WhatsApp if you have any questions.', ['date' => $booking->check_in->format('D, d M Y')]) }}

{{ __('Warm regards,') }}<br>
{{ $booking->tenant?->business_name ?? config('app.name') }}
</x-mail::message>
