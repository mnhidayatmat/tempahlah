@props(['spinner' => true])
{{--
    A link to a server-generated file (PDF, CSV). Shows a spinner from the click
    until the response actually starts arriving, and refuses a second click in
    between — a plain <a> gives no pressed state, so these reads as broken while
    DomPDF works.

        <x-btn-link class="btn btn-sm" :href="route('tenant.reports.export-pdf')">Export PDF</x-btn-link>

    The endpoint MUST pass its response through App\Support\Http\DownloadToken,
    otherwise the spinner just runs to its 30s bail-out.
--}}
<a {{ $attributes }} data-busy-link>
    @if ($spinner)<span class="bs-spinner" aria-hidden="true"></span>@endif{{ $slot }}
</a>
<x-busy-ui />
