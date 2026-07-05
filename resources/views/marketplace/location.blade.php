@php
    use Illuminate\Support\Str;
    $isBM = app()->getLocale() === 'ms';
    $h1 = $isBM ? "Homestay di {$locationName}" : "Homestays in {$locationName}";
    $intro = $isBM
        ? "Pilihan homestay keluarga di {$locationName} — tempah terus dengan tuan rumah, tanpa orang tengah dan tanpa komisen."
        : "Family-run homestays in {$locationName} — booked directly with the host, no middleman and no commission.";
    $metaDescription = $isBM
        ? "Tempah homestay di {$locationName} secara terus, tanpa komisen. {$total} pilihan homestay keluarga di Tempahlah."
        : "Book homestays in {$locationName} directly, no commission. {$total} family-run stays on Tempahlah.";
    $canonical = $townName
        ? route('marketplace.location.town', [$stateSlug, Str::slug($townName)])
        : route('marketplace.location.state', $stateSlug);

    // JSON-LD: breadcrumb + item list of listings.
    $crumbs = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $stateName, 'item' => route('marketplace.location.state', $stateSlug)],
    ];
    if ($townName) {
        $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $townName, 'item' => $canonical];
    }
    $items = [];
    foreach ($listings as $i => $l) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => route('marketplace.show', $l), 'name' => $l->title_bm];
    }
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => array_filter([
            ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs],
            $items ? ['@type' => 'ItemList', 'name' => $h1, 'itemListElement' => $items] : null,
        ]),
    ];
@endphp
@extends('layouts.booking-public', ['title' => $h1.' · '.config('app.name'), 'metaDescription' => $metaDescription, 'canonical' => $canonical, 'ogTitle' => $h1])

@push('head')
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<main>
    <section class="bp-hero" style="text-align:left; padding-bottom:8px;">
        <nav aria-label="Breadcrumb" style="font-size:12.5px; color:var(--ink-3); margin-bottom:12px;">
            <a href="{{ route('marketplace.search') }}" style="color:var(--ink-3); text-decoration:none;">{{ __('Homestays') }}</a>
            <span> › </span>
            <a href="{{ route('marketplace.location.state', $stateSlug) }}" style="color:var(--ink-3); text-decoration:none;">{{ $stateName }}</a>
            @if ($townName)<span> › </span><span style="color:var(--ink-2);">{{ $townName }}</span>@endif
        </nav>
        <h1 class="bp-hero-title" style="font-size:34px;">{{ $h1 }}</h1>
        <p class="bp-hero-sub">{{ $intro }}</p>
        <a href="{{ route('marketplace.search', ['state' => $stateName]) }}" class="btn btn-primary" style="text-decoration:none; margin-top:4px;">
            {{ $isBM ? 'Cari & tapis semua homestay' : 'Search & filter all homestays' }} →
        </a>
    </section>

    @if ($towns->isNotEmpty())
        <section class="bp-filter-row" style="flex-wrap:wrap; gap:8px;">
            @foreach ($towns as $t)
                <a href="{{ route('marketplace.location.town', [$stateSlug, Str::slug($t)]) }}"
                   class="bp-filter-pill" data-active="{{ $townName === $t ? 'true' : 'false' }}">{{ $t }}</a>
            @endforeach
        </section>
    @endif

    <section>
        @if ($listings->total())
            <div style="max-width:1200px; margin:0 auto; padding:4px 32px 12px; font-size:13px; color:var(--ink-3);">
                {{ trans_choice('{1} :count homestay in :loc|[2,*] :count homestays in :loc', $listings->total(), ['count' => $listings->total(), 'loc' => $locationName]) }}
            </div>
        @endif

        <div class="bp-grid">
            @forelse ($listings as $listing)
                @include('marketplace._card', ['listing' => $listing])
            @empty
                <div style="grid-column:1/-1; text-align:center; padding:56px 24px;">
                    <div style="font-size:34px; margin-bottom:10px;">🏡</div>
                    <div style="font-family:var(--font-display); font-size:22px; margin-bottom:6px;">
                        {{ $isBM ? "Belum ada homestay di {$locationName}" : "No homestays in {$locationName} yet" }}
                    </div>
                    <p style="color:var(--ink-3); font-size:13.5px; margin:0 auto 18px; max-width:420px;">
                        {{ $isBM ? 'Jadi yang pertama menyambut tetamu di sini.' : 'Be the first to welcome guests here.' }}
                    </p>
                    <a href="{{ route('hosts') }}" class="btn btn-primary">{{ __('List your homestay here') }} →</a>
                </div>
            @endforelse
        </div>

        <div style="max-width:1200px; margin:0 auto; padding:0 32px 24px;">
            {{ $listings->links() }}
        </div>
    </section>
</main>
@endsection
