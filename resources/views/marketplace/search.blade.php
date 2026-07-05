@extends('layouts.booking-public', ['title' => __('Find a homestay · :app', ['app' => config('app.name')])])

@section('content')
@php
    $isBM = app()->getLocale() === 'ms';
    $localeKey = $isBM ? 'title_bm' : 'title_en';
    $amLabel = $isBM ? 'label_bm' : 'label_en';
    $selectedAmenities = $filters['amenities'] ?? [];
    $sort = $filters['sort'] ?? 'relevance';
    $states = ['Selangor','Kuala Lumpur','Penang','Sabah','Sarawak','Johor','Pahang','Terengganu','Kelantan','Kedah','Perak','Negeri Sembilan','Melaka','Perlis','Putrajaya','Labuan'];
    $hasFilters = collect($filters)->except('sort')->filter(fn ($v) => filled($v) && $v !== [])->isNotEmpty();
    $ctrl = 'appearance:none; padding:9px 12px; border:1px solid var(--line); border-radius:10px; background:var(--bg-elev); color:var(--ink); font-family:inherit; font-size:13px; cursor:pointer;';
@endphp

{{-- One GET form wraps the hero search + the filter bar so a single submit
     applies everything. display:contents keeps the existing layout intact. --}}
<form method="GET" action="{{ route('marketplace.search') }}" style="display:contents">
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

        <div class="bp-search">
            <label class="bp-search-field">
                <div class="lbl">{{ __('Where') }}</div>
                <input name="city" value="{{ $filters['city'] ?? '' }}" placeholder="{{ __('Town or area') }}">
            </label>
            <label class="bp-search-field">
                <div class="lbl">{{ __('State') }}</div>
                <select name="state">
                    <option value="">{{ __('Any state') }}</option>
                    @foreach ($states as $st)
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
                {{ __('Search homestays') }}
            </button>
        </div>
    </section>

    {{-- Filter bar (≤5 visible controls + a "More" amenities expander) --}}
    <section class="bp-filter-row" style="flex-wrap:wrap; gap:10px; align-items:center;">
        <select name="house_type" style="{{ $ctrl }}" onchange="this.form.submit()">
            <option value="">{{ __('Any type') }}</option>
            <option value="whole_house" @selected(($filters['house_type'] ?? '') === 'whole_house')>{{ __('Whole house') }}</option>
            <option value="per_room" @selected(($filters['house_type'] ?? '') === 'per_room')>{{ __('Private room') }}</option>
        </select>

        <select name="min_rooms" style="{{ $ctrl }}" onchange="this.form.submit()">
            <option value="">{{ __('Any rooms') }}</option>
            @foreach ([1,2,3,4,5] as $n)
                <option value="{{ $n }}" @selected((string)($filters['min_rooms'] ?? '') === (string)$n)>{{ $n }}{{ $n === 5 ? '+' : '' }} {{ __('rooms') }}</option>
            @endforeach
        </select>

        <select name="guests" style="{{ $ctrl }}" onchange="this.form.submit()">
            <option value="">{{ __('Any guests') }}</option>
            @foreach ([2,4,6,8,10,15,20] as $n)
                <option value="{{ $n }}" @selected((string)($filters['guests'] ?? '') === (string)$n)>{{ $n }}+ {{ __('guests') }}</option>
            @endforeach
        </select>

        <div style="display:inline-flex; align-items:center; gap:4px;">
            <input type="number" name="min_price" min="0" step="10" value="{{ $filters['min_price'] ?? '' }}" placeholder="{{ __('Min RM') }}" style="{{ $ctrl }} width:92px;">
            <span style="color:var(--ink-4);">–</span>
            <input type="number" name="max_price" min="0" step="10" value="{{ $filters['max_price'] ?? '' }}" placeholder="{{ __('Max RM') }}" style="{{ $ctrl }} width:92px;">
        </div>

        {{-- Amenities expander --}}
        <details style="position:relative;">
            <summary style="{{ $ctrl }} list-style:none; display:inline-flex; align-items:center; gap:6px;">
                {{ __('More filters') }}
                @if (count($selectedAmenities)) <span class="count" style="background:var(--primary); color:#fff; border-radius:999px; padding:0 6px; font-size:11px;">{{ count($selectedAmenities) }}</span> @endif
                ▾
            </summary>
            <div class="hauz-card" style="position:absolute; z-index:40; top:calc(100% + 6px); left:0; width:320px; max-height:340px; overflow:auto; padding:14px; box-shadow:0 12px 32px -8px rgba(20,20,30,.20);">
                <div class="kicker" style="margin-bottom:10px;">{{ __('Amenities') }}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    @foreach ($amenityList as $a)
                        <label style="display:flex; align-items:center; gap:7px; font-size:12.5px; cursor:pointer;">
                            <input type="checkbox" name="amenities[]" value="{{ $a->key }}" @checked(in_array($a->key, $selectedAmenities, true))>
                            <span>{{ $a->icon }} {{ $a->{$amLabel} }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="width:100%; margin-top:12px;">{{ __('Apply') }}</button>
            </div>
        </details>

        <div style="flex:1;"></div>

        @if ($hasFilters)
            <a href="{{ route('marketplace.search') }}" style="font-size:12.5px; color:var(--ink-3); text-decoration:none;">{{ __('Reset') }}</a>
        @endif

        <select name="sort" style="{{ $ctrl }}" onchange="this.form.submit()" title="{{ __('Sort') }}">
            <option value="relevance" @selected($sort === 'relevance')>{{ __('Relevance') }}</option>
            <option value="price_low" @selected($sort === 'price_low')>{{ __('Price: low to high') }}</option>
            <option value="rating" @selected($sort === 'rating')>{{ __('Top rated') }}</option>
        </select>
    </section>

    <section>
        @if ($listings->total())
            <div style="max-width:1200px; margin:0 auto; padding:4px 32px 12px; font-size:13px; color:var(--ink-3);">
                {{ trans_choice('{1} :count homestay|[2,*] :count homestays', $listings->total(), ['count' => $listings->total()]) }}
            </div>
        @endif

        <div class="bp-grid">
            @forelse ($listings as $listing)
                @php
                    $title = $listing->{$localeKey} ?: $listing->title_bm ?: $listing->title_en;
                    $cover = $listing->cover_kind ?? 'beach';
                    $startsFrom = $listing->base_price_min;
                    $rating = $listing->rating_avg ? number_format((float) $listing->rating_avg, 1) : null;
                @endphp
                <a href="{{ route('marketplace.show', $listing) }}" class="bp-card">
                    <div class="bp-card-cover" data-cover="{{ $cover }}">
                        @if ($listing->hero_photo_path)
                            <img src="{{ Storage::url($listing->hero_photo_path) }}" alt="" loading="lazy">
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
            @empty
                {{-- Graceful empty state — never a broken grid; feeds the supply side. --}}
                <div style="grid-column:1/-1; text-align:center; padding:56px 24px;">
                    <div style="font-size:34px; margin-bottom:10px;">🏡</div>
                    <div style="font-family:var(--font-display); font-size:22px; margin-bottom:6px;">
                        {{ $hasFilters ? __('No homestays match these filters') : __('No homestays listed here yet') }}
                    </div>
                    <p style="color:var(--ink-3); font-size:13.5px; margin:0 auto 18px; max-width:420px;">
                        {{ $hasFilters ? __('Try widening your dates, price or area — or reset the filters.') : __('Be the first to welcome guests in this area.') }}
                    </p>
                    <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                        @if ($hasFilters)
                            <a href="{{ route('marketplace.search') }}" class="btn">{{ __('Reset filters') }}</a>
                        @endif
                        <a href="{{ route('hosts') }}" class="btn btn-primary">{{ __('List your homestay here') }} →</a>
                    </div>
                </div>
            @endforelse
        </div>

        <div style="max-width:1200px; margin:0 auto; padding:0 32px 24px;">
            {{ $listings->links() }}
        </div>
    </section>

    <section class="bp-trust">
        <div class="bp-trust-grid">
            <div>
                <div class="bp-trust-num">{{ $total }}</div>
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
                <div class="bp-trust-num">0%</div>
                <div class="bp-trust-label">{{ __('Commission on direct') }}</div>
            </div>
        </div>
    </section>
</main>
</form>
@endsection
