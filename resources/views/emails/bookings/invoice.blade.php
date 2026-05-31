<x-mail::message>
# {{ __('Pay your deposit') }}

{{ __('Hi :name,', ['name' => $booking->bookingGuests->where('is_lead', true)->first()?->full_name ?? __('there')]) }}

{{ __('Thanks for booking :property with :business. To confirm your stay, please complete the deposit payment below.', [
    'property' => $booking->property->name,
    'business' => $booking->tenant?->business_name ?? config('app.name'),
]) }}

- **{{ __('Booking reference') }}:** {{ $booking->reference }}
- **{{ __('Check-in') }}:** {{ $booking->check_in->format('D, d M Y') }}
- **{{ __('Check-out') }}:** {{ $booking->check_out->format('D, d M Y') }} ({{ $booking->nights }} {{ __('nights') }})
- **{{ __('Guests') }}:** {{ $booking->adults }} {{ __('adults') }}@if($booking->children), {{ $booking->children }} {{ __('children') }}@endif
- **{{ __('Total') }}:** RM {{ number_format($booking->total_amount, 2) }}
- **{{ __('Deposit due now') }}:** **RM {{ number_format($booking->deposit_amount, 2) }}**

<x-mail::button :url="$payUrl" color="primary">
{{ __('Pay deposit') }} — RM {{ number_format($booking->deposit_amount, 2) }}
</x-mail::button>

{{ __('This payment link is valid for 7 days. As soon as we receive your deposit, your booking will be confirmed and you\'ll receive a receipt automatically.') }}

{{ __('Invoice :num attached to this email.', ['num' => $invoice->invoice_number]) }}

{{ __('Thanks,') }}<br>
{{ $booking->tenant?->business_name ?? config('app.name') }}
</x-mail::message>
