<x-mail::message>
# {{ __('Booking cancelled') }}

{{ __('Hi :name,', ['name' => $booking->bookingGuests->where('is_lead', true)->first()?->full_name]) }}

{{ __('Your booking at :property has been cancelled.', ['property' => $booking->property->name]) }}

- **{{ __('Booking reference') }}:** {{ $booking->reference }}
- **{{ __('Check-in') }}:** {{ $booking->check_in->format('d M Y') }}
- **{{ __('Check-out') }}:** {{ $booking->check_out->format('d M Y') }}
@if ($reason)
- **{{ __('Reason') }}:** {{ $reason }}
@endif

{{ __('If you would still like to stay, please make a new booking or get in touch with us.') }}

{{ __('Thank you') }},<br>
{{ $booking->property->name }}
</x-mail::message>
