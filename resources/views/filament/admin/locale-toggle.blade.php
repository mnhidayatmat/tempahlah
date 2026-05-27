{{-- BM/EN locale toggle injected into Filament topbar via panels::topbar.end --}}
@php $current = app()->getLocale(); @endphp
<div class="hms-locale-toggle" role="group" aria-label="{{ __('Language') }}">
    <a href="{{ route('locale.switch', 'ms') }}"
       class="hms-locale-toggle-pill {{ $current === 'ms' ? 'is-active' : '' }}"
       aria-pressed="{{ $current === 'ms' ? 'true' : 'false' }}">BM</a>
    <a href="{{ route('locale.switch', 'en') }}"
       class="hms-locale-toggle-pill {{ $current === 'en' ? 'is-active' : '' }}"
       aria-pressed="{{ $current === 'en' ? 'true' : 'false' }}">EN</a>
</div>
