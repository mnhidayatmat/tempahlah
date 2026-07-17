<x-mail::message>
# {{ __('Your Pro trial is ending soon') }}

{{ __('Hi :name,', ['name' => $businessName ?? __('there')]) }}

{{ __('Your free Tempahlah Pro trial ends on :date — that\'s :days day(s) away.', ['date' => $endsOn, 'days' => $daysLeft]) }}

{{ __('Want to keep your Pro features — the AI WhatsApp agent, custom invoices, dynamic pricing, calendar sync, marketplace listing and more? Subscribe to continue on Pro. It\'s just RM 49/month.') }}

<x-mail::button :url="$subscribeUrl" color="primary">
{{ __('Continue with Pro') }}
</x-mail::button>

{{ __('No action needed if you\'d rather not — your account will simply move to the free plan when the trial ends, and your homestays and bookings stay exactly as they are.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
