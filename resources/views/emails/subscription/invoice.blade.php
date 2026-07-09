<x-mail::message>
# {{ $dunning ? __('Your Pro subscription is unpaid') : __('Your Tempahlah Pro invoice') }}

{{ __('Hi :name,', ['name' => $invoice->tenant?->business_name ?? __('there')]) }}

@if($dunning)
{{ __('We haven\'t received payment for your Tempahlah Pro subscription yet.') }}
@if($graceEndsOn)
**{{ __('Your Pro features stay on until :date. After that your account moves to the free plan.', ['date' => $graceEndsOn]) }}**
@endif
@else
{{ __('Here is your invoice for the next month of Tempahlah Pro.') }}
@endif

- **{{ __('Invoice') }}:** {{ $invoice->number }}
- **{{ __('Period') }}:** {{ $invoice->period_start->format('d M Y') }} → {{ $invoice->period_end->format('d M Y') }}
- **{{ __('Amount') }}:** **RM {{ number_format($invoice->amount, 2) }}**
@if($invoice->due_at)
- **{{ __('Due by') }}:** {{ $invoice->due_at->format('d M Y') }}
@endif

<x-mail::button :url="$payUrl" color="primary">
{{ __('Pay') }} — RM {{ number_format($invoice->amount, 2) }}
</x-mail::button>

{{ __('You can pay by FPX or card. Payment confirms within a few minutes and your Pro features continue uninterrupted.') }}

@if($dunning)
{{ __('Already paid? Ignore this email — it may have crossed with your payment.') }}
@endif

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
