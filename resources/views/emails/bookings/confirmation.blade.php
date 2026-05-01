<x-mail::message>
# {{ __('Booking confirmed') }}

{{ __('Hi :name,', ['name' => $booking->bookingGuests->where('is_lead', true)->first()?->full_name]) }}

{{ __('Your stay at :property is confirmed.', ['property' => $booking->property->name]) }}

- **{{ __('Booking reference') }}:** {{ $booking->reference }}
- **{{ __('Check-in') }}:** {{ $booking->check_in->format('d M Y') }} ({{ $booking->property->check_in_time }})
- **{{ __('Check-out') }}:** {{ $booking->check_out->format('d M Y') }} ({{ $booking->property->check_out_time }})
- **{{ __('Guests') }}:** {{ $booking->adults }} adults, {{ $booking->children }} children
- **{{ __('Total') }}:** RM {{ number_format($booking->total_amount, 2) }}

<x-mail::button :url="config('app.url')">
{{ __('View Booking') }}
</x-mail::button>

{{ __('See you soon!') }}<br>
{{ $booking->property->name }}
</x-mail::message>
