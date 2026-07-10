<x-mail::message>
# {{ __('We received your booking') }}

{{ __('Hi :name,', ['name' => $booking->bookingGuests->where('is_lead', true)->first()?->full_name ?? __('there')]) }}

{{ __('Thanks for booking :property with :business. Please complete payment using the instructions below to confirm your stay.', [
    'property' => $booking->property->name,
    'business' => $booking->tenant?->business_name ?? config('app.name'),
]) }}

- **{{ __('Booking reference') }}:** {{ $booking->reference }}
- **{{ __('Check-in') }}:** {{ $booking->check_in->format('D, d M Y') }}
- **{{ __('Check-out') }}:** {{ $booking->check_out->format('D, d M Y') }} ({{ $booking->nights }} {{ __('nights') }})
- **{{ __('Guests') }}:** {{ $booking->adults }} {{ __('adults') }}@if($booking->children), {{ $booking->children }} {{ __('children') }}@endif
- **{{ __('Total') }}:** RM {{ number_format($booking->total_amount, 2) }}
- **{{ __('Pay now') }}:** **RM {{ number_format($booking->deposit_amount, 2) }}**

<x-mail::panel>
**{{ __('How to pay') }}**

@include('emails.partials.how-to-pay')
</x-mail::panel>

{{ __('Once the host has received your payment, your booking will be confirmed.') }}

{{ __('Thanks,') }}<br>
{{ $booking->tenant?->business_name ?? config('app.name') }}
</x-mail::message>
