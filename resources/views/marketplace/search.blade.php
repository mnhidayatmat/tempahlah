@extends('layouts.booking-public', ['title' => __('Find a homestay · :app', ['app' => config('app.name')])])

@section('content')
@php
    $localeKey = app()->getLocale() === 'ms' ? 'title_bm' : 'title_en';
    $activeCover = request('cover', 'all');
    $coverOptions = [
        'all'      => ['label' => __('All stays'),    'count' => $facets['all'] ?? 0],
        'beach'    => ['label' => '🌊 '.__('Beachfront'), 'count' => $facets['beach'] ?? 0],
        'highland' => ['label' => '🌲 '.__('Highland'),   'count' => $facets['highland'] ?? 0],
        'kampung'  => ['label' => '🌾 '.__('Kampung'),    'count' => $facets['kampung'] ?? 0],
        'heritage' => ['label' => '🏛️ '.__('Heritage'),  'count' => $facets['heritage'] ?? 0],
    ];
@endphp

<main>
    <section class="bp-hero">
        <div class="kicker" style="margin-bottom:14px;">{{ __('Tempahlah · Direct from hosts') }}</div>
        <h1 class="bp-hero-title">
            {{ __('Stay somewhere') }}<br>{{ __('that feels like') }}
            <span style="color:var(--primary);">{{ __('home') }}</span>.
        </h1>
        <p class="bp-hero-sub">
            {{ __('Hand-picked homestays across Malaysia — booked direct, with a real human host on WhatsApp.') }}
        </p>

        <form method="GET" action="{{ route('marketplace.search') }}" class="bp-search">
            <label class="bp-search-field">
                <div class="lbl">{{ __('Where') }}</div>
                <input name="city" value="{{ $filters['city'] ?? '' }}" placeholder="{{ __('Anywhere in Malaysia') }}">
            </label>
            <label class="bp-search-field">
                <div class="lbl">{{ __('State') }}</div>
                <select name="state">
                    <option value="">{{ __('Any state') }}</option>
                    @foreach (['Selangor','Kuala Lumpur','Penang','Sabah','Sarawak','Johor','Pahang','Terengganu','Kelantan','Kedah','Perak','Negeri Sembilan','Melaka','Perlis','Putrajaya','Labuan'] as $st)
                        <option value="{{ $st }}" @selected(($filters['state'] ?? '') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </label>
            <label class="bp-search-field">
                <div class="lbl">{{ __('Search') }}</div>
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Name, area, keyword') }}">
            </label>
            <button type="submit" class="btn btn-primary btn-lg" style="border-radius:10px; padding:0 22px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/></svg>
                {{ __('Search') }}
            </button>
        </form>
    </section>

    <section class="bp-filter-row">
        @foreach ($coverOptions as $key => $opt)
            <a href="{{ route('marketplace.search', array_merge(request()->query(), ['cover' => $key])) }}"
               class="bp-filter-pill"
               data-active="{{ $activeCover === $key ? 'true' : 'false' }}">
                {{ $opt['label'] }}
                @if (! empty($opt['count']))
                    <span class="count">{{ $opt['count'] }}</span>
                @endif
            </a>
        @endforeach
    </section>

    <section>
        <div class="bp-grid">
            @forelse ($listings as $listing)
                @php
                    $title = $listing->{$localeKey} ?: $listing->title_bm ?: $listing->title_en;
                    $cover = $listing->cover_kind ?? 'beach';
                    $startsFrom = $listing->base_price_min ?? optional($listing->property?->rooms()->min('base_price'))->__toString();
                    $rating = $listing->rating_avg ? number_format((float) $listing->rating_avg, 1) : null;
                @endphp
                <a href="{{ route('marketplace.show', $listing) }}" class="bp-card">
                    <div class="bp-card-cover" data-cover="{{ $cover }}">
                        @if ($listing->hero_photo_path)
                            <img src="{{ Storage::url($listing->hero_photo_path) }}" alt="">
                        @endif
                        <span class="bp-card-cover-tag">
                            @switch($cover)
                                @case('beach') 🌊 {{ __('Beachfront') }} @break
                                @case('highland') 🌲 {{ __('Highland') }} @break
                                @case('kampung') 🌾 {{ __('Kampung') }} @break
                                @case('heritage') 🏛️ {{ __('Heritage') }} @break
                                @default 🏠 {{ __('Stay') }}
                            @endswitch
                        </span>
                        @if ($listing->is_featured)
                            <span class="bp-card-cover-tag" style="left:auto; right:56px; background:var(--primary); color:white;">{{ __('Featured') }}</span>
                        @endif
                        <button type="button" class="bp-card-cover-fav" aria-label="{{ __('Save') }}" onclick="event.preventDefault();">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        </button>
                    </div>
                    <div class="bp-card-body">
                        <div class="bp-card-title-row">
                            <h3 class="bp-card-title">{{ $title }}</h3>
                            @if ($rating)
                                <span class="bp-card-rating">★ {{ $rating }}</span>
                            @endif
                        </div>
                        <div class="bp-card-loc">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            {{ $listing->city }}{{ $listing->state ? ', '.$listing->state : '' }}
                        </div>
                        @if ($listing->review_count)
                            <div class="bp-card-meta">{{ $listing->review_count }} {{ __('reviews') }}</div>
                        @endif
                        @if ($startsFrom)
                            <div class="bp-card-rate">
                                {{ __('From') }} RM {{ number_format((float) $startsFrom, 0) }}
                                <span class="per">/ {{ __('night') }}</span>
                            </div>
                        @endif
                    </div>
                </a>
            @empty
                <p style="grid-column:1/-1; text-align:center; color:var(--ink-3); padding:48px 0;">
                    {{ __('No homestays match your filters.') }}
                </p>
            @endforelse
        </div>

        <div style="max-width:1200px; margin:0 auto; padding:0 32px 24px;">
            {{ $listings->links() }}
        </div>
    </section>

    <section class="bp-trust">
        <div class="bp-trust-grid">
            <div>
                <div class="bp-trust-num">{{ $facets['all'] ?? 0 }}</div>
                <div class="bp-trust-label">{{ __('Active stays') }}</div>
            </div>
            <div>
                <div class="bp-trust-num">4.8 ★</div>
                <div class="bp-trust-label">{{ __('Average rating') }}</div>
            </div>
            <div>
                <div class="bp-trust-num">&lt; 5 min</div>
                <div class="bp-trust-label">{{ __('WhatsApp reply') }}</div>
            </div>
            <div>
                <div class="bp-trust-num">100%</div>
                <div class="bp-trust-label">{{ __('Halal-friendly') }}</div>
            </div>
        </div>
    </section>
</main>
@endsection
