@props(['disabled' => false, 'spinner' => true])
{{--
    A submit button that shows a spinner and refuses a second click once its
    form is in flight. Use it for any action that does network I/O, renders a
    PDF, or touches money — anything where a frozen page invites a double-click.

    The parent must be a <form>; the guard is attached to the submit event, so
    HTML5 validation failures and cancelled confirm() dialogs leave the button
    untouched.

        <x-btn-submit class="btn btn-primary">Pay now</x-btn-submit>
        <x-btn-submit class="bk-doc-btn" :disabled="! $hasEmail" title="…">Email</x-btn-submit>

    `spinner => false` for buttons that already swap their own label.
--}}
<button type="submit" {{ $attributes }} data-busy-submit @disabled($disabled)>
    @if ($spinner)<span class="bs-spinner" aria-hidden="true"></span>@endif{{ $slot }}
</button>
<x-busy-ui />
