<x-mail::message>
@if ($isTest)
> **{{ __('Test send — only you received this.') }}**
@endif
{!! $bodyMarkdown !!}

---

<small>{{ __('You receive product updates because you host on :app.', ['app' => config('app.name', 'Tempahlah')]) }} [{{ __('Unsubscribe from marketing emails') }}]({{ $unsubscribeUrl }})</small>
</x-mail::message>
