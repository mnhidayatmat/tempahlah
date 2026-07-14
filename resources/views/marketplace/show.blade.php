@extends('layouts.booking-public', ['title' => $listing->title_bm.' · '.config('app.name')])

@section('content')
@php
    $localeKey = app()->getLocale() === 'ms' ? 'title_bm' : 'title_en';
    $title = $listing->{$localeKey} ?: $listing->title_bm ?: $listing->title_en;
    $description = $property->description_bm ?: $property->description_en ?: '';
    $rate = $rate > 0 ? (float) $rate : 200;
    $rating = $listing->rating_avg ? number_format((float) $listing->rating_avg, 1) : '4.9';
    $reviewCount = (int) ($listing->review_count ?? 0);
    $location = $listing->city.($listing->state ? ', '.$listing->state : '');
    $rules = is_array($property->house_rules)
        ? $property->house_rules
        : (is_string($property->house_rules) ? array_filter(array_map('trim', explode("\n", $property->house_rules))) : []);
    if (empty($rules)) {
        $rules = [
            __('No smoking indoors'),
            __('Quiet hours 11pm–7am'),
            __('Respect local customs'),
            __('Halal-only kitchen'),
        ];
    }
    $cancellation = $property->cancellation_policy ?: __('Free cancellation up to 7 days before check-in. 50% refund within 7 days. Non-refundable within 48h.');
    $hostName = $listing->tenant?->business_name ?? $listing->tenant?->name ?? __('Host');
    $hostInitial = mb_strtoupper(mb_substr($hostName, 0, 1));
    $facilityList = [
        ['key' => 'wifi',     'label' => __('Free Wi-Fi')],
        ['key' => 'ac',       'label' => __('Air-conditioning')],
        ['key' => 'kitchen',  'label' => __('Full kitchen')],
        ['key' => 'parking',  'label' => __('Free parking')],
        ['key' => 'pool',     'label' => __('Pool')],
        ['key' => 'tv',       'label' => __('Smart TV')],
        ['key' => 'washer',   'label' => __('Washing machine')],
        ['key' => 'halal',    'label' => __('Halal-friendly')],
    ];
@endphp

<main class="bp-detail" x-data="bookingDetail({
    rate: {{ (float) $rate }},
    sleeps: {{ (int) $sleeps }},
    defaultGuests: {{ (int) ($defaultGuests ?? 2) }},
    bookedDates: @js($bookedDates ?? []),
    contactPhone: @js($contactPhone),
    propertyName: @js($title),
    locale: @js(app()->getLocale() === 'ms' ? 'ms-MY' : 'en-MY')
})">

    {{-- Mobile-only banner (shown ≤640px) --}}
    <div class="bp-mobile-only">
        <a href="{{ route('marketplace.search') }}" class="bp-back-mobile" aria-label="{{ __('Back') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <button type="button" class="bp-share-mobile" aria-label="{{ __('Share') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/></svg>
        </button>
        <div class="bp-banner" data-cover="{{ $coverKind }}">
            @if ($listing->hero_photo_path)
                <img src="{{ Storage::url($listing->hero_photo_path) }}" alt="{{ $title }}">
            @endif
            <div class="bp-banner-bottom">
                <div class="bp-banner-name">{{ $title }}</div>
                <div class="bp-banner-meta">
                    <span class="bp-banner-rating">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15 9 22 9.3 16.5 14 18.5 21 12 17 5.5 21 7.5 14 2 9.3 9 9"/></svg>
                        {{ $rating }}
                    </span>
                    <span>{{ $roomCount }} {{ __('rooms') }} · {{ __('sleeps') }} {{ $sleeps }}</span>
                    <div class="bp-banner-rate">
                        <div class="bp-banner-rate-num">RM {{ number_format($rate, 0) }}</div>
                        <div class="bp-banner-rate-per">{{ __('per night') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Desktop breadcrumb + title row (hidden on mobile, banner takes over) --}}
    <a href="{{ route('marketplace.search') }}" class="bp-back" style="display:inline-flex;">
        ← {{ __('All stays') }}
    </a>

    <div class="bp-title-row">
        <h1 class="bp-title">{{ $title }}</h1>
        <div class="bp-title-meta">
            <span>★ <strong>{{ $rating }}</strong> · {{ $reviewCount }} {{ __('reviews') }}</span>
            <span class="dot">·</span>
            <span style="display:inline-flex; align-items:center; gap:4px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $location }}
            </span>
            <span class="dot">·</span>
            <span style="color:var(--primary); font-weight:500;">
                @switch($coverKind)
                    @case('beach') 🌊 {{ __('Beachfront') }} @break
                    @case('highland') 🌲 {{ __('Highland') }} @break
                    @case('kampung') 🌾 {{ __('Kampung') }} @break
                    @case('heritage') 🏛️ {{ __('Heritage') }} @break
                    @default 🏠 {{ __('Homestay') }}
                @endswitch
            </span>
        </div>
    </div>

    {{-- Desktop gallery (hidden on mobile) --}}
    @php
        // Full ordered photo list for the lightbox, hero first (deduped).
        $galleryUrls = $property->photos->map(fn ($p) => Storage::url($p->path));
        if ($listing->hero_photo_path) {
            $heroUrl = Storage::url($listing->hero_photo_path);
            $galleryUrls = collect([$heroUrl])->merge($galleryUrls->reject(fn ($u) => $u === $heroUrl));
        }
        $galleryUrls = $galleryUrls->values();
        $photos = $property->photos->take(5);
        $heroCellUrl = $listing->hero_photo_path
            ? Storage::url($listing->hero_photo_path)
            : ($photos->first() ? Storage::url($photos->first()->path) : null);
        $idxOf = function ($url) use ($galleryUrls) {
            $k = $galleryUrls->search($url);
            return $k === false ? 0 : $k;
        };
    @endphp

    <style>
        .bp-gallery-cell img, .bp-gallery-hero, .bp-gallery-show-all { cursor: zoom-in; }
        .bp-lightbox {
            position: fixed; inset: 0; z-index: 1000;
            background: rgba(0, 0, 0, .93);
            display: flex; align-items: center; justify-content: center; padding: 30px;
        }
        .bp-lb-img {
            max-width: 92vw; max-height: 88vh; object-fit: contain;
            border-radius: 6px; box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
            cursor: default; user-select: none;
        }
        .bp-lb-btn {
            position: absolute; background: rgba(255, 255, 255, .14); color: #fff;
            border: 0; border-radius: 999px; cursor: pointer; line-height: 1;
            display: flex; align-items: center; justify-content: center;
            -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); transition: background .15s;
        }
        .bp-lb-btn:hover { background: rgba(255, 255, 255, .3); }
        .bp-lb-close { top: 20px; right: 20px; width: 44px; height: 44px; font-size: 19px; }
        .bp-lb-prev, .bp-lb-next { top: 50%; transform: translateY(-50%); width: 52px; height: 52px; font-size: 28px; padding-bottom: 4px; }
        .bp-lb-prev { left: 18px; }
        .bp-lb-next { right: 18px; }
        .bp-lb-counter {
            position: absolute; bottom: 22px; left: 50%; transform: translateX(-50%);
            color: #fff; font-size: 13px; letter-spacing: .04em;
            background: rgba(0, 0, 0, .5); padding: 6px 14px; border-radius: 999px;
        }
        @media (max-width: 640px) {
            .bp-lb-prev, .bp-lb-next { width: 44px; height: 44px; font-size: 24px; }
            .bp-lb-close { width: 40px; height: 40px; }
        }
    </style>

    <div class="bp-gallery-wrap" x-data="{
            open: false,
            index: 0,
            photos: @js($galleryUrls),
            openAt(i) {
                if (!this.photos.length) return;
                this.index = Math.max(0, Math.min(i, this.photos.length - 1));
                this.open = true;
                document.body.style.overflow = 'hidden';
            },
            close() { this.open = false; document.body.style.overflow = ''; },
            next() { this.index = (this.index + 1) % this.photos.length; },
            prev() { this.index = (this.index - 1 + this.photos.length) % this.photos.length; }
        }">
        <div class="bp-gallery">
            <div class="bp-gallery-cell bp-gallery-hero" data-cover="{{ $coverKind }}"
                 @if ($heroCellUrl) @click="openAt({{ $idxOf($heroCellUrl) }})" @endif>
                @if ($heroCellUrl)
                    <img src="{{ $heroCellUrl }}" alt="">
                @endif
            </div>
            @for ($i = 1; $i <= 4; $i++)
                @php $cellPhoto = $photos->skip($i)->first(); $cellUrl = $cellPhoto ? Storage::url($cellPhoto->path) : null; @endphp
                <div class="bp-gallery-cell" style="filter: hue-rotate({{ $i * 8 }}deg) brightness({{ 0.92 + $i * 0.04 }});">
                    @if ($cellUrl)
                        <img src="{{ $cellUrl }}" alt="" @click="openAt({{ $idxOf($cellUrl) }})">
                    @endif
                    @if ($i === 4 && $property->photos->count() > 5)
                        <button class="bp-gallery-show-all" type="button" @click.stop="openAt(0)">{{ __('Show all :n photos', ['n' => $property->photos->count()]) }}</button>
                    @endif
                </div>
            @endfor
        </div>

        {{-- Full-screen photo viewer --}}
        <div class="bp-lightbox" x-show="open" x-cloak style="display:none;"
             x-transition.opacity
             @keydown.escape.window="close()"
             @keydown.arrow-right.window="open && next()"
             @keydown.arrow-left.window="open && prev()"
             @click.self="close()">
            <button type="button" class="bp-lb-btn bp-lb-close" @click="close()" aria-label="{{ __('Close') }}">✕</button>
            <button type="button" class="bp-lb-btn bp-lb-prev" @click.stop="prev()" x-show="photos.length > 1" aria-label="{{ __('Previous') }}">‹</button>
            <img class="bp-lb-img" :src="photos[index]" @click.stop alt="{{ $title }}">
            <button type="button" class="bp-lb-btn bp-lb-next" @click.stop="next()" x-show="photos.length > 1" aria-label="{{ __('Next') }}">›</button>
            <div class="bp-lb-counter" x-show="photos.length > 1" x-text="(index + 1) + ' / ' + photos.length"></div>
        </div>
    </div>

    <div class="bp-detail-body">
        <div class="bp-detail-content">
            <div class="bp-host-strip">
                <div class="bp-host-avatar">{{ $hostInitial }}</div>
                <div style="flex:1;">
                    <div class="bp-host-name">{{ __('Hosted by') }} {{ $hostName }}</div>
                    <div class="bp-host-meta">{{ __('Replies on WhatsApp · Verified host') }}</div>
                </div>
                @if ($contactPhone)
                    <a href="https://wa.me/{{ $contactPhone }}?text={{ urlencode(__("Hi! I'm interested in :name. Is it available?", ['name' => $title])) }}"
                       target="_blank" rel="noopener" class="btn btn-sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.8-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4 0 1.4 1 2.8 1.2 3 .1.2 2.1 3.2 5.1 4.4.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.1-.3-.2-.6-.3z"/><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .9.9-2.9-.2-.3a8.2 8.2 0 1 1 6.8 3.7z"/></svg>
                        WhatsApp
                    </a>
                @endif
            </div>

            <section class="bp-section">
                <h2>{{ __('About this place') }}</h2>
                <p style="font-size:15px; line-height:1.65; color:var(--ink-2); margin:0; text-wrap:pretty;">
                    {{ $description ?: __('Direct-booked stay — message the host on WhatsApp for arrival details, special requests and dietary needs.') }}
                </p>
                <div class="bp-highlight-grid">
                    <div class="bp-highlight">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <div>
                            <div class="bp-highlight-label">{{ __('Sleeps') }}</div>
                            <div class="bp-highlight-value">{{ $sleeps }}</div>
                        </div>
                    </div>
                    <div class="bp-highlight">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3"/><path d="M2 11h20v9H2z"/><path d="M6 11V9a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                        <div>
                            <div class="bp-highlight-label">{{ __('Rooms') }}</div>
                            <div class="bp-highlight-value">{{ $roomCount }}</div>
                        </div>
                    </div>
                    <div class="bp-highlight">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15 9 22 9.3 16.5 14 18.5 21 12 17 5.5 21 7.5 14 2 9.3 9 9"/></svg>
                        <div>
                            <div class="bp-highlight-label">{{ __('Rated') }}</div>
                            <div class="bp-highlight-value">{{ $rating }}/5</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bp-section">
                <h2>{{ __('What this place offers') }}</h2>
                <div class="bp-amenity-grid">
                    @foreach ($facilityList as $f)
                        <div class="bp-amenity">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            {{ $f['label'] }}
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($rooms->count())
                <section class="bp-section">
                    <h2>{{ __('Rooms') }}</h2>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        @foreach ($rooms as $room)
                            <div class="hauz-card" style="padding:14px 16px; display:flex; justify-content:space-between; align-items:center; gap:12px;">
                                <div>
                                    <div style="font-weight:600; color:var(--ink);">{{ $room->name }}</div>
                                    <div style="font-size:12.5px; color:var(--ink-3); margin-top:2px;">
                                        {{ $room->max_adults ?? 2 }} {{ __('adults') }}
                                        @if ($room->max_children ?? null) · {{ $room->max_children }} {{ __('children') }} @endif
                                    </div>
                                </div>
                                <div style="font-weight:600; font-family:var(--font-display); font-size:17px;">
                                    RM {{ number_format((float) $room->base_price, 0) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="bp-rules-grid bp-section">
                <div>
                    <h2 style="font-size:18px; font-weight:600; margin:0 0 12px;">{{ __('House rules') }}</h2>
                    <ul style="margin:0; padding-left:18px; font-size:13.5px; color:var(--ink-2); line-height:1.8;">
                        @foreach ($rules as $rule)
                            <li>{{ $rule }}</li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 style="font-size:18px; font-weight:600; margin:0 0 12px;">{{ __('Cancellation policy') }}</h2>
                    <p style="margin:0; font-size:13.5px; color:var(--ink-2); line-height:1.6;">
                        {{ $cancellation }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Sticky booking widget (becomes top calendar card on mobile) --}}
        <aside class="bp-side-sticky">
            <div class="bp-widget">
                {{-- Mobile-only "tap dates" banner (hidden on desktop) --}}
                <div class="bp-widget-banner bp-mobile-only" x-show="true">
                    <span class="bp-widget-pulse"></span>
                    <span x-text="bannerText()"></span>
                </div>

                <div style="padding:24px;" class="bp-widget-pad">
                    <div class="bp-widget-rate">
                        <span class="num">RM {{ number_format($rate, 0) }}</span>
                        <span class="per">/ {{ __('night') }}</span>
                    </div>
                    <div class="bp-widget-rating">★ {{ $rating }} · {{ $reviewCount }} {{ __('reviews') }}</div>

                    <div class="bp-widget-dates">
                        <div :style="checkin && !checkout ? 'background: var(--primary-tint);' : ''">
                            <div class="lbl">{{ __('Check-in') }}</div>
                            <div style="font-size:13px; font-weight:500;" x-text="checkin ? fmtFull(checkin) : '{{ __('Tap a date') }}'"></div>
                        </div>
                        <div :style="checkin && !checkout ? 'background: var(--primary-tint);' : ''">
                            <div class="lbl">{{ __('Check-out') }}</div>
                            <div style="font-size:13px; font-weight:500;" x-text="checkout ? fmtFull(checkout) : (checkin ? '{{ __('Tap end date') }}' : '—')"></div>
                        </div>
                    </div>

                    <div class="bp-widget-guests" style="border:.5px solid var(--line-2); border-radius:12px; margin-bottom:14px;">
                        <div>
                            <div class="lbl" style="font-size:9.5px; text-transform:uppercase; letter-spacing:.12em; color:var(--ink-3); font-weight:600; margin-bottom:2px;">{{ __('Guests') }}</div>
                            <div style="font-size:13px; font-weight:500;">
                                <span x-text="guests"></span> {{ __('guests') }}
                                <span style="color:var(--ink-3); font-weight:400; font-size:11.5px;">· {{ __('max') }} {{ $sleeps }}</span>
                            </div>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="bp-stepper" @click="guests = Math.max(1, guests - 1)">−</button>
                            <button type="button" class="bp-stepper" @click="guests = Math.min({{ $sleeps }}, guests + 1)">+</button>
                        </div>
                    </div>

                    {{-- Calendar --}}
                    <div class="bp-cal-month-row">
                        <div class="bp-cal-month-title bp-cal-hdr-title" x-text="monthLabel(0)"></div>
                        <div class="bp-cal-nav">
                            <button type="button" class="bp-cal-nav-btn" @click="prevMonth()" :aria-disabled="isCurrentMonth() ? 'true' : 'false'" aria-label="{{ __('Previous month') }}">‹</button>
                            <button type="button" class="bp-cal-nav-btn" @click="nextMonth()" aria-label="{{ __('Next month') }}">›</button>
                        </div>
                    </div>

                    <div class="bp-cal">
                        <template x-for="m in [0, 1]" :key="m">
                            <div>
                                <div class="bp-cal-month-title bp-cal-col-title" :class="{ 'bp-cal-col-title-first': m === 0 }" x-text="monthLabel(m)"></div>
                                <div class="bp-cal-weekdays">
                                    <template x-for="(w, i) in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="i">
                                        <div class="bp-cal-weekday" x-text="w"></div>
                                    </template>
                                </div>
                                <div class="bp-cal-grid">
                                    <template x-for="(d, i) in monthDays(m)" :key="m + '-' + i">
                                        <button
                                            type="button"
                                            class="bp-cal-day"
                                            :class="{
                                                'bp-cal-day-empty': !d,
                                                'bp-cal-day-past': d && isPast(d),
                                                'bp-cal-day-booked': d && !isPast(d) && isBooked(d),
                                                'bp-cal-day-available': d && !isPast(d) && !isBooked(d),
                                                'bp-cal-day-selected': d && isSelected(d),
                                                'bp-cal-day-in-range': d && inRange(d),
                                            }"
                                            :disabled="!d || isPast(d) || isBooked(d)"
                                            @click="d && pickDay(d)"
                                            x-text="d ? d.getDate() : ''"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="bp-cal-legend">
                        <span class="bp-cal-legend-item">
                            <span class="bp-cal-legend-swatch" style="background:var(--primary);"></span>{{ __('Selected') }}
                        </span>
                        <span class="bp-cal-legend-item">
                            <span class="bp-cal-legend-swatch" style="background:var(--primary-tint);"></span>{{ __('In stay') }}
                        </span>
                        <span class="bp-cal-legend-item">
                            <span class="bp-cal-legend-swatch" style="background:repeating-linear-gradient(-45deg, transparent 0 3px, var(--bg-sunk) 3px 5px); border:.5px solid var(--line-2);"></span>{{ __('Booked') }}
                        </span>
                        <span class="bp-cal-legend-item">
                            <span class="bp-cal-legend-swatch" style="background:var(--bg-elev); border:.5px solid var(--line-2);"></span>{{ __('Available') }}
                        </span>
                    </div>

                    {{-- Primary: click through to the host's own booking page
                         (carries marketplace attribution → 3% commission). --}}
                    <a href="{{ $bookUrl }}" class="bp-widget-cta">
                        {{ __('Book at :host', ['host' => $listing->tenant->business_name]) }} →
                    </a>
                    <a :href="reserveLink()" target="_blank" rel="noopener"
                       style="display:block; text-align:center; margin-top:10px; font-size:13px; color:var(--ink-3); text-decoration:none;">
                        {{ __('Or ask the host on WhatsApp') }}
                    </a>
                    <div class="bp-widget-cta-hint">{{ __("You won't be charged yet") }}</div>

                    <div class="bp-breakdown" x-show="checkin && checkout">
                        <div class="bp-breakdown-row">
                            <span>RM {{ number_format($rate, 0) }} × <span x-text="nights()"></span> {{ __('nights') }}</span>
                            <span class="mono" x-text="'RM ' + subtotal().toLocaleString()"></span>
                        </div>
                        <div class="bp-breakdown-row">
                            <span>{{ __('Cleaning fee') }}</span>
                            <span class="mono">RM 80</span>
                        </div>
                        <div class="bp-breakdown-total">
                            <span>{{ __('Total (MYR)') }}</span>
                            <span class="mono" x-text="'RM ' + total().toLocaleString()"></span>
                        </div>
                    </div>

                    <div class="bp-widget-trust">
                        <span class="pill pill-ok">✓ {{ __('Free cancel 7d before') }}</span>
                        <span class="pill">🔒 {{ __('Direct booking') }}</span>
                    </div>
                </div>
            </div>

            <div class="bp-widget-aside">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <div style="flex:1;">
                    <strong>{{ __('Got questions?') }}</strong> {{ __('Replies on WhatsApp in < 5 min.') }}
                </div>
            </div>
        </aside>
    </div>

    {{-- Mobile sticky reserve dock (only shown ≤640px when dates picked) --}}
    <div class="bp-mobile-cta" x-show="checkin && checkout">
        <div class="bp-mobile-cta-row">
            <span class="lbl"><span x-text="nights()"></span> {{ __('nights') }} × RM {{ number_format($rate, 0) }}</span>
            <span class="val" x-text="'RM ' + subtotal().toLocaleString()"></span>
        </div>
        <div class="bp-mobile-cta-row total">
            <span class="lbl">{{ __('Total incl. fees') }}</span>
            <span class="val" x-text="'RM ' + total().toLocaleString()"></span>
        </div>
        <a href="{{ $bookUrl }}" class="bp-mobile-cta-btn">
            <span>{{ __('Book at :host', ['host' => $listing->tenant->business_name]) }}</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
    </div>
</main>

@push('scripts')
<script>
function bookingDetail(opts) {
    return {
        rate: opts.rate,
        sleeps: opts.sleeps,
        bookedSet: new Set(opts.bookedDates || []),
        contactPhone: opts.contactPhone,
        propertyName: opts.propertyName,
        locale: opts.locale,
        cursor: (() => { const d = new Date(); d.setHours(0,0,0,0); d.setDate(1); return d; })(),
        today: (() => { const d = new Date(); d.setHours(0,0,0,0); return d; })(),
        checkin: null,
        checkout: null,
        guests: Math.min(opts.defaultGuests || 2, opts.sleeps || 99),

        iso(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },
        isPast(d) { return d < this.today; },
        isBooked(d) { return this.bookedSet.has(this.iso(d)); },
        isSelected(d) {
            const k = this.iso(d);
            return k === this.checkin || k === this.checkout;
        },
        inRange(d) {
            if (!this.checkin || !this.checkout) return false;
            const k = this.iso(d);
            return k > this.checkin && k < this.checkout;
        },
        pickDay(d) {
            const k = this.iso(d);
            if (this.isPast(d) || this.isBooked(d)) return;
            if (!this.checkin || (this.checkin && this.checkout)) {
                this.checkin = k; this.checkout = null;
            } else if (k <= this.checkin) {
                this.checkin = k; this.checkout = null;
            } else {
                // check no booked nights between
                const start = new Date(this.checkin);
                const end = new Date(k);
                const cur = new Date(start); cur.setDate(cur.getDate() + 1);
                let valid = true;
                while (cur < end) {
                    if (this.bookedSet.has(this.iso(cur))) { valid = false; break; }
                    cur.setDate(cur.getDate() + 1);
                }
                if (valid) this.checkout = k;
                else { this.checkin = k; this.checkout = null; }
            }
        },
        monthDays(offset) {
            const c = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + offset, 1);
            const last = new Date(c.getFullYear(), c.getMonth() + 1, 0);
            const startOffset = (c.getDay() + 6) % 7; // Mon-first
            const days = [];
            for (let i = 0; i < startOffset; i++) days.push(null);
            for (let d = 1; d <= last.getDate(); d++) days.push(new Date(c.getFullYear(), c.getMonth(), d));
            return days;
        },
        monthLabel(offset) {
            const c = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + offset, 1);
            return c.toLocaleDateString(this.locale, { month: 'long', year: 'numeric' });
        },
        isCurrentMonth() {
            return this.cursor.getFullYear() === this.today.getFullYear()
                && this.cursor.getMonth() === this.today.getMonth();
        },
        prevMonth() {
            if (this.isCurrentMonth()) return;
            this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() - 1, 1);
        },
        nextMonth() {
            this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + 1, 1);
        },
        nights() {
            if (!this.checkin || !this.checkout) return 0;
            return Math.max(1, Math.round((new Date(this.checkout) - new Date(this.checkin)) / 86400000));
        },
        subtotal() { return this.nights() * this.rate; },
        total() {
            const sub = this.subtotal();
            const cleaning = sub > 0 ? 80 : 0;
            return sub + cleaning;
        },
        fmtFull(iso) {
            return new Date(iso).toLocaleDateString(this.locale, { weekday: 'short', day: 'numeric', month: 'short' });
        },
        fmtShort(iso) {
            if (!iso) return '';
            return new Date(iso).toLocaleDateString(this.locale, { day: 'numeric', month: 'short' });
        },
        bannerText() {
            if (!this.checkin) return '{{ __('Tap any date to start booking') }}';
            if (!this.checkout) return '{{ __('Now tap your check-out date') }}';
            return '{{ __('Locked in — review and reserve') }}';
        },
        reserveLink() {
            if (!this.checkin || !this.checkout || !this.contactPhone) return '#';
            const msg = `Hi! I'd like to book ${this.propertyName}, ${this.fmtFull(this.checkin)} → ${this.fmtFull(this.checkout)}, ${this.guests} guests. Total RM ${this.total().toLocaleString()}.`;
            return `https://wa.me/${this.contactPhone}?text=${encodeURIComponent(msg)}`;
        },
    };
}
</script>
@endpush
@endsection
