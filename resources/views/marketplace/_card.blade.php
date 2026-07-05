@php
    $localeKey = app()->getLocale() === 'ms' ? 'title_bm' : 'title_en';
    $title = $listing->{$localeKey} ?: $listing->title_bm ?: $listing->title_en;
    $cover = $listing->cover_kind ?? 'beach';
    $startsFrom = $listing->base_price_min;
    $rating = $listing->rating_avg ? number_format((float) $listing->rating_avg, 1) : null;
@endphp
<a href="{{ route('marketplace.show', $listing) }}" class="bp-card">
    <div class="bp-card-cover" data-cover="{{ $cover }}">
        @if ($listing->hero_photo_path)
            <img src="{{ Storage::url($listing->hero_photo_path) }}" alt="{{ $title }}" loading="lazy">
        @endif
        <span class="bp-card-cover-tag">
            {{ $listing->house_type === 'per_room' ? '🚪 '.__('Room') : '🏠 '.__('Whole house') }}
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
            @if ($rating)<span class="bp-card-rating">★ {{ $rating }}</span>@endif
        </div>
        <div class="bp-card-loc">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            {{ $listing->city }}{{ $listing->state ? ', '.$listing->state : '' }}
        </div>
        <div class="bp-card-meta">
            @if ($listing->rooms_count){{ $listing->rooms_count }} {{ __('rooms') }}@endif
            @if ($listing->max_guests) · {{ __('sleeps') }} {{ $listing->max_guests }}@endif
        </div>
        @if ($startsFrom)
            <div class="bp-card-rate">
                {{ __('From') }} RM {{ number_format((float) $startsFrom, 0) }}
                <span class="per">/ {{ __('night') }}</span>
            </div>
        @endif
    </div>
</a>
