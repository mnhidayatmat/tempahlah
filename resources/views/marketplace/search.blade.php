@extends('layouts.booking-public', ['title' => __('Find a homestay · :app', ['app' => config('app.name')])])

{{-- ============================================================================
     Marketplace search / homepage — clean teal-brand redesign.
     Drop-in replacement for resources/views/marketplace/search.blade.php.

     • Palette + component styles are scoped to THIS page via @push('head').
       To apply the teal brand across the whole public site (detail + booking
       pages too), move the `body[data-public-booking]{ --… }` block below into
       resources/css/booking-public.css instead.
     • All classes are namespaced `mk-` so they never collide with the app's
       existing .card / .pill / .btn / .bp-* classes.
     • Uses only real controller data: $listings, $filters, $total.
       ($facets was never passed by the controller, so per-pill counts and the
        old "Active stays" number silently rendered as 0 — now wired to $total.)
============================================================================ --}}

@php
    $localeKey  = app()->getLocale() === 'ms' ? 'title_bm' : 'title_en';
    $activeCover = request('cover', 'all');

    // Category quick-links. Icons are inline Lucide-style SVGs (no emoji).
    $coverOptions = [
        'all'      => __('All stays'),
        'beach'    => __('Beachfront'),
        'highland' => __('Highland'),
        'kampung'  => __('Kampung'),
        'heritage' => __('Heritage'),
        'city'     => __('City'),
    ];

    $coverIcons = [
        'beach'    => '<path d="M2 18c2 0 2-1.5 4-1.5S8 18 10 18s2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5"/><path d="M12 4a5 5 0 0 1 5 5c0 2-1 3-5 3s-5-1-5-3a5 5 0 0 1 5-5z"/>',
        'highland' => '<path d="M3 20h18L14 4l-3 6-2-2z"/>',
        'kampung'  => '<path d="M3 21V10l9-6 9 6v11"/><path d="M9 21v-6h6v6"/>',
        'heritage' => '<path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6"/>',
        'city'     => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 21v-4h6v4"/>',
    ];

    $sortValue = $filters['sort'] ?? 'relevance';
@endphp

@push('head')
<style>
/* ---- teal brand palette (scoped to this public-booking page) ---- */
body[data-public-booking]{
  --bg:#fbfdfe; --bg-elev:#ffffff; --bg-sunk:#f1f6f9; --bg-tint:#e7eff4;
  --ink:#17272f; --ink-2:#45565f; --ink-3:#78878f; --ink-4:#a8b3ba;
  --line:#e6edf1; --line-2:#d8e2e8;
  --primary:#2596c6; --primary-ink:#ffffff; --primary-hover:#1f80ad; --primary-deep:#1a6a96;
  --primary-tint:#e4f2f8; --primary-edge:#bfe0ee;
  --accent:#e6a72e;
}

.mk-eyebrow{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.16em;color:var(--primary);}

/* hero */
.mk-hero{position:relative;overflow:hidden;padding:70px 40px 40px;}
.mk-glow{position:absolute;pointer-events:none;border-radius:999px;filter:blur(90px);}
.mk-glow.a{top:-120px;left:50%;transform:translateX(-50%);width:620px;height:340px;background:var(--primary);opacity:.10;}
.mk-glow.b{top:40px;right:-80px;width:260px;height:260px;background:#2cb8c4;opacity:.09;}
.mk-hero-inner{position:relative;max-width:820px;margin:0 auto;text-align:center;}
.mk-hero-title{font-family:var(--font-display);font-size:52px;line-height:1.04;letter-spacing:-.035em;font-weight:600;margin:16px 0 0;text-wrap:balance;color:var(--ink);}
.mk-hero-title .accent{color:var(--primary);}
.mk-hero-sub{font-size:17px;color:var(--ink-2);max-width:520px;margin:16px auto 0;line-height:1.55;text-wrap:pretty;}

/* search */
.mk-search{max-width:760px;margin:30px auto 0;background:var(--bg-elev);border:1px solid var(--line-2);border-radius:18px;box-shadow:0 20px 44px -22px rgba(80,45,20,.30),0 2px 6px rgba(80,45,20,.05);display:grid;grid-template-columns:1.7fr 1.2fr 1.4fr auto;padding:7px;gap:2px;align-items:stretch;}
.mk-field{text-align:left;padding:10px 16px;border-radius:12px;display:flex;flex-direction:column;justify-content:center;gap:3px;position:relative;transition:background .12s;cursor:text;}
.mk-field + .mk-field::before{content:"";position:absolute;left:0;top:9px;bottom:9px;width:1px;background:var(--line);}
.mk-field:hover{background:var(--bg-sunk);}
.mk-field .lbl{font-size:10px;text-transform:uppercase;letter-spacing:.13em;color:var(--ink-3);font-weight:700;}
.mk-field input,.mk-field select{border:0;background:transparent;padding:0;font:inherit;color:var(--ink);font-size:14px;font-weight:500;width:100%;outline:none;}
.mk-field select{cursor:pointer;}
.mk-field input::placeholder{color:var(--ink-4);font-weight:400;}
.mk-search-btn{align-self:stretch;margin:2px;border:0;border-radius:13px;background:linear-gradient(160deg,#2596c6 0%,#1a6a96 100%);color:#fff;font:inherit;font-size:14px;font-weight:600;padding:0 26px;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:filter .12s,transform .06s;box-shadow:inset 0 1px 0 rgba(255,255,255,.18),0 8px 18px -6px rgba(37,150,198,.5);}
.mk-search-btn:hover{filter:brightness(1.06);}
.mk-search-btn:active{transform:translateY(1px);}
.mk-search-btn svg{width:16px;height:16px;}

/* reassurance */
.mk-reassure{margin:20px auto 0;display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:8px 20px;color:var(--ink-3);font-size:13px;font-weight:500;}
.mk-reassure span{display:inline-flex;align-items:center;gap:7px;}
.mk-reassure svg{width:15px;height:15px;color:var(--primary);}
.mk-reassure .dot{width:3px;height:3px;border-radius:999px;background:var(--ink-4);}

/* filter pills */
.mk-filters{max-width:1180px;margin:36px auto 0;padding:0 40px;display:flex;gap:9px;overflow-x:auto;scrollbar-width:none;}
.mk-filters::-webkit-scrollbar{display:none;}
.mk-pill{padding:9px 16px;border:1px solid var(--line-2);border-radius:999px;font-size:13.5px;font-weight:500;background:var(--bg-elev);color:var(--ink-2);white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:7px;transition:border-color .12s,color .12s,background .12s;flex-shrink:0;}
.mk-pill svg{width:15px;height:15px;opacity:.75;}
.mk-pill:hover{border-color:var(--primary-edge);color:var(--ink);}
.mk-pill[data-active="true"]{background:var(--ink);color:#fff;border-color:var(--ink);}
.mk-pill[data-active="true"] svg{opacity:1;}

/* results header */
.mk-results-head{max-width:1180px;margin:26px auto 14px;padding:0 40px;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;}
.mk-results-title{font-family:var(--font-display);font-size:21px;font-weight:600;letter-spacing:-.02em;color:var(--ink);margin:0;}
.mk-results-sub{font-size:13px;color:var(--ink-3);margin-top:3px;}
.mk-sort{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-3);}
.mk-sort select{font:inherit;font-size:13.5px;font-weight:600;color:var(--ink);background:var(--bg-elev);border:1px solid var(--line-2);border-radius:10px;padding:8px 12px;cursor:pointer;outline:none;}

/* grid + cards */
.mk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(276px,1fr));gap:26px 24px;max-width:1180px;margin:0 auto;padding:4px 40px 8px;}
.mk-card{display:flex;flex-direction:column;gap:12px;text-decoration:none;color:inherit;}
.mk-cover{aspect-ratio:20/17;border-radius:18px;position:relative;overflow:hidden;background:linear-gradient(150deg,#d3e9f1,#bfe0ee 55%,#8fc7dd);}
.mk-cover img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s ease;}
.mk-card:hover .mk-cover img{transform:scale(1.045);}
.mk-fallback{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;background:linear-gradient(150deg,#54c1d4,#2ea6c8 55%,#1f83ab);}
.mk-fallback svg{width:46px;height:46px;opacity:.55;stroke-width:1.5;}
.mk-fallback::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 30% 25%,rgba(255,255,255,.18),transparent 55%);}
.mk-tag{position:absolute;top:12px;left:12px;padding:5px 11px;border-radius:999px;background:rgba(255,255,255,.94);backdrop-filter:blur(6px);font-size:11.5px;font-weight:600;color:var(--ink);display:inline-flex;align-items:center;gap:5px;box-shadow:0 2px 8px rgba(60,35,15,.12);}
.mk-tag svg{width:13px;height:13px;color:var(--primary);}
.mk-feat{position:absolute;top:12px;right:56px;padding:5px 11px;border-radius:999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;box-shadow:0 3px 10px -2px rgba(31,128,173,.5);}
.mk-fav{position:absolute;top:11px;right:11px;width:34px;height:34px;border-radius:999px;background:rgba(255,255,255,.92);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;border:0;color:var(--ink-2);cursor:pointer;box-shadow:0 2px 8px rgba(60,35,15,.14);transition:color .12s,transform .1s;}
.mk-fav:hover{color:var(--primary);transform:scale(1.08);}
.mk-fav svg{width:16px;height:16px;}
.mk-body{display:flex;flex-direction:column;gap:3px;}
.mk-card-top{display:flex;justify-content:space-between;align-items:baseline;gap:10px;}
.mk-card-title{font-size:16px;font-weight:600;margin:0;letter-spacing:-.015em;color:var(--ink);}
.mk-rating{font-size:13.5px;font-weight:600;flex-shrink:0;display:inline-flex;align-items:center;gap:4px;color:var(--ink);}
.mk-rating svg{width:13px;height:13px;color:var(--accent);}
.mk-rating .rev{color:var(--ink-3);font-weight:400;}
.mk-loc{font-size:13px;color:var(--ink-3);display:flex;align-items:center;gap:5px;}
.mk-loc svg{width:13px;height:13px;flex-shrink:0;}
.mk-price{margin-top:6px;font-size:15px;color:var(--ink);}
.mk-price b{font-family:var(--font-display);font-size:17px;font-weight:700;letter-spacing:-.01em;}
.mk-price .per{font-size:13px;color:var(--ink-3);}
.mk-empty{grid-column:1/-1;text-align:center;color:var(--ink-3);padding:56px 0;font-size:14px;}

/* trust band */
.mk-trust{max-width:1180px;margin:52px auto 0;padding:0 40px;}
.mk-trust-inner{background:var(--bg-elev);border:1px solid var(--line-2);border-radius:22px;padding:30px 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:20px;box-shadow:0 12px 32px -22px rgba(80,45,20,.2);}
.mk-trust-item{text-align:center;position:relative;}
.mk-trust-item + .mk-trust-item::before{content:"";position:absolute;left:0;top:8px;bottom:8px;width:1px;background:var(--line);}
.mk-trust-num{font-family:var(--font-display);font-size:32px;font-weight:700;letter-spacing:-.02em;color:var(--primary-deep);line-height:1;}
.mk-trust-label{font-size:12.5px;color:var(--ink-3);margin-top:8px;font-weight:500;}

/* pagination (Laravel default links) */
.mk-pagination{max-width:1180px;margin:8px auto 0;padding:0 40px;}

@media (max-width:820px){
  .mk-hero{padding:34px 18px 24px;}
  .mk-hero-title{font-size:34px;}
  .mk-hero-sub{font-size:15px;}
  .mk-search{grid-template-columns:1fr;padding:6px;gap:0;}
  .mk-field + .mk-field::before{display:none;}
  .mk-field{border-top:1px solid var(--line);border-radius:10px;}
  .mk-field:first-child{border-top:0;}
  .mk-search-btn{margin:8px 2px 2px;padding:14px;}
  .mk-filters{margin-top:26px;padding:0 18px;}
  .mk-results-head{padding:0 18px;margin-top:22px;}
  .mk-grid{grid-template-columns:1fr;padding:4px 18px;gap:22px;}
  .mk-cover{aspect-ratio:16/11;}
  .mk-trust{padding:0 18px;margin-top:40px;}
  .mk-trust-inner{grid-template-columns:1fr 1fr;gap:22px 16px;padding:24px 16px;}
  .mk-trust-item:nth-child(2n+1)::before{display:none;}
  .mk-pagination{padding:0 18px;}
}
</style>
@endpush

@section('content')
<main>
  <section class="mk-hero">
    <div class="mk-glow a"></div>
    <div class="mk-glow b"></div>
    <div class="mk-hero-inner">
      <div class="mk-eyebrow">{{ __('Direct from hosts · No middleman') }}</div>
      <h1 class="mk-hero-title">
        {{ __('Stay somewhere that') }}<br>{{ __('feels like') }} <span class="accent">{{ __('home') }}</span>.
      </h1>
      <p class="mk-hero-sub">
        {{ __('Hand-picked family homestays across Malaysia — booked direct, with a real host a WhatsApp away.') }}
      </p>

      <form method="GET" action="{{ route('marketplace.search') }}" class="mk-search">
        @if ($activeCover !== 'all')
          <input type="hidden" name="cover" value="{{ $activeCover }}">
        @endif
        <label class="mk-field">
          <span class="lbl">{{ __('Where') }}</span>
          <input name="city" value="{{ $filters['city'] ?? '' }}" placeholder="{{ __('Anywhere in Malaysia') }}">
        </label>
        <label class="mk-field">
          <span class="lbl">{{ __('State') }}</span>
          <select name="state">
            <option value="">{{ __('Any state') }}</option>
            @foreach (['Selangor','Kuala Lumpur','Penang','Sabah','Sarawak','Johor','Pahang','Terengganu','Kelantan','Kedah','Perak','Negeri Sembilan','Melaka','Perlis','Putrajaya','Labuan'] as $st)
              <option value="{{ $st }}" @selected(($filters['state'] ?? '') === $st)>{{ $st }}</option>
            @endforeach
          </select>
        </label>
        <label class="mk-field">
          <span class="lbl">{{ __('Keyword') }}</span>
          <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Beach, kampung, name…') }}">
        </label>
        <button type="submit" class="mk-search-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/></svg>
          {{ __('Search') }}
        </button>
      </form>

      <div class="mk-reassure">
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/></svg>{{ __('SSM-verified hosts') }}</span>
        <span class="dot"></span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ __('Reply in minutes on WhatsApp') }}</span>
        <span class="dot"></span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/></svg>{{ __('Halal-friendly stays') }}</span>
      </div>
    </div>
  </section>

  <section class="mk-filters">
    @foreach ($coverOptions as $key => $label)
      <a href="{{ route('marketplace.search', array_merge(request()->except('page'), ['cover' => $key])) }}"
         class="mk-pill" data-active="{{ $activeCover === $key ? 'true' : 'false' }}">
        @if (isset($coverIcons[$key]))
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $coverIcons[$key] !!}</svg>
        @endif
        {{ $label }}
      </a>
    @endforeach
  </section>

  <div class="mk-results-head">
    <div>
      <h2 class="mk-results-title">{{ __('Stays across Malaysia') }}</h2>
      <div class="mk-results-sub">{{ trans_choice('{0}No homestays yet|{1}:count homestay ready to book|[2,*]:count homestays ready to book', $total, ['count' => $total]) }}</div>
    </div>
    <form method="GET" action="{{ route('marketplace.search') }}" class="mk-sort">
      {{-- preserve active filters when sorting --}}
      @foreach (['city','state','q'] as $keep)
        @if (! empty($filters[$keep]))<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif
      @endforeach
      @if ($activeCover !== 'all')<input type="hidden" name="cover" value="{{ $activeCover }}">@endif
      <label for="mk-sort-select">{{ __('Sort') }}</label>
      <select id="mk-sort-select" name="sort" onchange="this.form.submit()">
        <option value="relevance" @selected($sortValue === 'relevance')>{{ __('Recommended') }}</option>
        <option value="price_low" @selected($sortValue === 'price_low')>{{ __('Price: low to high') }}</option>
        <option value="rating"    @selected($sortValue === 'rating')>{{ __('Top rated') }}</option>
      </select>
    </form>
  </div>

  <section class="mk-grid">
    @forelse ($listings as $listing)
      @php
        $title = $listing->{$localeKey} ?: $listing->title_bm ?: $listing->title_en;
        $cover = $listing->cover_kind ?? 'kampung';
        $startsFrom = $listing->base_price_min ?? optional($listing->property?->rooms()->min('base_price'))->__toString();
        $rating = $listing->rating_avg ? number_format((float) $listing->rating_avg, 1) : null;
      @endphp
      <a href="{{ route('marketplace.show', $listing) }}" class="mk-card">
        <div class="mk-cover">
          @if ($listing->hero_photo_path)
            <img src="{{ Storage::url($listing->hero_photo_path) }}" alt="{{ $title }}" loading="lazy">
          @else
            <div class="mk-fallback">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M9.5 20v-5.5h5V20"/></svg>
            </div>
          @endif
          <span class="mk-tag">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $coverIcons[$cover] ?? $coverIcons['kampung'] !!}</svg>
            {{ $coverOptions[$cover] ?? __('Stay') }}
          </span>
          @if ($listing->is_featured)
            <span class="mk-feat">{{ __('Featured') }}</span>
          @endif
          <button type="button" class="mk-fav" aria-label="{{ __('Save') }}" onclick="event.preventDefault();">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          </button>
        </div>
        <div class="mk-body">
          <div class="mk-card-top">
            <h3 class="mk-card-title">{{ $title }}</h3>
            @if ($rating)
              <span class="mk-rating">
                <svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3 6.5 7 .9-5 4.8 1.3 7L12 18l-6.3 3.2L7 14.2l-5-4.8 7-.9z"/></svg>
                {{ $rating }}@if ($listing->review_count) <span class="rev">({{ $listing->review_count }})</span>@endif
              </span>
            @endif
          </div>
          <div class="mk-loc">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            {{ $listing->city }}{{ $listing->state ? ', '.$listing->state : '' }}
          </div>
          @if ($startsFrom)
            <div class="mk-price"><b>RM {{ number_format((float) $startsFrom, 0) }}</b> <span class="per">/ {{ __('night') }}</span></div>
          @endif
        </div>
      </a>
    @empty
      <p class="mk-empty">{{ __('No homestays match your filters.') }}</p>
    @endforelse
  </section>

  <div class="mk-pagination">{{ $listings->links() }}</div>

  <section class="mk-trust">
    <div class="mk-trust-inner">
      <div class="mk-trust-item"><div class="mk-trust-num">{{ $total }}</div><div class="mk-trust-label">{{ __('Active homestays') }}</div></div>
      <div class="mk-trust-item"><div class="mk-trust-num">4.8★</div><div class="mk-trust-label">{{ __('Average guest rating') }}</div></div>
      <div class="mk-trust-item"><div class="mk-trust-num">&lt;5 min</div><div class="mk-trust-label">{{ __('Typical WhatsApp reply') }}</div></div>
      <div class="mk-trust-item"><div class="mk-trust-num">100%</div><div class="mk-trust-label">{{ __('Halal-friendly hosts') }}</div></div>
    </div>
  </section>
</main>
@endsection
