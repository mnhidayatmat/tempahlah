<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tenant->business_name }} — {{ __('Book direct') }}</title>
    <meta name="description" content="{{ __('Direct bookings for :name. No middleman, no commission.', ['name' => $tenant->business_name]) }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Tenant subdomain doesn't load Livewire, so Alpine is pulled standalone from CDN. --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style id="tenant-theme">:root { {!! $tenant->themeCssVariables() !!} }</style>
    <meta name="theme-color" content="{{ $tenant->themePrimary() }}">
    {{-- iOS / Android PWA: when added to home screen, run as a standalone
         app with a TRANSPARENT status bar so the cover photo can extend
         under the wifi/signal/battery icons. white-translucent / black-
         translucent both work but "black-translucent" gives white icons
         which read best over our deep-bottom-scrim hero. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    @php
        $loc = app()->getLocale();
        $isBM = $loc === 'ms';

        $coverGradients = [
            'beach' => [
                'g' => 'radial-gradient(ellipse at 20% 30%, rgba(150,200,230,0.6) 0%, transparent 55%), radial-gradient(ellipse at 80% 70%, rgba(50,110,160,0.5) 0%, transparent 55%), linear-gradient(135deg, #4a82a8 0%, #6ba0c4 45%, #b8d4e3 100%)',
                'tone' => '🌊',
                'lbl_en' => 'Beachfront', 'lbl_ms' => 'Tepi Pantai',
            ],
            'highland' => [
                'g' => 'radial-gradient(ellipse at 20% 30%, rgba(178,210,170,0.6) 0%, transparent 55%), radial-gradient(ellipse at 80% 70%, rgba(80,120,80,0.5) 0%, transparent 55%), linear-gradient(135deg, #5d7a5d 0%, #8aab8a 45%, #c6dac6 100%)',
                'tone' => '🌲',
                'lbl_en' => 'Highland', 'lbl_ms' => 'Tanah Tinggi',
            ],
            'kampung' => [
                'g' => 'radial-gradient(ellipse at 20% 30%, rgba(255,200,140,0.55) 0%, transparent 55%), radial-gradient(ellipse at 80% 70%, rgba(168,98,30,0.45) 0%, transparent 55%), linear-gradient(135deg, #b08750 0%, #d4a86a 45%, #ecd2a8 100%)',
                'tone' => '🌾',
                'lbl_en' => 'Kampung', 'lbl_ms' => 'Kampung',
            ],
            'heritage' => [
                'g' => 'radial-gradient(ellipse at 20% 30%, rgba(255,200,140,0.5) 0%, transparent 55%), radial-gradient(ellipse at 80% 70%, rgba(168,64,30,0.5) 0%, transparent 55%), radial-gradient(ellipse at 50% 90%, rgba(217,119,87,0.6) 0%, transparent 60%), linear-gradient(135deg, #c25e3e 0%, #d97757 45%, #e8a06a 100%)',
                'tone' => '🏛️',
                'lbl_en' => 'Heritage', 'lbl_ms' => 'Warisan',
            ],
            'city' => [
                'g' => 'radial-gradient(ellipse at 20% 30%, rgba(200,170,210,0.55) 0%, transparent 55%), radial-gradient(ellipse at 80% 70%, rgba(120,80,150,0.4) 0%, transparent 55%), linear-gradient(135deg, #7a5e95 0%, #9d80b8 45%, #d4c2e0 100%)',
                'tone' => '🏙️',
                'lbl_en' => 'City', 'lbl_ms' => 'Bandar',
            ],
        ];

        $propertiesPayload = $properties->map(function ($p) use ($coverGradients, $isBM, $bookedByProperty) {
            $cover = $coverGradients[$p->cover_kind] ?? $coverGradients['city'];
            // For whole-house properties this is the single synthetic Room.
            // For per-room properties this picks the cheapest — matching
            // the "from RM X" headline. v1.5 will let the form choose the
            // exact room when the property has many.
            $defaultRoom = $p->rooms->sortBy('base_price')->first();
            // Full address for the "Direction" bottom-nav button (Google
            // Maps deep-link). Use whatever fields are populated, joined by
            // commas; the property name is prepended so the map result is
            // accurate when only the city/state is set.
            $addressParts = array_filter([
                $p->name,
                $p->address_line1,
                $p->address_line2,
                $p->postcode ? trim(($p->postcode ?? '').' '.($p->city ?? '')) : $p->city,
                $p->state,
                $p->country ?: 'Malaysia',
            ]);
            $addressFull = implode(', ', $addressParts);

            // All photo URLs (cover first if marked is_hero, then by
            // sort_order). Fed into the gallery lightbox.
            $sortedPhotos = $p->photos
                ->sortBy(fn ($ph) => ($ph->is_hero ? 0 : 1).str_pad((string) ($ph->sort_order ?? 0), 6, '0', STR_PAD_LEFT))
                ->values();
            $galleryPhotos = $sortedPhotos
                ->map(fn ($ph) => $ph->url())
                ->filter()
                ->values()
                ->all();

            // Top amenities for the Utama chip strip — already eager-loaded
            // on the controller. Sorted by a category priority (essentials
            // first, then food, leisure, family, cultural, safety, etc.)
            // then by per-amenity sort_order. Take up to 10; UI can scroll.
            $catPriority = ['essential' => 0, 'kitchen' => 1, 'outdoor' => 2, 'leisure' => 3, 'family' => 4, 'cultural' => 5, 'safety' => 6, 'tech' => 7];
            $topAmenities = $p->amenities
                ->sortBy(fn ($a) => sprintf('%02d-%04d',
                    $catPriority[$a->category] ?? 99,
                    (int) ($a->sort_order ?? 0),
                ))
                ->take(10)
                ->map(fn ($a) => [
                    'icon'  => (string) ($a->icon ?? '✓'),
                    'label' => $isBM ? (string) $a->label_bm : (string) $a->label_en,
                ])
                ->values()
                ->all();

            return [
                'id'        => $p->id,
                'name'      => $p->name,
                'city'      => $p->city,
                'state'     => $p->state,
                'address'   => $addressFull,
                // Pre-pinned Google Maps short-link the host pasted in the
                // property edit form (optional). When non-null the bottom-nav
                // "Direction" button opens this directly instead of building
                // a Maps-search URL from the free-text address.
                'map_url'   => $p->map_url,
                // Per-booking flat fee (cleaning, service, etc.). Surfaces
                // as its own line on the summary + invoice. fee_amount = 0
                // means no fee — the line is hidden.
                'fee_amount' => (float) ($p->booking_fee_amount ?? 0),
                'fee_label'  => (string) ($p->booking_fee_label ?? ''),
                'rate'      => (float) $p->starting_rate,
                'sleeps'    => (int) $p->sleeps_total,
                'default_guests' => (int) ($p->default_guests_resolved ?? max(1, (int) floor(($p->sleeps_total ?? 2) / 2))),
                'rooms'     => (int) $p->rooms->count(),
                'beds'      => (int) $p->beds_total,
                'cover'     => $cover['g'],
                'photo_url' => $p->cover_photo_url,
                'photos'    => $galleryPhotos,
                // Locale-aware short description for the "About" card. Falls
                // back to the other locale if the host only filled one side.
                'description' => trim((string) ($isBM
                    ? ($p->description_bm ?: $p->description_en)
                    : ($p->description_en ?: $p->description_bm))),
                'amenities' => $topAmenities,
                'bathrooms_total' => (int) (($p->bathrooms ?? 0) + ($p->toilets ?? 0)),
                'kind'      => $p->cover_kind,
                'tone'      => $cover['tone'],
                // Was a hardcoded category label ("Tanah Tinggi" / "Beachfront")
                // derived from `cover_kind`. Customers found it confusing — it
                // looked like part of the address. Show the property's actual
                // street/area instead (address_line1), and gracefully hide the
                // span when it's blank (city/state still render in the next span).
                'tone_label'=> trim((string) ($p->address_line1 ?? '')),
                'initial'   => mb_strtoupper(mb_substr($p->name, 0, 1)),
                'booked'    => $bookedByProperty[$p->id] ?? [],
                // Default room id used by the booking-form hidden input.
                'room_id'   => $defaultRoom?->id,
                // Dynamic per-date rates (60-day window starting today).
                // Empty for dates outside the window — calendar falls back
                // to `rate` (the "starting from" headline).
                'rates'     => $p->rates_by_date ?? (object) [],
            ];
        })->values();
    @endphp
</head>
<body class="wf-body @if($properties->isEmpty()) wf-empty-body @endif">

@if($properties->isEmpty())

    <main class="wf-empty">
        <div class="wf-empty-mark">·</div>
        <h1 class="wf-empty-title">{{ __('No homestays listed yet') }}</h1>
        <p class="wf-empty-sub">{{ __('We are setting things up. Please check back soon.') }}</p>
        @if($contactPhone)
            <a href="https://wa.me/{{ $contactPhone }}" target="_blank" rel="noopener" class="wf-empty-cta">{{ __('Message us') }} →</a>
        @endif
    </main>

@else

<main class="wf-app"
      x-data="wafa({
          tenantName: @js($tenant->business_name),
          tenantSlug: @js($tenant->slug),
          tenantDomain: @js(config('app.tenant_domain')),
          tenantPhone: @js($tenant->business_phone),
          tenantEmail: @js($tenant->business_email),
          phone: @js($contactPhone),
          locale: @js($isBM ? 'ms-MY' : 'en-MY'),
          isBM: @js($isBM),
          properties: @js($propertiesPayload),
          toyyibpayConfigured: @js($toyyibpayConfigured),
          depositPct: 20,
      })">

    {{-- ───── HERO BANNER ─────────────────────────────────────────── --}}
    <header class="wf-banner"
            :class="{ 'wf-banner-has-photo': !!current.photo_url }"
            :style="current.photo_url
                ? `background-image: url('${current.photo_url}'); background-size: cover; background-position: center; background-color: #1a1614;`
                : `background: ${current.cover};`">
        {{-- Diagonal-stripe grain only when there's no real photo; over a
             photo it would look like an old TV. --}}
        <div class="wf-banner-grain" aria-hidden="true" x-show="!current.photo_url"></div>
        {{-- Slightly stronger top scrim when a photo is present, so the
             locale + WhatsApp pills stay readable over varied imagery. --}}
        <div class="wf-banner-vignette-top" aria-hidden="true" x-show="!!current.photo_url"></div>
        <div class="wf-banner-vignette" aria-hidden="true"></div>

        <div class="wf-banner-top">
            <a href="{{ route('locale.switch', $loc === 'ms' ? 'en' : 'ms') }}" class="wf-pill wf-pill-light">
                <span style="font-family:var(--font-mono); font-weight:600;">{{ strtoupper($loc) }}</span>
                <span style="opacity:.5;">/</span>
                <span style="font-family:var(--font-mono); opacity:.7;">{{ $loc === 'ms' ? 'EN' : 'MS' }}</span>
            </a>
            @if($contactPhone)
                <a href="https://wa.me/{{ $contactPhone }}" target="_blank" rel="noopener" class="wf-pill wf-pill-solid" aria-label="WhatsApp">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.8-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4 0 1.4 1 2.8 1.2 3 .1.2 2.1 3.2 5.1 4.4.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.1-.3-.2-.6-.3z"/><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .9.9-2.9-.2-.3a8.2 8.2 0 1 1 6.8 3.7z"/></svg>
                    WhatsApp
                </a>
            @endif
        </div>

        <div class="wf-banner-bottom">
            {{-- Business name leads the hero so the homestay brand reads first.
                 The address kicker sits below in a smaller, lighter treatment so
                 it informs without competing with the name. --}}
            <h1 class="wf-banner-name">{{ $tenant->business_name }}</h1>
            <div class="wf-banner-kicker">
                <svg class="wf-banner-pin" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
                <template x-if="current.tone_label">
                    <span x-text="current.tone_label"></span>
                </template>
                <template x-if="current.tone_label && (current.city || current.state)">
                    <span class="wf-banner-dot">·</span>
                </template>
                <span x-text="(current.city || '') + (current.state ? ', ' + current.state : '')"></span>
            </div>
        </div>
    </header>

    {{-- ───── PROPERTY CARDS ──────────────────────────────────────── --}}
    {{-- Only render the "Pilih pakej" picker when the tenant has 2+ active
         properties. With a single homestay, the banner already identifies
         it — forcing the customer to "pick" their only option is noise.
         Alpine still defaults selectedIdx to 0 so the calendar + booking
         flow work normally against that single property. --}}
    @if ($properties->count() > 1)
        <section class="wf-stack">
            <div class="wf-section-eyebrow">{{ $isBM ? 'Pilih pakej' : 'Choose your stay' }}</div>

            <template x-for="(p, i) in properties" :key="p.id">
                <button type="button"
                        class="wf-prop"
                        :class="{ 'is-active': selectedIdx === i }"
                        @click="selectProperty(i)">
                    <span class="wf-prop-bar"></span>
                    <div class="wf-prop-body">
                        <div class="wf-prop-name" x-text="p.name"></div>
                        <div class="wf-prop-meta">
                            <span x-text="p.rooms"></span> {{ $isBM ? 'bilik' : 'rooms' }}
                            <span class="wf-prop-meta-dot">·</span>
                            <span x-text="p.sleeps || p.beds || 2"></span> {{ $isBM ? 'tetamu' : 'sleeps' }}
                        </div>
                    </div>
                    <div class="wf-prop-price">
                        <div class="wf-prop-price-rm">RM</div>
                        <div class="wf-prop-price-num" x-text="formatMoney(p.rate)"></div>
                        <div class="wf-prop-price-per">/ {{ $isBM ? 'malam' : 'night' }}</div>
                    </div>
                </button>
            </template>
        </section>
    @endif

    {{-- ───── UTAMA OVERVIEW SECTIONS ──────────────────────────────
         All sections below auto-render against current.* — switching
         properties (when tenant has 2+) updates them reactively.
         Each section hides when its underlying data is empty, so a
         brand-new tenant with minimal data still sees a clean page.
    ───────────────────────────────────────────────────────────── --}}

    {{-- 1) Quick-stats strip — 4 tiles, always renders. --}}
    <section class="wf-stats">
        <div class="wf-stat">
            <div class="wf-stat-icon">🛏️</div>
            <div class="wf-stat-num" x-text="current.beds || current.rooms"></div>
            <div class="wf-stat-lbl">{{ $isBM ? 'Bilik' : 'Bedrooms' }}</div>
        </div>
        <div class="wf-stat">
            <div class="wf-stat-icon">👥</div>
            <div class="wf-stat-num" x-text="current.sleeps"></div>
            <div class="wf-stat-lbl">{{ $isBM ? 'Tetamu' : 'Guests' }}</div>
        </div>
        <div class="wf-stat" x-show="current.bathrooms_total > 0" x-cloak>
            <div class="wf-stat-icon">🚿</div>
            <div class="wf-stat-num" x-text="current.bathrooms_total"></div>
            <div class="wf-stat-lbl">{{ $isBM ? 'Bilik air' : 'Baths' }}</div>
        </div>
        <div class="wf-stat">
            <div class="wf-stat-icon">💰</div>
            <div class="wf-stat-num">
                <span style="font-size: 12px; opacity: 0.6;">RM</span><span x-text="Math.round(current.rate).toLocaleString()"></span>
            </div>
            <div class="wf-stat-lbl">{{ $isBM ? '/ malam' : '/ night' }}</div>
        </div>
    </section>

    {{-- 2) About — short description card. Hidden when both locale
         descriptions are blank (new tenant before they fill it in). --}}
    <section class="wf-about" x-show="current.description && current.description.length > 0" x-cloak>
        <div class="wf-section-eyebrow">{{ $isBM ? 'Tentang' : 'About' }}</div>
        <p class="wf-about-body" x-text="current.description"></p>
    </section>

    {{-- 3) Top amenities chips — horizontal scrollable strip. --}}
    <section class="wf-amenities" x-show="current.amenities && current.amenities.length > 0" x-cloak>
        <div class="wf-section-eyebrow">{{ $isBM ? 'Kemudahan' : 'What\'s included' }}</div>
        <div class="wf-amenities-row">
            <template x-for="(a, i) in current.amenities" :key="i">
                <div class="wf-amenity-chip">
                    <span class="wf-amenity-icon" x-text="a.icon"></span>
                    <span class="wf-amenity-lbl" x-text="a.label"></span>
                </div>
            </template>
        </div>
    </section>

    {{-- 4) Photo strip — horizontal swipe of thumbnails. Tap any →
         opens the existing gallery lightbox at that index. --}}
    <section class="wf-photostrip" x-show="current.photos && current.photos.length > 1" x-cloak>
        <div class="wf-section-eyebrow-row">
            <div class="wf-section-eyebrow">{{ $isBM ? 'Galeri' : 'Gallery' }}</div>
            <button type="button" class="wf-photostrip-all" @click="openGallery()">
                <span x-text="`${current.photos.length} ${'{{ $isBM ? 'foto' : 'photos' }}'} →`"></span>
            </button>
        </div>
        <div class="wf-photostrip-row">
            <template x-for="(url, i) in current.photos" :key="i">
                <button type="button" class="wf-photostrip-tile" @click="galleryIndex = i; galleryOpen = true; document.body.style.overflow='hidden'; navTab='gallery';">
                    <img :src="url" :alt="`${current.name} ${i+1}`" loading="lazy">
                </button>
            </template>
        </div>
    </section>

    {{-- 5) Location card — address + Get directions. Skips when no
         address has been entered. --}}
    <section class="wf-location" x-show="current.address && current.address.length > 0" x-cloak>
        <div class="wf-section-eyebrow">{{ $isBM ? 'Lokasi' : 'Location' }}</div>
        <div class="wf-location-card">
            <div class="wf-location-pin">📍</div>
            <div class="wf-location-text">
                <div class="wf-location-name" x-text="current.name"></div>
                <div class="wf-location-addr" x-text="current.address"></div>
            </div>
            <a class="wf-location-cta" :href="directionUrl()" target="_blank" rel="noopener">
                <span>🧭</span>
                <span>{{ $isBM ? 'Arah' : 'Directions' }}</span>
            </a>
        </div>
    </section>

    {{-- ───── DARK PROMPT BAR ─────────────────────────────────────── --}}
    <div class="wf-prompt" x-show="!checkin">
        <span>{{ $isBM ? 'Pilih mana-mana tarikh untuk tempah' : 'Click any date to make a booking' }}</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
    </div>

    {{-- ───── PICKED PILLS (after first pick) ─────────────────────── --}}
    <div class="wf-pills" x-show="checkin" x-cloak>
        <div class="wf-pill-cell" :class="{ 'is-active': checkin && !checkout }">
            <div class="wf-pill-lbl">{{ $isBM ? 'Daftar masuk' : 'Check-in' }}</div>
            <div class="wf-pill-val" x-text="checkin ? fmtPill(checkin) : '— —'"></div>
        </div>
        <div class="wf-pill-cell" :class="{ 'is-active': checkin && !checkout }">
            <div class="wf-pill-lbl">{{ $isBM ? 'Daftar keluar' : 'Check-out' }}</div>
            <div class="wf-pill-val" x-text="checkout ? fmtPill(checkout) : (checkin ? (isBM ? 'Pilih tarikh keluar' : 'Pick check-out') : '— —')"></div>
        </div>
    </div>

    {{-- ───── CALENDAR CARD ───────────────────────────────────────── --}}
    <section class="wf-cal">
        <div class="wf-cal-month-row">
            <div class="wf-cal-month" x-text="monthLabel()"></div>
            <div class="wf-cal-nav">
                <button type="button" class="wf-cal-nav-btn" @click="prevMonth" :disabled="isCurrentMonth()" aria-label="{{ __('Previous month') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button type="button" class="wf-cal-nav-btn" @click="nextMonth" aria-label="{{ __('Next month') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>

        <div class="wf-cal-weekdays">
            <template x-for="(w, i) in weekdayHeader" :key="i">
                <div :class="{ 'is-weekend': i === 0 || i === 6 }" x-text="w"></div>
            </template>
        </div>

        <div class="wf-cal-grid">
            <template x-for="(d, i) in monthDays()" :key="i">
                <button type="button"
                        class="wf-cal-day"
                        :class="{
                            'wf-cal-day-empty':     !d,
                            'wf-cal-day-past':      d && isPast(d),
                            'wf-cal-day-full':      d && !isPast(d) && isBooked(d),
                            'wf-cal-day-available': d && !isPast(d) && !isBooked(d),
                            'wf-cal-day-today':     d && isToday(d),
                            'wf-cal-day-selected':  d && (isCheckin(d) || isCheckout(d)),
                            'wf-cal-day-in-range':  d && inRange(d),
                            'wf-cal-day-range-start': d && isCheckin(d) && checkout,
                            'wf-cal-day-range-end':   d && isCheckout(d),
                        }"
                        :disabled="!d || isPast(d) || isBooked(d)"
                        @click="d && pickDay(d)">
                    <span class="wf-cal-day-num" x-text="d ? d.getDate() : ''"></span>
                    <span class="wf-cal-day-rate" x-show="d && !isPast(d) && !isBooked(d) && !isCheckin(d) && !isCheckout(d)" x-text="d ? ('RM' + Math.round(rateFor(d))) : ''"></span>
                </button>
            </template>
        </div>

        <div class="wf-cal-legend">
            <span class="wf-leg"><span class="wf-leg-dot is-avail"></span>{{ $isBM ? 'Kosong' : 'Available' }}</span>
            <span class="wf-leg"><span class="wf-leg-dot is-pick"></span>{{ $isBM ? 'Pilih' : 'Selected' }}</span>
            <span class="wf-leg"><span class="wf-leg-dot is-full"></span>{{ $isBM ? 'Penuh' : 'Booked' }}</span>
        </div>
    </section>

    {{-- ───── SUMMARY + RESERVE (after both dates) ────────────────── --}}
    <section class="wf-bottom" x-show="checkin && checkout" x-cloak>
        <div class="wf-summary">
            <div class="wf-summary-head">
                <div class="wf-summary-head-l">
                    <div class="wf-summary-tag">{{ $isBM ? 'Ringkasan tempahan' : 'Booking summary' }}</div>
                    <div class="wf-summary-property" x-text="current.name"></div>
                </div>
                <div class="wf-summary-head-r">
                    <span x-text="fmtPill(checkin)"></span>
                    <span class="wf-summary-arrow">→</span>
                    <span x-text="fmtPill(checkout)"></span>
                </div>
            </div>

            <div class="wf-summary-row">
                <span class="lbl">RM <span x-text="formatMoney(avgRate())"></span> × <span x-text="nights()"></span> {{ $isBM ? 'malam' : 'nights' }}</span>
                <span class="val">RM <span x-text="formatMoney(subtotal())"></span></span>
            </div>
            {{-- Per-booking flat fee line (only when the host set one). --}}
            <div class="wf-summary-row" x-show="feeAmount() > 0" x-cloak>
                <span class="lbl" x-text="feeLabel()"></span>
                <span class="val">RM <span x-text="formatMoney(feeAmount())"></span></span>
            </div>
            <div class="wf-summary-row wf-summary-row-guest">
                <div class="wf-guests-lbl">
                    <span class="lbl">{{ $isBM ? 'Tetamu' : 'Guests' }}</span>
                </div>
                <div class="wf-stepper">
                    <button type="button" class="wf-stepper-btn" @click="guests = Math.max(1, guests - 1)" :disabled="guests <= 1" aria-label="−">−</button>
                    <span class="wf-stepper-num" x-text="guests + ' ' + (isBM ? 'orang' : 'pax')"></span>
                    <button type="button" class="wf-stepper-btn" @click="guests = Math.min(current.sleeps || 99, guests + 1)" :disabled="guests >= (current.sleeps || 99)" aria-label="+">+</button>
                </div>
            </div>
            <div class="wf-summary-row wf-summary-total">
                <span class="lbl">{{ $isBM ? 'Jumlah anggaran' : 'Estimated total' }}</span>
                <span class="val">RM <span x-text="formatMoney(grandTotal())"></span></span>
            </div>
            <div class="wf-summary-note">
                ✻ {{ $isBM ? 'SST + cukai pelancongan akan disahkan tuan rumah di WhatsApp.' : 'SST + tourism tax confirmed by host on WhatsApp.' }}
            </div>
        </div>

        @if($toyyibpayConfigured)
            {{-- Pay-deposit CTA: opens the reservation form. --}}
            <button type="button" class="wf-reserve" @click="openBookForm = true; $nextTick(() => { const el = document.getElementById('wf-book-name'); if (el) el.focus(); })">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="13" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>{{ $isBM ? 'Tempah & bayar sekarang' : 'Reserve & pay now' }} · RM <span x-text="formatMoney(depositAmount())"></span></span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-left:auto;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
            <div class="wf-reserve-hint">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>{{ $isBM ? 'Bayar selamat melalui Toyyibpay · FPX, kad, DuitNow' : 'Secure payment via Toyyibpay · FPX, cards, DuitNow' }}</span>
            </div>
        @elseif($contactPhone)
            {{-- Fallback: tenant hasn't connected Toyyibpay yet. Keep the
                 wa.me deeplink so the page still works out-of-the-box. --}}
            <a :href="reserveLink()" target="_blank" rel="noopener" class="wf-reserve">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.8-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4 0 1.4 1 2.8 1.2 3 .1.2 2.1 3.2 5.1 4.4.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.1-.3-.2-.6-.3z"/><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .9.9-2.9-.2-.3a8.2 8.2 0 1 1 6.8 3.7z"/></svg>
                <span>{{ $isBM ? 'Tempah di WhatsApp' : 'Reserve on WhatsApp' }} · RM <span x-text="formatMoney(grandTotal())"></span></span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-left:auto;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
            <div class="wf-reserve-hint">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>{{ $isBM ? 'Tiada bayaran sekarang — tuan rumah akan sahkan' : 'No payment yet — host confirms first' }}</span>
            </div>
        @endif
    </section>

    @if($toyyibpayConfigured)
    {{-- ───── BOOK FORM MODAL ─────────────────────────────────────── --}}
    <div class="wf-book-overlay" x-show="openBookForm" x-cloak x-transition.opacity @click.self="openBookForm = false" @keydown.escape.window="openBookForm = false">
        <div class="wf-book-card" @click.stop x-transition>
            <button type="button" class="wf-book-close" @click="openBookForm = false" aria-label="{{ __('Close') }}">×</button>
            <div class="wf-book-eyebrow">{{ $isBM ? 'Pengesahan tempahan' : 'Confirm your booking' }}</div>
            <div class="wf-book-title" x-text="current.name"></div>

            <div class="wf-book-recap">
                <div class="wf-book-recap-row">
                    <span class="lbl">{{ $isBM ? 'Tarikh' : 'Dates' }}</span>
                    <span class="val"><span x-text="checkin ? fmtPill(checkin) : ''"></span> → <span x-text="checkout ? fmtPill(checkout) : ''"></span></span>
                </div>
                <div class="wf-book-recap-row">
                    <span class="lbl">{{ $isBM ? 'Malam' : 'Nights' }}</span>
                    <span class="val" x-text="nights()"></span>
                </div>
                <div class="wf-book-recap-row">
                    <span class="lbl">{{ $isBM ? 'Tetamu' : 'Guests' }}</span>
                    <span class="val" x-text="guests + ' ' + (isBM ? 'orang' : 'pax')"></span>
                </div>
                <div class="wf-book-recap-row" x-show="feeAmount() > 0" x-cloak>
                    <span class="lbl" x-text="feeLabel()"></span>
                    <span class="val">RM <span x-text="formatMoney(feeAmount())"></span></span>
                </div>
                <div class="wf-book-recap-row">
                    <span class="lbl">{{ $isBM ? 'Jumlah anggaran' : 'Estimated total' }}</span>
                    <span class="val">RM <span x-text="formatMoney(grandTotal())"></span></span>
                </div>
                {{-- "Pay now" line — tied to the property's booking fee
                     (set in Property → Pricing → Booking fee, default
                     RM 100). Replaces the old hardcoded 20% deposit. --}}
                <div class="wf-book-recap-row wf-book-recap-deposit" x-show="depositAmount() > 0" x-cloak>
                    <span class="lbl">{{ $isBM ? 'Bayar sekarang' : 'Pay now' }}</span>
                    <span class="val">RM <span x-text="formatMoney(depositAmount())"></span></span>
                </div>
            </div>

            <form method="POST" action="{{ route('tenant-public.booking.store', ['tenant_slug' => $tenant->slug]) }}" class="wf-book-form">
                @csrf
                <input type="hidden" name="property_id" :value="current.id">
                <input type="hidden" name="room_id" :value="current.room_id">
                <input type="hidden" name="check_in" :value="checkin">
                <input type="hidden" name="check_out" :value="checkout">
                <input type="hidden" name="adults" :value="guests">
                <input type="hidden" name="children" value="0">

                <label class="wf-book-field">
                    <span class="wf-book-label">{{ $isBM ? 'Nama penuh' : 'Full name' }}</span>
                    <input id="wf-book-name" type="text" name="guest_name" required minlength="2" maxlength="120" autocomplete="name" value="{{ old('guest_name') }}">
                </label>

                <label class="wf-book-field">
                    <span class="wf-book-label">{{ $isBM ? 'Emel' : 'Email' }}</span>
                    <input type="email" name="guest_email" required maxlength="160" autocomplete="email" placeholder="you@example.com" value="{{ old('guest_email') }}">
                </label>

                <label class="wf-book-field">
                    <span class="wf-book-label">{{ $isBM ? 'Nombor WhatsApp' : 'WhatsApp number' }}</span>
                    <input type="tel" name="guest_phone" required minlength="7" maxlength="24" autocomplete="tel" placeholder="+60123456789" value="{{ old('guest_phone') }}">
                </label>

                <label class="wf-book-field">
                    <span class="wf-book-label">{{ $isBM ? 'Permintaan khas (pilihan)' : 'Special requests (optional)' }}</span>
                    <textarea name="special_requests" rows="2" maxlength="500" placeholder="{{ $isBM ? 'cth: daftar masuk awal' : 'e.g. early check-in' }}">{{ old('special_requests') }}</textarea>
                </label>

                @error('guest_email') <div class="wf-book-err">{{ $message }}</div> @enderror
                @error('guest_phone') <div class="wf-book-err">{{ $message }}</div> @enderror

                <div class="wf-book-policy">
                    <span class="wf-book-policy-title">{{ $isBM ? 'Polisi bayaran balik' : 'Refund policy' }}</span>
                    {{ $tenant->refundPolicyText() }}
                </div>

                <button type="submit" class="wf-book-submit" @click="bookSubmitting = true">
                    <span x-show="!bookSubmitting">{{ $isBM ? 'Bayar sekarang' : 'Pay now' }} RM <span x-text="formatMoney(depositAmount())"></span></span>
                    <span x-show="bookSubmitting" x-cloak>{{ $isBM ? 'Memproses…' : 'Processing…' }}</span>
                </button>
                <p class="wf-book-fine">
                    {{ $isBM
                        ? 'Anda akan dialihkan ke Toyyibpay untuk membayar yuran tempahan. Resit & pengesahan dihantar ke emel + WhatsApp.'
                        : 'You\'ll be redirected to Toyyibpay to pay the booking fee. Receipt + confirmation are sent to your email + WhatsApp.' }}
                </p>
            </form>
        </div>
    </div>
    @endif

    @if (session('booking_error'))
        <div class="wf-flash wf-flash-err">{{ session('booking_error') }}</div>
    @endif

    {{-- Empty-state hint when no dates picked --}}
    <div class="wf-hint" x-show="!checkin && !checkout">
        <strong>{{ $isBM ? 'Tempah terus' : 'Direct booking' }}</strong> ·
        {{ $isBM ? 'Tiada caj platform.' : 'No platform fees.' }}
        @if($contactPhone)
            {{ $isBM ? 'Mesej kami untuk soalan:' : 'Message us for questions:' }}
            <a href="https://wa.me/{{ $contactPhone }}" target="_blank" rel="noopener">WhatsApp ↗</a>
        @endif
    </div>

    {{-- ───── OWNER AREA (footer) ─────────────────────────────────
         Discreet link for the tenant owner / staff to jump back to
         the dashboard. Auth is shared across subdomains via the
         `.tempahlah.com` session cookie, so we can detect whether
         the visitor is signed in AND a member of THIS tenant.
         - Owner of this tenant   → "Open dashboard"
         - Anyone else / signed out → "Owner area" (apex /login)
         Sits in the scrolling page (above the fixed bottom nav) so
         it never competes with the customer CTAs above the fold. --}}
    <div class="wf-owner-area">
        @if ($ownerCanAccess)
            <a href="{{ $apexUrl }}/dashboard" rel="noopener">
                {{ $isBM ? 'Buka papan pemuka' : 'Open dashboard' }} →
            </a>
        @else
            <a href="{{ $apexUrl }}/login" rel="noopener">
                {{ $isBM ? 'Ruang pemilik' : 'Owner area' }} →
            </a>
        @endif
    </div>

    {{-- ───── BOTTOM NAV ──────────────────────────────────────────── --}}
    {{-- 5-item dock with raised middle "TEMPAH" pillar.
         Order: Utama · Homestay · TEMPAH (raised) · Direction · WhatsApp
         The middle item is rendered with .wf-botnav-pillar — a floating
         circle that sits ABOVE the bar. The bar has an SVG cut-out behind
         it so the circle's bottom half visually lifts out of the dock. --}}
    <nav class="wf-botnav" :class="{ 'wf-botnav-has-gallery': current.photos && current.photos.length }">
        {{-- Utama / Home — scroll to top --}}
        <button type="button"
                class="wf-botnav-item"
                :class="{ 'is-active': navTab === 'home' }"
                @click="goHome"
                aria-label="{{ $isBM ? 'Utama' : 'Home' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M9 20v-6h6v6"/></svg>
            <span>{{ $isBM ? 'Utama' : 'Home' }}</span>
        </button>

        {{-- Gallery — open photo lightbox (hidden when property has 0 photos) --}}
        <button type="button"
                class="wf-botnav-item"
                :class="{ 'is-active': navTab === 'gallery' }"
                :disabled="!current.photos || current.photos.length === 0"
                @click="openGallery()"
                aria-label="{{ $isBM ? 'Galeri' : 'Gallery' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
            <span>{{ $isBM ? 'Galeri' : 'Gallery' }}</span>
        </button>

        {{-- TEMPAH — raised middle pillar --}}
        <button type="button"
                class="wf-botnav-item wf-botnav-pillar"
                :class="{ 'is-active': navTab === 'book' }"
                @click="goBook"
                aria-label="{{ $isBM ? 'Tempah' : 'Book' }}">
            <span class="wf-botnav-pillar-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2.5"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <span class="wf-botnav-pillar-label">{{ $isBM ? 'TEMPAH' : 'BOOK' }}</span>
        </button>

        {{-- Direction — opens Google Maps to the property address --}}
        <a :href="directionUrl()"
           target="_blank"
           rel="noopener"
           class="wf-botnav-item"
           :class="{ 'is-active': navTab === 'direction' }"
           @click="navTab = 'direction'"
           aria-label="{{ $isBM ? 'Arah' : 'Direction' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>{{ $isBM ? 'Arah' : 'Direction' }}</span>
        </a>

        {{-- WhatsApp --}}
        @if($contactPhone)
            <a href="https://wa.me/{{ $contactPhone }}"
               target="_blank"
               rel="noopener"
               class="wf-botnav-item"
               :class="{ 'is-active': navTab === 'wa' }"
               @click="navTab = 'wa'"
               aria-label="WhatsApp">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.8-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4 0 1.4 1 2.8 1.2 3 .1.2 2.1 3.2 5.1 4.4.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.1-.3-.2-.6-.3z"/><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .9.9-2.9-.2-.3a8.2 8.2 0 1 1 6.8 3.7z"/></svg>
                <span>WhatsApp</span>
            </a>
        @else
            {{-- No WA configured — keep grid columns balanced with a placeholder --}}
            <span class="wf-botnav-item" style="opacity:0.4; pointer-events:none;" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                <span>—</span>
            </span>
        @endif
    </nav>

    {{-- ───── GALLERY LIGHTBOX ─────────────────────────────────── --}}
    <div class="wf-gallery"
         x-show="galleryOpen"
         x-cloak
         x-transition.opacity
         @click.self="closeGallery()"
         @keydown.escape.window="closeGallery()"
         @keydown.arrow-left.window="galleryOpen && galleryPrev()"
         @keydown.arrow-right.window="galleryOpen && galleryNext()">
        <button type="button" class="wf-gallery-close" @click="closeGallery()" aria-label="Close">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="wf-gallery-stage"
             @touchstart="galleryTouchStart($event)"
             @touchend="galleryTouchEnd($event)">
            <button type="button"
                    class="wf-gallery-arrow wf-gallery-arrow-prev"
                    @click.stop="galleryPrev()"
                    x-show="current.photos && current.photos.length > 1"
                    aria-label="Previous">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <img :src="current.photos[galleryIndex]"
                 :alt="`${current.name} photo ${galleryIndex+1}`"
                 class="wf-gallery-img">
            <button type="button"
                    class="wf-gallery-arrow wf-gallery-arrow-next"
                    @click.stop="galleryNext()"
                    x-show="current.photos && current.photos.length > 1"
                    aria-label="Next">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
        <div class="wf-gallery-counter" x-show="current.photos && current.photos.length > 1">
            <span x-text="galleryIndex + 1"></span> / <span x-text="current.photos.length"></span>
        </div>
    </div>

</main>

@endif

<style>
    [x-cloak] { display: none !important; }

    .wf-body {
        margin: 0;
        font-family: var(--font-sans);
        background:
            radial-gradient(900px 600px at 50% -20%, oklch(96% 0.04 45 / 0.5), transparent 65%),
            var(--bg);
        color: var(--ink);
        min-height: 100dvh;
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
        -webkit-tap-highlight-color: transparent;
    }
    .wf-body * { box-sizing: border-box; }
    /* Kill the 300ms double-tap-zoom delay on iOS for every interactive
       element on this page. Combined with the 16px input font-size rule
       below, this makes taps feel native (no auto-zoom on focus, no
       300ms hesitation on double-tap). */
    .wf-body button,
    .wf-body a,
    .wf-body [role="button"],
    .wf-body input,
    .wf-body textarea,
    .wf-body select {
        touch-action: manipulation;
    }

    /* ── App shell — phone frame ──────────────────────────────── */
    .wf-app {
        max-width: 440px;
        margin: 0 auto;
        min-height: 100dvh;
        background: var(--bg-elev);
        position: relative;
        padding-bottom: calc(76px + env(safe-area-inset-bottom));
        box-shadow: 0 0 60px -10px rgba(40,30,10,0.08);
    }
    @media (min-width: 768px) {
        .wf-body {
            background:
                radial-gradient(1200px 800px at 50% -20%, oklch(96% 0.04 45 / 0.6), transparent 65%),
                radial-gradient(900px 600px at -10% 110%, oklch(96% 0.025 80 / 0.5), transparent 60%),
                var(--bg);
        }
        .wf-app {
            max-width: 480px;
            margin: 24px auto;
            min-height: auto;
            border-radius: 32px;
            overflow: hidden;
            border: 1px solid var(--line);
            box-shadow: 0 30px 80px -20px rgba(40,30,10,0.18), 0 4px 12px rgba(40,30,10,0.05);
        }
    }

    /* ── Banner ────────────────────────────────────────────────── */
    .wf-banner {
        position: relative;
        /* Bleed under the iOS status bar / Android cutout. The visible
           content area below the notch is still 240px; safe-area-inset-top
           adds however many extra pixels the device's notch / status bar
           occupies (0 on desktop and most browsers, ~44–59px on iOS phones
           in standalone-PWA mode). */
        height: calc(240px + env(safe-area-inset-top));
        margin-top: 0;
        overflow: hidden;
        color: #fff;
    }
    .wf-banner-grain {
        position: absolute; inset: 0;
        background:
            radial-gradient(circle at 15% 20%, rgba(255,255,255,0.18) 0%, transparent 25%),
            radial-gradient(circle at 85% 60%, rgba(40,20,10,0.20) 0%, transparent 35%),
            repeating-linear-gradient(45deg, transparent 0 18px, rgba(255,255,255,0.04) 18px 19px);
        pointer-events: none;
    }
    .wf-banner-vignette {
        position: absolute; left: 0; right: 0; bottom: 0;
        height: 65%;
        background: linear-gradient(180deg, transparent 0%, rgba(20,10,5,0.55) 100%);
        pointer-events: none;
    }
    /* When the hero is showing a real cover photo, the H1 + tagline need
       a deeper bottom scrim because real photos vary wildly in brightness. */
    .wf-banner.wf-banner-has-photo .wf-banner-vignette {
        height: 75%;
        background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.35) 45%, rgba(0,0,0,0.75) 100%);
    }
    /* Light top scrim only when there's a photo — keeps the locale +
       WhatsApp pills (top-left / top-right) readable over bright skies. */
    .wf-banner-vignette-top {
        position: absolute; left: 0; right: 0; top: 0;
        height: 35%;
        background: linear-gradient(180deg, rgba(0,0,0,0.45) 0%, transparent 100%);
        pointer-events: none;
    }
    /* Slight blueish-grey backdrop while the photo is still loading. */
    .wf-banner.wf-banner-has-photo { background-color: #2c2622; }
    .wf-banner-top {
        position: absolute;
        top: calc(14px + env(safe-area-inset-top));
        left: 14px; right: 14px;
        display: flex; justify-content: space-between; align-items: center;
        gap: 10px;
        z-index: 2;
    }
    .wf-banner-bottom {
        position: absolute;
        left: 18px; right: 18px; bottom: 20px;
        z-index: 2;
    }
    .wf-banner-kicker {
        display: inline-flex; align-items: center; gap: 6px;
        margin-top: 8px;
        font-size: 11.5px; font-weight: 600;
        color: rgba(255,255,255,0.95);
        text-shadow: 0 1px 5px rgba(0,0,0,0.55);
        margin-bottom: 10px;
    }
    .wf-banner-pin {
        flex-shrink: 0;
        color: rgba(255,255,255,0.95);
        filter: drop-shadow(0 1px 3px rgba(0,0,0,0.5));
    }
    .wf-banner-dot { color: rgba(255,255,255,0.5); }
    .wf-banner-name {
        font-size: 26px; font-weight: 700;
        letter-spacing: -0.025em;
        line-height: 1.1;
        margin: 0;
        text-shadow: 0 2px 8px rgba(0,0,0,0.35);
    }

    .wf-pill {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11.5px; font-weight: 600;
        padding: 7px 11px;
        border-radius: 999px;
        text-decoration: none;
        font-family: var(--font-sans);
        letter-spacing: 0.005em;
        transition: transform .12s, background .15s;
    }
    .wf-pill-light {
        background: rgba(255,255,255,0.92);
        color: var(--ink);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .wf-pill-light:active { transform: scale(0.96); }
    .wf-pill-solid {
        background: var(--primary);
        color: #fff;
        box-shadow: 0 4px 12px -2px rgba(217,119,87,0.4);
    }
    .wf-pill-solid:active { transform: scale(0.96); background: var(--primary-hover); }

    /* ── Section eyebrow ──────────────────────────────────────── */
    .wf-section-eyebrow {
        font-family: var(--font-mono);
        font-size: 10px; font-weight: 600;
        letter-spacing: 0.14em; text-transform: uppercase;
        color: var(--ink-3);
        margin: 0 0 8px;
        padding: 0 4px;
    }

    /* ── Stack of property cards (Wafa packages) ─────────────── */
    .wf-stack {
        padding: 18px 16px 0;
    }
    .wf-prop {
        display: flex; align-items: stretch;
        width: 100%;
        background: var(--bg);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 0;
        margin: 0 0 10px;
        overflow: hidden;
        appearance: none;
        cursor: pointer;
        text-align: left;
        font-family: inherit;
        transition: border-color .15s, background .15s, transform .1s;
        box-shadow: 0 1px 0 rgba(40,30,10,0.04);
    }
    .wf-prop:active { transform: scale(0.99); }
    .wf-prop.is-active {
        border-color: var(--primary);
        background: oklch(98% 0.02 45);
        box-shadow: 0 8px 22px -8px rgba(217,119,87,0.32);
    }
    .wf-prop-bar {
        width: 5px;
        flex-shrink: 0;
        background: var(--primary-edge);
        transition: background .15s;
    }
    .wf-prop.is-active .wf-prop-bar {
        background: linear-gradient(180deg, var(--primary) 0%, var(--primary-deep) 100%);
    }
    .wf-prop-body {
        flex: 1; min-width: 0;
        padding: 14px 4px 14px 14px;
    }
    .wf-prop-name {
        font-size: 15px; font-weight: 700;
        letter-spacing: -0.015em;
        color: var(--ink);
        line-height: 1.2;
    }
    .wf-prop-meta {
        margin-top: 4px;
        font-size: 11.5px; color: var(--ink-3);
        font-weight: 500;
    }
    .wf-prop-meta-dot { color: var(--ink-4); margin: 0 4px; }

    .wf-prop-price {
        flex-shrink: 0;
        align-self: stretch;
        background: linear-gradient(180deg, var(--primary) 0%, var(--primary-hover) 100%);
        color: #fff;
        padding: 12px 16px;
        display: flex; flex-direction: column;
        align-items: flex-end; justify-content: center;
        line-height: 1;
        position: relative;
        min-width: 96px;
    }
    .wf-prop-price::before {
        content: "";
        position: absolute;
        left: -1px; top: 50%; transform: translateY(-50%);
        width: 8px; height: 8px;
        background: var(--bg-elev);
        border-radius: 50%;
    }
    .wf-prop-price-rm {
        font-size: 10px; font-weight: 600;
        letter-spacing: 0.08em;
        opacity: 0.85;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .wf-prop-price-num {
        font-family: var(--font-mono);
        font-size: 22px; font-weight: 700;
        letter-spacing: -0.02em;
        font-feature-settings: "tnum";
    }
    .wf-prop-price-per {
        font-size: 9.5px; font-weight: 500;
        opacity: 0.8;
        margin-top: 3px;
    }

    /* ──────────────────── Utama overview sections ────────────────────
       All five sections share a tight horizontal margin matching the
       phone-frame inner padding, and a 12-14px vertical rhythm between
       them so the page reads as a scannable summary above the calendar.
       Each section auto-hides via x-show on current.* when its data is
       empty — new tenants with minimal data still see a clean page.
    ──────────────────────────────────────────────────────────────── */

    /* 1) Quick-stats — 4 even tiles, never wraps even on narrow phones */
    .wf-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin: 16px 14px 4px;
    }
    .wf-stat {
        background: var(--bg-elev);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 12px 6px 10px;
        text-align: center;
        line-height: 1.2;
    }
    .wf-stat-icon { font-size: 18px; margin-bottom: 4px; }
    .wf-stat-num  {
        font-family: var(--font-mono);
        font-size: 17px;
        font-weight: 700;
        color: var(--ink);
        letter-spacing: -0.01em;
    }
    .wf-stat-lbl  {
        font-size: 10.5px;
        color: var(--ink-3);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-top: 2px;
    }

    /* 2) About — short body copy in a soft tinted card */
    .wf-about {
        margin: 14px 14px 0;
        background: var(--bg-elev);
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 14px 16px 16px;
    }
    .wf-about .wf-section-eyebrow { margin-bottom: 8px; }
    .wf-about-body {
        margin: 0;
        font-size: 13.5px;
        line-height: 1.6;
        color: var(--ink-2);
        white-space: pre-wrap;
    }

    /* 3) Amenities — emoji-led pill chips on a horizontal scroll rail */
    .wf-amenities { margin: 14px 0 0; }
    .wf-amenities .wf-section-eyebrow { margin: 0 14px 8px; }
    .wf-amenities-row {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 4px 14px 6px;
        scrollbar-width: none;
        scroll-snap-type: x proximity;
        -webkit-overflow-scrolling: touch;
    }
    .wf-amenities-row::-webkit-scrollbar { display: none; }
    .wf-amenity-chip {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 13px 8px 11px;
        background: var(--bg-elev);
        border: 1px solid var(--line);
        border-radius: 999px;
        scroll-snap-align: start;
        font-size: 12.5px;
        color: var(--ink-2);
        white-space: nowrap;
    }
    .wf-amenity-icon { font-size: 15px; line-height: 1; }
    .wf-amenity-lbl  { font-weight: 500; }

    /* 4) Photo strip — horizontal tile rail; 4:5 portrait crops feel
       hotel-magazine-ish and let one more tile peek in vs square. */
    .wf-photostrip { margin: 16px 0 0; }
    .wf-section-eyebrow-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin: 0 14px 8px;
    }
    .wf-photostrip-all {
        background: transparent;
        border: 0;
        color: var(--primary);
        font-family: var(--font-mono);
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        cursor: pointer;
        padding: 0;
    }
    .wf-photostrip-row {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 4px 14px 8px;
        scrollbar-width: none;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
    }
    .wf-photostrip-row::-webkit-scrollbar { display: none; }
    .wf-photostrip-tile {
        flex: 0 0 132px;
        aspect-ratio: 4 / 5;
        border: 0;
        border-radius: 14px;
        overflow: hidden;
        padding: 0;
        cursor: pointer;
        background: var(--bg-elev);
        scroll-snap-align: start;
        position: relative;
        box-shadow: 0 1px 2px rgba(15,25,40,0.06);
    }
    .wf-photostrip-tile img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.25s ease;
    }
    .wf-photostrip-tile:active img { transform: scale(1.03); }

    /* 5) Location — pin + address + Directions CTA */
    .wf-location { margin: 16px 14px 4px; }
    .wf-location .wf-section-eyebrow { margin-bottom: 8px; }
    .wf-location-card {
        display: flex;
        align-items: center;
        gap: 12px;
        background: var(--bg-elev);
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 14px;
    }
    .wf-location-pin {
        font-size: 22px;
        flex-shrink: 0;
        width: 40px; height: 40px;
        background: color-mix(in srgb, var(--primary) 9%, transparent);
        border-radius: 10px;
        display: grid; place-items: center;
    }
    .wf-location-text { flex: 1; min-width: 0; }
    .wf-location-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .wf-location-addr {
        font-size: 11.5px;
        line-height: 1.45;
        color: var(--ink-3);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .wf-location-cta {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 12px;
        background: var(--primary);
        color: #fff;
        text-decoration: none;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
        box-shadow: 0 4px 10px -4px color-mix(in srgb, var(--primary) 55%, transparent);
        transition: transform 0.15s ease;
    }
    .wf-location-cta:active { transform: scale(0.96); }

    /* ── Dark prompt strip ────────────────────────────────────── */
    .wf-prompt {
        display: flex; align-items: center; justify-content: center; gap: 10px;
        margin: 14px 16px 12px;
        padding: 13px 18px;
        background: linear-gradient(135deg, #2c2622 0%, #1a1614 100%);
        color: #fff;
        font-size: 13px; font-weight: 600;
        border-radius: 12px;
        letter-spacing: 0.005em;
        box-shadow: 0 4px 14px -4px rgba(40,30,10,0.4);
        animation: wf-prompt-bob 2.4s ease-in-out infinite;
    }
    @keyframes wf-prompt-bob {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-2px); }
    }

    /* ── Picked-date pills ────────────────────────────────────── */
    .wf-pills {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin: 0 16px 12px;
    }
    .wf-pill-cell {
        background: var(--bg);
        border: 1.5px solid var(--line);
        border-radius: 14px;
        padding: 10px 13px;
        transition: border-color .15s, background .15s;
    }
    .wf-pill-cell.is-active {
        border-color: var(--primary);
        background: oklch(97% 0.025 45);
    }
    .wf-pill-lbl {
        font-size: 9.5px; font-weight: 600;
        letter-spacing: 0.1em; text-transform: uppercase;
        color: var(--ink-3);
        margin-bottom: 3px;
    }
    .wf-pill-val {
        font-family: var(--font-mono);
        font-size: 13.5px; font-weight: 600;
        color: var(--ink);
        letter-spacing: -0.005em;
    }

    /* ── Calendar ─────────────────────────────────────────────── */
    .wf-cal {
        background: var(--bg);
        margin: 0 16px;
        border-radius: 18px;
        border: 1px solid var(--line);
        box-shadow: 0 2px 0 rgba(40,30,10,0.04), 0 12px 28px -16px rgba(40,30,10,0.12);
        overflow: hidden;
    }
    .wf-cal-month-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 14px 6px;
    }
    .wf-cal-month {
        font-size: 16px; font-weight: 700;
        letter-spacing: -0.018em;
        color: var(--ink);
    }
    .wf-cal-nav { display: flex; gap: 6px; }
    .wf-cal-nav-btn {
        width: 32px; height: 32px;
        border-radius: 50%;
        background: linear-gradient(180deg, var(--primary) 0%, var(--primary-hover) 100%);
        color: #fff;
        border: 0;
        display: grid; place-items: center;
        cursor: pointer;
        box-shadow: 0 3px 8px -2px rgba(217,119,87,0.45);
        transition: transform .1s;
    }
    .wf-cal-nav-btn:active { transform: scale(0.92); }
    .wf-cal-nav-btn:disabled {
        opacity: 0.35; cursor: not-allowed;
        box-shadow: none;
    }

    .wf-cal-weekdays {
        display: grid; grid-template-columns: repeat(7, 1fr);
        padding: 0 10px;
    }
    .wf-cal-weekdays > div {
        text-align: center;
        font-size: 10px; font-weight: 700;
        color: var(--ink-3);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        padding: 6px 0 4px;
    }
    .wf-cal-weekdays > div.is-weekend { color: var(--primary); }

    .wf-cal-grid {
        display: grid; grid-template-columns: repeat(7, 1fr);
        gap: 4px;
        padding: 4px 10px 12px;
    }
    .wf-cal-day {
        border: 0;
        border-radius: 10px;
        font-family: var(--font-sans);
        font-weight: 600;
        cursor: pointer;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        transition: transform 100ms, background .12s, color .12s;
        font-feature-settings: "tnum";
        position: relative;
        padding: 0;
        background: var(--bg-elev);
        color: var(--ink);
        font-size: 15px;
        aspect-ratio: 1 / 1;
        width: 100%;
    }
    .wf-cal-day-empty { visibility: hidden; }
    .wf-cal-day-past {
        background: transparent;
        color: var(--ink-4);
        cursor: not-allowed;
        text-decoration: line-through;
        text-decoration-thickness: 1px;
        text-decoration-color: var(--ink-4);
    }
    .wf-cal-day-full {
        background: var(--bg-sunk);
        color: var(--ink-4);
        cursor: not-allowed;
        position: relative;
    }
    .wf-cal-day-full::after {
        content: "";
        position: absolute;
        inset: 7px 5px;
        background: repeating-linear-gradient(-45deg, transparent 0 3px, var(--ink-4) 3px 4px);
        opacity: 0.35;
        border-radius: 6px;
        pointer-events: none;
    }
    .wf-cal-day-available {
        background: var(--bg);
        border: 1.5px solid var(--line-2);
        color: var(--ink);
    }
    .wf-cal-day-available:active { transform: scale(0.93); background: oklch(96% 0.04 45); border-color: var(--primary); }
    .wf-cal-day-today { box-shadow: inset 0 0 0 2px var(--primary); }
    .wf-cal-day-today.wf-cal-day-available { border-color: transparent; }

    .wf-cal-day-selected {
        background: linear-gradient(180deg, var(--primary) 0%, var(--primary-hover) 100%) !important;
        color: #fff !important;
        border-color: var(--primary) !important;
        box-shadow: 0 6px 14px -4px rgba(217,119,87,0.55) !important;
        z-index: 2;
        transform: scale(1.04);
    }
    .wf-cal-day-in-range {
        background: oklch(95% 0.06 45) !important;
        color: var(--primary-deep) !important;
        border-color: transparent !important;
        border-radius: 4px !important;
    }
    .wf-cal-day-range-start {
        border-top-right-radius: 4px !important;
        border-bottom-right-radius: 4px !important;
    }
    .wf-cal-day-range-end {
        border-top-left-radius: 4px !important;
        border-bottom-left-radius: 4px !important;
    }
    .wf-cal-day-num { line-height: 1; }
    .wf-cal-day-rate {
        font-family: var(--font-mono);
        font-size: 8.5px; font-weight: 600;
        color: var(--ink-3);
        margin-top: 3px;
        line-height: 1;
        font-feature-settings: "tnum";
    }
    .wf-cal-day-selected .wf-cal-day-rate { color: rgba(255,255,255,0.85); }
    .wf-cal-day-in-range .wf-cal-day-rate { color: var(--primary-deep); opacity: 0.65; }

    .wf-cal-legend {
        display: flex; flex-wrap: wrap;
        justify-content: center;
        gap: 14px;
        padding: 8px 12px 14px;
        border-top: 1px solid var(--line);
        margin-top: 4px;
    }
    .wf-leg {
        display: inline-flex; align-items: center; gap: 5px;
        font-family: var(--font-mono);
        font-size: 10px; font-weight: 600;
        letter-spacing: 0.04em;
        color: var(--ink-3);
        text-transform: uppercase;
    }
    .wf-leg-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
    }
    .wf-leg-dot.is-avail { background: var(--ok); }
    .wf-leg-dot.is-pick  { background: var(--primary); }
    .wf-leg-dot.is-full  { background: var(--ink-4); }

    /* ── Bottom: hint or summary ──────────────────────────────── */
    .wf-hint {
        text-align: center;
        font-size: 12px;
        color: var(--ink-3);
        font-weight: 500;
        /* Extra bottom padding clears the raised TEMPAH pillar circle
           that floats above the fixed dock — otherwise the hint text
           sits underneath the floating disc on mobile. */
        padding: 16px 18px 40px;
        line-height: 1.5;
        max-width: 360px;
        margin: 0 auto;
    }
    .wf-hint strong { color: var(--primary-deep); font-weight: 700; }
    .wf-hint a { color: var(--primary); text-decoration: none; font-weight: 600; }

    /* ── Owner area (footer link for tenant owner / staff) ───────
       Deliberately small + muted — this is host UX, not customer UX.
       Sits in the scroll area above the fixed bottom nav. Hover lifts
       the underline only. Respects existing token palette. */
    .wf-owner-area {
        text-align: center;
        padding: 20px 18px 8px;
        font-family: 'Geist Mono', ui-monospace, monospace;
        font-size: 11px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .wf-owner-area a {
        color: var(--ink-3);
        text-decoration: none;
        border-bottom: 1px dashed var(--line);
        padding: 2px 0;
        transition: color 120ms ease, border-color 120ms ease;
    }
    .wf-owner-area a:hover,
    .wf-owner-area a:focus-visible {
        color: var(--primary-deep);
        border-bottom-color: var(--primary);
        outline: none;
    }

    .wf-bottom {
        margin: 14px 16px 4px;
    }
    .wf-summary {
        background: var(--bg);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 14px 16px;
        box-shadow: 0 6px 18px -8px rgba(80,50,20,0.15);
    }
    .wf-summary-head {
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--line);
    }
    .wf-summary-head-l { flex: 1; min-width: 0; }
    .wf-summary-tag {
        font-family: var(--font-mono);
        font-size: 9.5px; font-weight: 600;
        letter-spacing: 0.12em; text-transform: uppercase;
        color: var(--ink-3);
        margin-bottom: 3px;
    }
    .wf-summary-property {
        font-size: 14.5px; font-weight: 700;
        color: var(--ink);
        letter-spacing: -0.012em;
    }
    .wf-summary-head-r {
        display: inline-flex; align-items: center; gap: 6px;
        font-family: var(--font-mono);
        font-size: 12.5px; font-weight: 600;
        color: var(--ink);
        background: oklch(97% 0.025 45);
        border-radius: 8px;
        padding: 6px 10px;
        flex-shrink: 0;
    }
    .wf-summary-arrow { color: var(--primary); }
    .wf-summary-row {
        display: flex; justify-content: space-between; align-items: center;
        font-size: 13px;
        padding: 5px 0;
    }
    .wf-summary-row .lbl { color: var(--ink-2); font-weight: 500; }
    .wf-summary-row .val { font-weight: 700; color: var(--ink); font-family: var(--font-mono); font-feature-settings: "tnum"; }
    .wf-summary-row-guest {
        padding: 6px 0;
    }
    .wf-stepper {
        display: inline-flex; align-items: center; gap: 6px;
        background: var(--bg-elev);
        border: 1px solid var(--line);
        border-radius: 999px;
        padding: 3px;
    }
    .wf-stepper-btn {
        width: 26px; height: 26px;
        border-radius: 50%;
        background: var(--bg); border: 1px solid var(--line);
        font-size: 14px; font-weight: 700;
        color: var(--ink-2); cursor: pointer;
        display: grid; place-items: center;
        font-family: inherit;
        transition: background .12s;
    }
    .wf-stepper-btn:active:not(:disabled) { background: var(--primary); color: #fff; border-color: var(--primary); }
    .wf-stepper-btn:disabled { opacity: 0.35; cursor: not-allowed; }
    .wf-stepper-num {
        font-family: var(--font-mono);
        font-size: 12.5px; font-weight: 600;
        color: var(--ink);
        min-width: 56px;
        text-align: center;
        padding: 0 4px;
    }
    .wf-summary-total {
        border-top: 1px dashed var(--line-2);
        margin-top: 8px;
        padding-top: 10px;
    }
    .wf-summary-total .lbl { color: var(--ink); font-weight: 700; font-size: 13.5px; }
    .wf-summary-total .val {
        color: var(--primary-deep);
        font-size: 19px;
        letter-spacing: -0.015em;
    }
    .wf-summary-note {
        margin-top: 10px;
        font-size: 10.5px;
        color: var(--ink-3);
        line-height: 1.4;
        font-style: italic;
    }

    .wf-reserve {
        display: flex; align-items: center; gap: 10px;
        width: 100%;
        margin-top: 12px;
        padding: 15px 18px;
        background: linear-gradient(180deg, var(--primary) 0%, var(--primary-hover) 100%);
        color: #fff;
        border: 0;
        border-radius: 14px;
        font-family: inherit;
        font-size: 14.5px; font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 8px 20px -4px rgba(217,119,87,0.5), inset 0 1px 0 rgba(255,255,255,0.2);
        letter-spacing: 0.005em;
        position: relative;
        overflow: hidden;
    }
    .wf-reserve::before {
        content: "";
        position: absolute; inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.18), transparent 50%);
        pointer-events: none;
    }
    .wf-reserve:active { transform: scale(0.985); }
    .wf-reserve-hint {
        display: flex; align-items: center; justify-content: center; gap: 6px;
        margin: 8px 4px 0;
        font-family: var(--font-mono);
        font-size: 10.5px;
        color: var(--ink-3);
        letter-spacing: 0.01em;
    }

    /* ── Bottom nav (5-item dock with raised middle TEMPAH pillar) ──
       5-column grid; the middle column hosts a floating circle that
       sits visually ABOVE the dock surface. A small downward shadow
       on the bar combined with the larger upward shadow on the circle
       gives the "lifted-out" effect from the template. */
    .wf-botnav {
        position: fixed;
        bottom: 0; left: 0; right: 0;
        max-width: 440px;
        margin: 0 auto;
        /* Taller than the previous 64px to give the pillar's bottom
           half somewhere to anchor inside the dock. */
        height: calc(72px + env(safe-area-inset-bottom));
        padding-bottom: env(safe-area-inset-bottom);
        background: rgba(255,255,255,0.96);
        backdrop-filter: saturate(180%) blur(20px);
        -webkit-backdrop-filter: saturate(180%) blur(20px);
        border-top: 1px solid var(--line);
        z-index: 30;
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        /* Allow the pillar's circle to overflow upward without clipping. */
        overflow: visible;
        /* Soft "lifted off the floor" shadow that the template's bar
           gives off so users notice the dock as a distinct surface. */
        box-shadow: 0 -8px 24px -12px rgba(40, 30, 10, 0.10);
    }
    @media (min-width: 768px) {
        .wf-botnav {
            position: sticky;
            bottom: 0;
            max-width: none;
            margin: 0;
            border-bottom-left-radius: 31px;
            border-bottom-right-radius: 31px;
        }
    }

    .wf-botnav-item {
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        gap: 4px;
        background: transparent; border: 0;
        padding: 8px 4px 6px;
        color: var(--ink-3);
        font-family: inherit;
        font-size: 11px; font-weight: 600;
        cursor: pointer;
        position: relative;
        letter-spacing: 0.005em;
        text-decoration: none;
        transition: color .12s, transform .1s;
    }
    .wf-botnav-item svg {
        width: 22px; height: 22px;
        stroke-width: 2;
    }
    .wf-botnav-item:active { transform: scale(0.94); }
    .wf-botnav-item:disabled { opacity: 0.4; cursor: not-allowed; }
    .wf-botnav-item:disabled:active { transform: none; }

    /* Active state — label flips to primary + dot beneath the label
       (matches the template's tiny dot under "Utama"). No top bar
       indicator any more — the dot reads cleaner under five items. */
    .wf-botnav-item.is-active { color: var(--primary-deep); }
    .wf-botnav-item.is-active::after {
        content: "";
        position: absolute;
        bottom: 2px; left: 50%; transform: translateX(-50%);
        width: 4px; height: 4px;
        background: var(--primary);
        border-radius: 999px;
    }

    /* ── Raised middle pillar (TEMPAH) ─────────────────────────── */
    .wf-botnav-pillar {
        /* Pull the entire column up so the circle floats above the
           dock. The label tucks neatly back inside the dock. */
        position: relative;
        padding-top: 0;
        padding-bottom: 6px;
        gap: 2px;
    }
    .wf-botnav-pillar-circle {
        /* The lifted disc. Solid primary fill, white icon. */
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 52px; height: 52px;
        border-radius: 999px;
        background: linear-gradient(180deg, var(--primary) 0%, var(--primary-deep) 100%);
        color: #fff;
        margin-top: -22px;   /* the lift */
        box-shadow:
            0 8px 18px -6px color-mix(in srgb, var(--primary-deep) 60%, transparent),
            0 0 0 4px var(--bg-elev);   /* white halo cut-out into the dock */
        transition: transform .12s ease;
    }
    .wf-botnav-pillar-circle svg {
        width: 24px; height: 24px;
        color: #fff;
    }
    .wf-botnav-pillar:active .wf-botnav-pillar-circle { transform: scale(0.93); }
    .wf-botnav-pillar-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--ink-2);
    }
    .wf-botnav-pillar.is-active .wf-botnav-pillar-label { color: var(--primary-deep); }
    /* Don't show the under-label dot on the pillar — it'd sit
       awkwardly below the uppercase wordmark. */
    .wf-botnav-pillar.is-active::after { display: none; }

    /* Extra bottom padding on the app shell so content isn't hidden
       behind the (slightly taller) new dock or the floating circle. */
    .wf-app { padding-bottom: calc(84px + env(safe-area-inset-bottom)); }

    /* ── Gallery lightbox ──────────────────────────────────────── */
    .wf-gallery {
        position: fixed;
        inset: 0;
        z-index: 80;
        background: rgba(20, 16, 14, 0.94);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 56px 16px 32px;
    }
    .wf-gallery-close {
        position: absolute;
        top: 14px; right: 14px;
        width: 40px; height: 40px;
        border-radius: 999px;
        background: rgba(255,255,255,0.12);
        color: #fff;
        border: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background .12s;
    }
    .wf-gallery-close:hover { background: rgba(255,255,255,0.22); }
    .wf-gallery-stage {
        position: relative;
        max-width: 100%;
        max-height: 75dvh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .wf-gallery-img {
        max-width: 100%;
        max-height: 75dvh;
        object-fit: contain;
        border-radius: 14px;
        box-shadow: 0 20px 60px -10px rgba(0,0,0,0.5);
    }
    .wf-gallery-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 48px; height: 48px;
        border-radius: 999px;
        /* Bumped from 0.14 → 0.28 so the arrows actually catch the eye
           over a dark photo. Still subtle enough not to dominate. */
        background: rgba(0,0,0,0.45);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.18);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background .12s, transform .12s;
        z-index: 2;
    }
    .wf-gallery-arrow:hover { background: rgba(0,0,0,0.65); }
    .wf-gallery-arrow:active { transform: translateY(-50%) scale(0.94); }
    .wf-gallery-arrow-prev { left: 8px; }
    .wf-gallery-arrow-next { right: 8px; }
    @media (min-width: 768px) {
        .wf-gallery-arrow-prev { left: -56px; }
        .wf-gallery-arrow-next { right: -56px; }
    }
    .wf-gallery-counter {
        margin-top: 14px;
        color: rgba(255,255,255,0.7);
        font-family: var(--font-mono);
        font-size: 12.5px;
        letter-spacing: 0.06em;
    }

    /* ── Empty state ──────────────────────────────────────────── */
    .wf-empty-body { background: var(--bg); display: grid; place-items: center; min-height: 100dvh; }
    .wf-empty { max-width: 380px; text-align: center; padding: 32px 24px; }
    .wf-empty-mark { font-size: 56px; line-height: 0.6; color: var(--ink-4); margin-bottom: 14px; }
    .wf-empty-title { font-size: 22px; font-weight: 700; margin: 0 0 8px; color: var(--ink); letter-spacing: -0.02em; }
    .wf-empty-sub { font-size: 14px; color: var(--ink-2); margin: 0 0 20px; line-height: 1.5; }
    .wf-empty-cta {
        display: inline-flex; padding: 12px 22px;
        background: var(--primary); color: #fff;
        border-radius: 999px; text-decoration: none;
        font-weight: 700; font-size: 14px;
        box-shadow: 0 8px 20px -4px rgba(217,119,87,0.45);
    }

    /* very small phones */
    @media (max-width: 380px) {
        .wf-banner { height: 220px; }
        .wf-banner-name { font-size: 22px; }
        .wf-prop-body { padding: 12px 4px 12px 12px; }
        .wf-prop-name { font-size: 14px; }
        .wf-cal-day { font-size: 13.5px; }
        .wf-cal-day-rate { font-size: 7.5px; }
        .wf-summary-property { font-size: 13.5px; }
        .wf-cal { margin: 0 12px; }
        .wf-stack { padding: 16px 12px 0; }
        .wf-prompt { margin: 12px 12px 10px; }
        .wf-pills, .wf-bottom { margin-left: 12px; margin-right: 12px; }
    }

    /* ── Flash banner ──────────────────────────────────────────── */
    .wf-flash {
        margin: 12px 16px 0;
        padding: 10px 14px;
        border-radius: 12px;
        font-size: 13px;
        line-height: 1.4;
    }
    .wf-flash-err {
        background: color-mix(in srgb, var(--err) 12%, transparent);
        color: var(--err);
        border: 1px solid color-mix(in srgb, var(--err) 30%, transparent);
    }

    /* ── Book form modal ──────────────────────────────────────── */
    .wf-book-overlay {
        position: fixed; inset: 0; z-index: 100;
        background: rgba(28, 22, 20, 0.55);
        backdrop-filter: blur(4px);
        display: flex; align-items: flex-end; justify-content: center;
        padding: 0;
    }
    @media (min-width: 768px) {
        .wf-book-overlay {
            align-items: center;
            padding: 24px;
        }
    }
    .wf-book-card {
        position: relative;
        width: 100%;
        max-width: 460px;
        background: var(--bg-elev);
        border-radius: 22px 22px 0 0;
        padding: 22px 20px calc(28px + env(safe-area-inset-bottom));
        box-shadow: 0 -20px 60px -10px rgba(40,30,10,0.25);
        max-height: 92dvh;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    @media (min-width: 768px) {
        .wf-book-card {
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 30px 80px -20px rgba(40,30,10,0.4);
        }
    }
    .wf-book-close {
        position: absolute; top: 12px; right: 12px;
        width: 32px; height: 32px;
        border: 0; background: var(--bg-sunk);
        border-radius: 50%;
        font-size: 22px; line-height: 1;
        color: var(--ink-2);
        cursor: pointer;
        display: grid; place-items: center;
    }
    .wf-book-close:hover { background: var(--line); color: var(--ink); }
    .wf-book-eyebrow {
        font-family: var(--font-mono);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--primary);
        margin-bottom: 4px;
    }
    .wf-book-title {
        font-family: var(--font-sans);
        font-weight: 700;
        font-size: 22px;
        color: var(--ink);
        margin-bottom: 16px;
        line-height: 1.2;
    }
    .wf-book-recap {
        background: var(--bg-sunk);
        border-radius: 14px;
        padding: 12px 14px;
        margin-bottom: 16px;
        display: flex; flex-direction: column; gap: 6px;
    }
    .wf-book-recap-row {
        display: flex; justify-content: space-between; align-items: baseline;
        font-size: 13px;
    }
    .wf-book-recap-row .lbl { color: var(--ink-3); }
    .wf-book-recap-row .val { color: var(--ink); font-weight: 500; font-family: var(--font-mono); }
    .wf-book-recap-deposit {
        margin-top: 4px;
        padding-top: 10px;
        border-top: 1px dashed var(--line);
    }
    .wf-book-recap-deposit .lbl { color: var(--primary-deep); font-weight: 600; }
    .wf-book-recap-deposit .val { color: var(--primary-deep); font-weight: 700; font-size: 15px; }
    .wf-book-form { display: flex; flex-direction: column; gap: 12px; }
    .wf-book-field { display: flex; flex-direction: column; gap: 4px; }
    .wf-book-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--ink-2);
    }
    .wf-book-form input,
    .wf-book-form textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--bg);
        font-family: inherit;
        /* 16px is the iOS Safari auto-zoom-on-focus threshold. Anything
           below and iOS zooms the viewport in on tap, which looks janky
           and reads as "the page is broken" on a phone. Keep at 16. */
        font-size: 16px;
        color: var(--ink);
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
        /* Kills iOS Safari's 300ms double-tap-zoom delay on focusable inputs */
        touch-action: manipulation;
    }
    .wf-book-form input:focus,
    .wf-book-form textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent);
    }
    .wf-book-form textarea { resize: vertical; min-height: 56px; }
    .wf-book-err {
        background: color-mix(in srgb, var(--err) 10%, transparent);
        color: var(--err);
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 12.5px;
    }
    .wf-book-submit {
        margin-top: 4px;
        background: linear-gradient(180deg, var(--primary) 0%, var(--primary-deep) 100%);
        color: #fff;
        border: 0;
        padding: 14px 18px;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        box-shadow: 0 4px 14px -4px color-mix(in srgb, var(--primary-deep) 45%, transparent);
        transition: transform 0.1s;
    }
    .wf-book-submit:hover { transform: translateY(-1px); }
    .wf-book-submit:active { transform: translateY(0); }
    .wf-book-submit:disabled { opacity: 0.7; cursor: wait; }
    .wf-book-fine {
        margin: 8px 0 0;
        font-size: 11.5px;
        line-height: 1.4;
        color: var(--ink-3);
        text-align: center;
    }
    .wf-book-policy {
        margin: 4px 0 2px;
        padding: 10px 12px;
        background: var(--bg-sunk, #f4f4f2);
        border-radius: 10px;
        font-size: 11.5px;
        line-height: 1.45;
        color: var(--ink-2);
        white-space: pre-line;
    }
    .wf-book-policy-title {
        display: block;
        font-weight: 700;
        color: var(--ink);
        margin-bottom: 2px;
    }
</style>

<script>
    function wafa(opts) {
        return {
            tenantName: opts.tenantName,
            tenantSlug: opts.tenantSlug,
            tenantDomain: opts.tenantDomain,
            tenantPhone: opts.tenantPhone,
            tenantEmail: opts.tenantEmail,
            phone: opts.phone,
            locale: opts.locale,
            isBM: opts.isBM,
            properties: opts.properties,
            selectedIdx: 0,
            cursor: (() => { const d = new Date(); d.setHours(0,0,0,0); d.setDate(1); return d; })(),
            today: (() => { const d = new Date(); d.setHours(0,0,0,0); return d; })(),
            checkin: null,
            checkout: null,
            /* Pre-fill from the first property's tenant-configured default
               (or floor(sleeps/2) if unset). On selectProperty() the stepper
               resets to that property's own default. */
            guests: opts.properties?.[0]?.default_guests || 2,
            toyyibpayConfigured: opts.toyyibpayConfigured,
            depositPct: opts.depositPct || 20,
            openBookForm: false,
            bookSubmitting: false,

            /* ── Bottom-nav state ──────────────────────────────────
               navTab is the currently-highlighted dock item. Set
               by the click handlers AND by goBook so the active
               "dot" indicator follows the user's last action.
               galleryOpen/galleryIndex drive the lightbox modal. */
            navTab: 'home',
            galleryOpen: false,
            galleryIndex: 0,

            goHome() {
                this.navTab = 'home';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            goBook() {
                this.navTab = 'book';
                this.scrollToCalendar();
            },
            openGallery() {
                if (!this.current?.photos || this.current.photos.length === 0) return;
                this.navTab = 'gallery';
                this.galleryIndex = 0;
                this.galleryOpen = true;
                /* Prevent background scroll while lightbox is open. */
                document.body.style.overflow = 'hidden';
            },
            closeGallery() {
                this.galleryOpen = false;
                document.body.style.overflow = '';
                /* Don't keep gallery highlighted after close — fall back
                   to whichever section the user was viewing. */
                this.navTab = 'home';
            },
            galleryPrev() {
                const n = this.current?.photos?.length || 0;
                if (n === 0) return;
                this.galleryIndex = (this.galleryIndex - 1 + n) % n;
            },
            galleryNext() {
                const n = this.current?.photos?.length || 0;
                if (n === 0) return;
                this.galleryIndex = (this.galleryIndex + 1) % n;
            },
            /* Touch-swipe between photos. Native mobile gesture — feels
               broken without it ("cannot scroll the images"). Stores the
               starting touch X, then on touchend compares against ending
               X and fires next/prev if horizontal travel exceeds the
               threshold AND wasn't predominantly vertical (avoids
               hijacking scroll). */
            _touchStartX: 0,
            _touchStartY: 0,
            galleryTouchStart(e) {
                if (!e.changedTouches || !e.changedTouches[0]) return;
                this._touchStartX = e.changedTouches[0].clientX;
                this._touchStartY = e.changedTouches[0].clientY;
            },
            galleryTouchEnd(e) {
                if (!e.changedTouches || !e.changedTouches[0]) return;
                const dx = e.changedTouches[0].clientX - this._touchStartX;
                const dy = e.changedTouches[0].clientY - this._touchStartY;
                const threshold = 40; /* px — short enough to feel responsive */
                if (Math.abs(dx) < threshold) return;          /* not a swipe */
                if (Math.abs(dy) > Math.abs(dx)) return;        /* mostly vertical, let scroll happen */
                if (dx < 0) this.galleryNext();                 /* swipe left → next */
                else        this.galleryPrev();                  /* swipe right → prev */
            },
            directionUrl() {
                // Prefer the host-curated pre-pinned URL when they've set one
                // — it lands on the exact pin every time. Fall back to a
                // Maps-search by the free-text address (which can drift for
                // vague kampung addresses but works for anything geocodable).
                if (this.current?.map_url) return this.current.map_url;
                const q = this.current?.address || `${this.current?.name || this.tenantName}, ${this.current?.city || ''}`;
                return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(q)}`;
            },

            get current() { return this.properties[this.selectedIdx] || this.properties[0]; },
            get currentBookedSet() { return new Set(this.current?.booked || []); },
            get weekdayHeader() {
                return this.isBM
                    ? ['Aha','Isn','Sel','Rab','Kha','Jum','Sab']
                    : ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            },

            selectProperty(i) {
                if (i === this.selectedIdx) return;
                this.selectedIdx = i;
                if (this.checkin && this.checkout) {
                    const start = new Date(this.checkin);
                    const end = new Date(this.checkout);
                    const cur = new Date(start);
                    let conflict = false;
                    while (cur < end) {
                        if (this.currentBookedSet.has(this.iso(cur))) { conflict = true; break; }
                        cur.setDate(cur.getDate() + 1);
                    }
                    if (conflict) { this.checkin = null; this.checkout = null; }
                }
                /* Reset guests to the new property's tenant-configured default
                   (or fall back to floor(sleeps/2)) so switching properties
                   doesn't carry over a now-inappropriate count. */
                this.guests = this.current.default_guests
                    || Math.max(1, Math.floor((this.current.sleeps || 2) / 2));
                if (this.guests > (this.current.sleeps || 99)) this.guests = this.current.sleeps || 1;
            },

            iso(d) {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${day}`;
            },
            isPast(d) { return d < this.today; },
            isToday(d) { return d.getTime() === this.today.getTime(); },
            isBooked(d) { return this.currentBookedSet.has(this.iso(d)); },
            isCheckin(d) { return this.iso(d) === this.checkin; },
            isCheckout(d) { return this.iso(d) === this.checkout; },
            inRange(d) {
                if (!this.checkin || !this.checkout) return false;
                const k = this.iso(d);
                return k > this.checkin && k < this.checkout;
            },

            pickDay(d) {
                if (this.isPast(d) || this.isBooked(d)) return;
                const k = this.iso(d);
                if (!this.checkin || (this.checkin && this.checkout)) {
                    this.checkin = k; this.checkout = null;
                    return;
                }
                if (k <= this.checkin) {
                    this.checkin = k; this.checkout = null;
                    return;
                }
                const start = new Date(this.checkin);
                const end = new Date(k);
                const cur = new Date(start); cur.setDate(cur.getDate() + 1);
                let valid = true;
                while (cur < end) {
                    if (this.currentBookedSet.has(this.iso(cur))) { valid = false; break; }
                    cur.setDate(cur.getDate() + 1);
                }
                if (valid) this.checkout = k;
                else       { this.checkin = k; this.checkout = null; }
            },

            monthDays() {
                const c = this.cursor;
                const first = new Date(c.getFullYear(), c.getMonth(), 1);
                const last  = new Date(c.getFullYear(), c.getMonth() + 1, 0);
                const startOffset = first.getDay(); // Sun-first
                const out = [];
                for (let i = 0; i < startOffset; i++) out.push(null);
                for (let d = 1; d <= last.getDate(); d++) out.push(new Date(c.getFullYear(), c.getMonth(), d));
                while (out.length % 7 !== 0) out.push(null);
                return out;
            },
            monthLabel() {
                return this.cursor.toLocaleDateString(this.locale, { month: 'long', year: 'numeric' });
            },
            isCurrentMonth() {
                return this.cursor.getFullYear() === this.today.getFullYear()
                    && this.cursor.getMonth() === this.today.getMonth();
            },
            prevMonth() { if (!this.isCurrentMonth()) this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() - 1, 1); },
            nextMonth() { this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + 1, 1); },

            nights() {
                if (!this.checkin || !this.checkout) return 0;
                return Math.max(1, Math.round((new Date(this.checkout) - new Date(this.checkin)) / 86400000));
            },

            // YYYY-MM-DD for a JS Date (matches the keys in current.rates).
            isoOf(d) {
                if (!d) return '';
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${day}`;
            },

            // Per-date rate from the dynamic-pricing map. Falls back to the
            // "starting from" rate if the date is outside the pre-computed
            // 60-day window (forward navigation past the window).
            rateFor(d) {
                if (!d || !this.current) return 0;
                const iso = this.isoOf(d);
                const rates = this.current.rates || {};
                const r = rates[iso];
                return (typeof r === 'number') ? r : (this.current.rate || 0);
            },

            // Sum per-night rates over the selected range — so weekend
            // surcharges, school-holiday markups, etc. are reflected in
            // the total instead of multiplying nights by a flat rate.
            subtotal() {
                if (!this.checkin || !this.checkout) return 0;
                let total = 0;
                const end = new Date(this.checkout + 'T00:00:00');
                const cur = new Date(this.checkin + 'T00:00:00');
                while (cur < end) {
                    total += this.rateFor(cur);
                    cur.setDate(cur.getDate() + 1);
                }
                return total;
            },

            // Per-booking flat fee (cleaning fee, service fee, etc.).
            // Server-side authoritative — these are display-only previews
            // matching what PublicBookingController + CreateBooking compute
            // from the same `properties.booking_fee_amount` column.
            feeAmount() {
                return Number(this.current?.fee_amount || 0);
            },
            feeLabel() {
                const lbl = (this.current?.fee_label || '').trim();
                if (lbl) return lbl;
                return this.isBM ? 'Yuran tempahan' : 'Booking fee';
            },
            grandTotal() {
                return this.subtotal() + this.feeAmount();
            },

            avgRate() {
                const n = this.nights();
                return n > 0 ? this.subtotal() / n : (this.current?.rate || 0);
            },

            // Pay-now amount — equals the property's booking fee.
            // Mirrors the server-side logic in CreateBooking: when the
            // public flow doesn't pass deposit_pct, the booking fee IS
            // the deposit. Falls back to 20% only if no fee configured.
            depositAmount() {
                const fee = this.feeAmount();
                if (fee > 0) return fee;
                return Math.round(this.grandTotal() * (this.depositPct / 100));
            },
            formatMoney(n) {
                return Number(n || 0).toLocaleString(this.locale, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            },
            fmtPill(iso) {
                if (!iso) return '';
                return new Date(iso + 'T00:00:00').toLocaleDateString(this.locale, { weekday: 'short', day: 'numeric', month: 'short' });
            },

            scrollToCalendar() {
                const el = document.querySelector('.wf-cal');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },

            reserveLink() {
                if (!this.checkin || !this.checkout || !this.phone) return '#';
                const p = this.current;
                const ci = this.fmtPill(this.checkin);
                const co = this.fmtPill(this.checkout);
                const n  = this.nights();
                const total = this.formatMoney(this.grandTotal());
                const msg = this.isBM
                    ? `Salam ${this.tenantName}! Saya nak tempah ${p.name}: ${ci} → ${co} (${n} malam), ${this.guests} tetamu. Jumlah anggaran RM ${total}. Boleh sahkan?`
                    : `Hi ${this.tenantName}! I'd like to book ${p.name}: ${ci} → ${co} (${n} ${n === 1 ? 'night' : 'nights'}), ${this.guests} guests. Estimated total RM ${total}. Could you confirm availability?`;
                return `https://wa.me/${this.phone}?text=${encodeURIComponent(msg)}`;
            },
        };
    }
</script>

</body>
</html>
