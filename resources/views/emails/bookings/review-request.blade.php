<x-mail::message>
# {{ __('How was your stay?') }}

{{ __('Hi :name,', ['name' => $booking->bookingGuests->where('is_lead', true)->first()?->full_name ?? $booking->guestName()]) }}

{{ __('Thank you for staying at :property with :business. Would you share a short testimonial about your stay? It only takes a minute and helps other guests book with confidence.', [
    'property' => $booking->property?->name,
    'business' => $business,
]) }}

<x-mail::button :url="$reviewUrl">
{{ __('Leave a testimonial') }}
</x-mail::button>

{{ __('Your testimonial appears on the :business booking page. Thank you for helping other travellers.', ['business' => $business]) }}

{{ __('Thanks,') }}<br>
{{ $business }}
</x-mail::message>
