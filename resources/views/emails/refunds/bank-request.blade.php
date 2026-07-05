<x-mail::message>
# {{ __('Your deposit refund') }}

{{ __('Hi :name,', ['name' => $booking?->guestName() ?? __('there')]) }}

{{ __('Thank you for staying with :business. We\'d like to return your deposit of RM :amount.', ['business' => $tenant?->business_name ?? config('app.name'), 'amount' => $amount]) }}

{{ __('To receive it, please submit your bank account details using the secure link below:') }}

<x-mail::button :url="$url">
{{ __('Submit bank details') }}
</x-mail::button>

{{ __('This link works without a password and is unique to your refund. Please don\'t share it. It expires in 60 days.') }}

@if ($tenant && trim($tenant->refundPolicyText()) !== '')
---
**{{ __('Refund policy') }}**

{{ $tenant->refundPolicyText() }}
@endif

{{ __('Thank you,') }}<br>
{{ $tenant?->business_name ?? config('app.name') }}
</x-mail::message>
