{{--
    First-time onboarding walkthrough — Mobbin-style anchored popover.
    Each step (other than welcome/finish) targets a sidebar nav item
    via [data-tour="..."]: a spotlight ring is drawn around the target,
    and the card is anchored next to it. On mobile the sidebar drawer
    auto-opens for the targeted steps and the card docks at the
    bottom of the viewport. Renders only when users.tour_completed_at
    is null. POST to /dashboard/onboarding/complete stamps it.
--}}
@php
    // Each step ships both EN + BM copy; the Alpine `lang` state picks
    // which one to render. Default = 'en', tenant can flip to 'ms' via
    // the toggle on the welcome step. Toggle does NOT change app locale.
    $steps = [
        // step 0 — welcome (centered, no target)
        [
            'eyebrow' => ['en' => 'Welcome',                       'ms' => 'Selamat datang'],
            'title'   => ['en' => 'Welcome to Tempahlah!',         'ms' => 'Selamat datang ke Tempahlah!'],
            'body'    => ['en' => "Quick walkthrough — we'll point out each part of the app so you know where everything lives.",
                          'ms' => "Walkthrough ringkas — kami akan tunjuk setiap bahagian app supaya anda tahu di mana semua benda."],
            'icon'    => 'wave',
            'target'  => null,
        ],
        [
            'eyebrow' => ['en' => 'Dashboard', 'ms' => 'Dashboard'],
            'title'   => ['en' => 'Your home base', 'ms' => 'Pusat operasi anda'],
            'body'    => ['en' => 'See revenue, active bookings and quick stats the moment you log in.',
                          'ms' => 'Lihat hasil, tempahan aktif dan statistik penting sebaik sahaja anda log masuk.'],
            'icon'    => 'dashboard',
            'target'  => '[data-tour="dashboard"]',
        ],
        [
            'eyebrow' => ['en' => 'Calendar', 'ms' => 'Kalendar'],
            'title'   => ['en' => 'Every night at a glance', 'ms' => 'Setiap malam dalam satu pandangan'],
            'body'    => ['en' => 'Tap any date to see who is checking in, who is leaving, and which rooms are free.',
                          'ms' => 'Ketuk mana-mana tarikh untuk lihat siapa daftar masuk, siapa keluar, dan bilik mana yang kosong.'],
            'icon'    => 'calendar',
            'target'  => '[data-tour="calendar"]',
        ],
        [
            'eyebrow' => ['en' => 'Bookings', 'ms' => 'Tempahan'],
            'title'   => ['en' => 'Take reservations your way', 'ms' => 'Terima tempahan ikut cara anda'],
            'body'    => ['en' => 'Add walk-in, WhatsApp or marketplace bookings — Tempahlah handles deposits and reminders for you.',
                          'ms' => 'Tambah tempahan walk-in, WhatsApp atau marketplace — Tempahlah uruskan deposit & peringatan untuk anda.'],
            'icon'    => 'bookings',
            'target'  => '[data-tour="bookings"]',
        ],
        [
            'eyebrow' => ['en' => 'Properties', 'ms' => 'Homestay'],
            'title'   => ['en' => 'List your homestays', 'ms' => 'Senaraikan homestay anda'],
            'body'    => ['en' => 'Upload photos, set prices, mark amenities — your direct booking page updates instantly.',
                          'ms' => 'Muat naik gambar, tetapkan harga, tanda kemudahan — laman tempahan terus anda dikemaskini serta-merta.'],
            'icon'    => 'properties',
            'target'  => '[data-tour="properties"]',
        ],
        [
            'eyebrow' => ['en' => 'Guests', 'ms' => 'Tetamu'],
            'title'   => ['en' => 'Know your guests', 'ms' => 'Kenali tetamu anda'],
            'body'    => ['en' => 'Track repeat guests, contact details and lifetime spend. Blacklist troublemakers in one tap.',
                          'ms' => 'Jejak tetamu berulang, butiran hubungan dan jumlah perbelanjaan. Senarai hitam tetamu bermasalah dengan satu ketukan.'],
            'icon'    => 'guests',
            'target'  => '[data-tour="guests"]',
        ],
        [
            'eyebrow' => ['en' => 'Housekeeping', 'ms' => 'Housekeeping'],
            'title'   => ['en' => 'Auto-schedule turnover', 'ms' => 'Jadual pertukaran automatik'],
            'body'    => ['en' => 'Cleaning + laundry tasks generate themselves from every confirmed booking — no double-entry.',
                          'ms' => 'Tugas pembersihan + dobi terjana sendiri dari setiap tempahan disahkan — tiada input berganda.'],
            'icon'    => 'sparkle',
            'target'  => '[data-tour="housekeeping"]',
        ],
        [
            'eyebrow' => ['en' => 'Reports', 'ms' => 'Laporan'],
            'title'   => ['en' => 'Know what is working', 'ms' => 'Tahu apa yang berjaya'],
            'body'    => ['en' => 'Trailing-12-month revenue, occupancy and per-property breakdowns. Export PDF or CSV anytime.',
                          'ms' => 'Hasil 12 bulan terkini, kadar penghunian dan pecahan setiap homestay. Eksport PDF atau CSV bila-bila masa.'],
            'icon'    => 'reports',
            'target'  => '[data-tour="reports"]',
        ],
        [
            'eyebrow' => ['en' => 'Settings', 'ms' => 'Tetapan'],
            'title'   => ['en' => 'Make it yours', 'ms' => 'Sesuaikan ikut anda'],
            'body'    => ['en' => 'Brand colours, SST, locale and your public URL — all in one place.',
                          'ms' => 'Warna jenama, SST, bahasa dan URL awam anda — semuanya di satu tempat.'],
            'icon'    => 'settings',
            'target'  => '[data-tour="settings"]',
        ],
        // last step — finish (centered, CTA to add first property)
        [
            'eyebrow' => ['en' => 'You are set', 'ms' => 'Anda dah sedia'],
            'title'   => ['en' => "Let's get started!", 'ms' => 'Mari mulakan!'],
            'body'    => ['en' => 'Add your first property to start taking bookings. You can always replay this tour from Settings.',
                          'ms' => 'Tambah homestay pertama anda untuk mula terima tempahan. Anda boleh ulang tour ini dari Tetapan bila-bila masa.'],
            'icon'    => 'rocket',
            'target'  => null,
        ],
    ];

    // UI labels — same bilingual map, rendered via Alpine.
    $labels = [
        'skip'    => ['en' => 'Skip',          'ms' => 'Langkau'],
        'next'    => ['en' => 'Next',          'ms' => 'Seterusnya'],
        'finish'  => ['en' => 'Add property',  'ms' => 'Tambah homestay'],
        'close'   => ['en' => 'Close',         'ms' => 'Tutup'],
        'lang'    => ['en' => 'Language',      'ms' => 'Bahasa'],
        'progress'=> ['en' => 'Progress',      'ms' => 'Kemajuan'],
    ];
@endphp

<div
    x-data="onboardingTour({
        total: {{ count($steps) }},
        completeUrl: '{{ route('tenant.onboarding.complete') }}',
        csrf: '{{ csrf_token() }}',
    })"
    x-cloak
    x-show="open"
    x-transition.opacity.duration.200ms
    @keydown.escape.window="dismiss()"
    @resize.window.debounce.100ms="position()"
    @scroll.window.debounce.50ms="position()"
    class="ob-root"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ob-title">

    {{-- Backdrop: full dim when untargeted, transparent (spotlight handles dim) when targeted --}}
    <div class="ob-backdrop" :class="targeted ? 'is-clear' : 'is-dim'" @click="dismiss()"></div>

    {{-- Spotlight ring around the target element. Box-shadow trick:
         a small div positioned over the target gets an enormous
         outset shadow that paints the rest of the viewport dim. --}}
    <div class="ob-spotlight"
         x-show="targeted && ready"
         x-transition.opacity.duration.200ms
         :style="spotStyle"></div>

    {{-- The popover card --}}
    <div class="ob-card"
         :class="{ 'is-center': !targeted }"
         :style="cardStyle"
         x-show="ready || !targeted"
         x-transition:enter="ob-anim-in"
         x-transition:enter-start="ob-anim-in-from"
         x-transition:enter-end="ob-anim-in-to">

        <button type="button" class="ob-close" @click="dismiss()" :aria-label="labels.close[lang]">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>

        <div class="ob-head">
            <div class="ob-chip">
                <template x-if="steps[step].icon === 'wave'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11l2 4 3-7 2 5 2-3"/></svg>
                </template>
                <template x-if="steps[step].icon === 'dashboard'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                </template>
                <template x-if="steps[step].icon === 'calendar'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                </template>
                <template x-if="steps[step].icon === 'bookings'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h14v17l-3-2-3 2-2-2-3 2-3-2V4z"/><path d="M9 9h6M9 13h6"/></svg>
                </template>
                <template x-if="steps[step].icon === 'properties'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7v9a1 1 0 01-1 1H4a1 1 0 01-1-1v-9z"/><path d="M9 21V13h6v8"/></svg>
                </template>
                <template x-if="steps[step].icon === 'guests'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="3.5"/><circle cx="17" cy="10" r="2.6"/><path d="M3 19c0-3 3-5 6-5s6 2 6 5"/><path d="M15 19c0-2 2-4 4-4s2 2 2 4"/></svg>
                </template>
                <template x-if="steps[step].icon === 'sparkle'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2 2M16 16l2 2M6 18l2-2M16 8l2-2"/></svg>
                </template>
                <template x-if="steps[step].icon === 'reports'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16"/><rect x="6" y="11" width="3" height="9" rx="1"/><rect x="11" y="6" width="3" height="14" rx="1"/><rect x="16" y="14" width="3" height="6" rx="1"/></svg>
                </template>
                <template x-if="steps[step].icon === 'settings'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19 12l2-1-1-3-2 1-2-1V5h-3v2l-2 1-2-1-2-1-1 3 2 1v2l-2 1 1 3 2-1 2 1v2h3v-2l2-1 2 1 1-3-2-1v-2z"/></svg>
                </template>
                <template x-if="steps[step].icon === 'rocket'">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c4 4 6 8 6 12 0 3-2 5-3 6l-3-2-3 2c-1-1-3-3-3-6 0-4 2-8 6-12z"/><circle cx="12" cy="10" r="1.5"/></svg>
                </template>
            </div>
            <div class="ob-eyebrow" x-text="steps[step].eyebrow[lang]"></div>

            {{-- EN / BM toggle. Only on the welcome step — once they pick,
                 the choice persists for the rest of the tour. --}}
            <div class="ob-lang" x-show="step === 0" role="group" :aria-label="labels.lang[lang]">
                <button type="button"
                        class="ob-lang-pill"
                        :class="lang === 'en' ? 'is-active' : ''"
                        @click="lang = 'en'"
                        aria-pressed="true"
                        :aria-pressed="lang === 'en'">EN</button>
                <button type="button"
                        class="ob-lang-pill"
                        :class="lang === 'ms' ? 'is-active' : ''"
                        @click="lang = 'ms'"
                        :aria-pressed="lang === 'ms'">BM</button>
            </div>
        </div>

        <h2 id="ob-title" class="ob-title" x-text="steps[step].title[lang]"></h2>
        <p class="ob-text" x-text="steps[step].body[lang]"></p>

        <div class="ob-foot">
            <div class="ob-dots" role="tablist" :aria-label="labels.progress[lang]">
                <template x-for="i in total" :key="i">
                    <button type="button"
                            class="ob-dot"
                            :class="(i - 1) === step ? 'is-active' : ((i - 1) < step ? 'is-done' : '')"
                            @click="goto(i - 1)"
                            :aria-label="`Step ${i}`"
                            :aria-current="(i - 1) === step ? 'step' : 'false'"></button>
                </template>
            </div>

            <div class="ob-actions">
                <button type="button" class="ob-skip" @click="dismiss()" x-show="step < total - 1" x-text="labels.skip[lang]"></button>

                <template x-if="step < total - 1">
                    <button type="button" class="ob-cta" @click="next()">
                        <span x-text="labels.next[lang]"></span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px;"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </template>
                <template x-if="step === total - 1">
                    <a href="{{ route('tenant.properties.create') }}" class="ob-cta" @click="finish()">
                        <span x-text="labels.finish[lang]"></span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px;"><path d="M12 5v14M5 12h14"/></svg>
                    </a>
                </template>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    .ob-root { position: fixed; inset: 0; z-index: 1000; pointer-events: none; }
    .ob-root > * { pointer-events: auto; }

    .ob-backdrop { position: absolute; inset: 0; transition: background-color 220ms ease, backdrop-filter 220ms ease; }
    .ob-backdrop.is-dim   { background: color-mix(in oklab, var(--ink) 55%, transparent); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
    .ob-backdrop.is-clear { background: transparent; pointer-events: none; } /* spotlight box-shadow paints the dim */

    /* Spotlight ring — sits over the target with a huge outset shadow */
    .ob-spotlight {
        position: fixed;
        border-radius: 12px;
        pointer-events: none;
        box-shadow:
            0 0 0 9999px color-mix(in oklab, var(--ink) 55%, transparent),
            0 0 0 2px var(--primary),
            0 0 0 6px color-mix(in oklab, var(--primary) 25%, transparent);
        transition: top 320ms cubic-bezier(.4,.2,.2,1), left 320ms cubic-bezier(.4,.2,.2,1),
                    width 320ms cubic-bezier(.4,.2,.2,1), height 320ms cubic-bezier(.4,.2,.2,1);
    }

    /* The card */
    .ob-card {
        position: fixed;
        width: 340px; max-width: calc(100vw - 24px);
        background: var(--bg-elev);
        color: var(--ink);
        border-radius: 18px;
        box-shadow: 0 24px 60px -16px rgba(0, 0, 0, 0.32), 0 0 0 1px var(--line);
        padding: 18px 18px 14px;
        transition: top 320ms cubic-bezier(.4,.2,.2,1), left 320ms cubic-bezier(.4,.2,.2,1);
    }
    .ob-card.is-center {
        top: 50% !important; left: 50% !important;
        transform: translate(-50%, -50%);
        width: 380px;
    }

    .ob-anim-in { transition: transform 260ms cubic-bezier(.2,.9,.3,1.2), opacity 200ms ease; }
    .ob-anim-in-from { opacity: 0; transform: translateY(8px) scale(.96); }
    .ob-anim-in-to   { opacity: 1; transform: translateY(0) scale(1); }
    .ob-card.is-center.ob-anim-in-from { transform: translate(-50%, -46%) scale(.96); }
    .ob-card.is-center.ob-anim-in-to   { transform: translate(-50%, -50%) scale(1); }

    .ob-close {
        position: absolute; top: 10px; right: 10px;
        width: 26px; height: 26px;
        display: inline-flex; align-items: center; justify-content: center;
        background: transparent; color: var(--ink-3);
        border: 0; border-radius: 8px; cursor: pointer;
        transition: background 140ms ease, color 140ms ease;
        touch-action: manipulation;
    }
    .ob-close:hover { background: var(--bg-sunk); color: var(--ink); }

    .ob-head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

    /* EN / BM toggle — sits at the right end of the head row on step 0 */
    .ob-lang {
        margin-left: auto;
        display: inline-flex;
        padding: 2px;
        background: var(--bg-sunk);
        border: 1px solid var(--line);
        border-radius: 999px;
    }
    .ob-lang-pill {
        padding: 3px 9px;
        font-size: 11px; font-weight: 700; letter-spacing: 0.04em;
        color: var(--ink-3);
        background: transparent; border: 0; border-radius: 999px;
        cursor: pointer;
        touch-action: manipulation;
        transition: background 140ms ease, color 140ms ease;
        min-height: 22px;
    }
    .ob-lang-pill:hover { color: var(--ink); }
    .ob-lang-pill.is-active {
        background: var(--bg-elev);
        color: var(--primary);
        box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    }
    .ob-chip {
        width: 36px; height: 36px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px;
        background: var(--primary-tint);
        color: var(--primary-deep);
        flex-shrink: 0;
    }
    .ob-eyebrow {
        font-size: 10.5px; font-weight: 700; letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--primary);
    }

    .ob-title {
        font-size: 17px; font-weight: 700; line-height: 1.3;
        letter-spacing: -0.005em;
        color: var(--ink);
        margin: 0 0 6px;
    }
    .ob-text {
        font-size: 14px; line-height: 1.5;
        color: var(--ink-2);
        margin: 0 0 16px;
    }

    .ob-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; }

    .ob-dots { display: flex; align-items: center; gap: 5px; }
    .ob-dot {
        width: 6px; height: 6px; border-radius: 999px;
        border: 0; padding: 0;
        background: var(--line-2);
        cursor: pointer;
        transition: width 200ms ease, background 200ms ease;
        touch-action: manipulation;
    }
    .ob-dot.is-done   { background: color-mix(in oklab, var(--primary) 50%, transparent); }
    .ob-dot.is-active { background: var(--primary); width: 18px; }

    .ob-actions { display: flex; align-items: center; gap: 8px; }
    .ob-skip {
        background: transparent; border: 0;
        font-size: 13px; font-weight: 600;
        color: var(--ink-3); cursor: pointer;
        padding: 8px 6px;
        touch-action: manipulation;
    }
    .ob-skip:hover { color: var(--ink); }

    .ob-cta {
        display: inline-flex; align-items: center; justify-content: center;
        padding: 9px 16px;
        font-size: 14px; font-weight: 700;
        color: #fff; text-decoration: none;
        background: linear-gradient(180deg, var(--primary), var(--primary-deep));
        border: 0; border-radius: 999px;
        box-shadow: 0 6px 14px -4px color-mix(in oklab, var(--primary) 55%, transparent), inset 0 1px 0 rgba(255,255,255,0.15);
        cursor: pointer;
        transition: transform 140ms ease, filter 140ms ease;
        touch-action: manipulation;
        min-height: 38px;
    }
    .ob-cta:hover  { transform: translateY(-1px); filter: brightness(1.04); }
    .ob-cta:active { transform: translateY(0); }

    /* Mobile — keep card readable, dock at viewport bottom for targeted
       steps so it never sits on top of the spotlighted nav item. */
    @media (max-width: 768px) {
        .ob-card        { width: calc(100vw - 24px); max-width: 420px; padding: 16px; }
        .ob-card.is-center { width: calc(100vw - 24px); }
        .ob-title       { font-size: 16px; }
        .ob-text        { font-size: 14px; }
        .ob-cta         { font-size: 14px; min-height: 40px; padding: 10px 16px; }
        /* >=16px on actual inputs is the iOS auto-zoom rule; buttons are exempt. */
    }

    @media (prefers-reduced-motion: reduce) {
        .ob-spotlight, .ob-card { transition: none; }
        .ob-anim-in { transition: none; }
    }
</style>

<script>
    // Alpine component — target-aware tour. Each step's `target`
    // selector is queried at runtime; we draw a spotlight ring around
    // the element and anchor the card next to it. Re-runs on resize.
    window.onboardingTour = function ({ total, completeUrl, csrf }) {
        return {
            open: true,
            step: 0,
            total,
            steps: @json($steps),
            labels: @json($labels),
            // Tour starts in English by default; user can flip to BM on
            // the welcome step via the EN/BM pill. Choice persists for
            // the rest of the tour. Doesn't touch the app's locale.
            lang: 'en',
            ready: false,
            targeted: false,
            spotStyle: '',
            cardStyle: '',

            init() {
                // Tell the layout's sidebar listener to ignore click-away
                // while the tour drives the drawer state.
                window.__tempahlahTourActive = true;
                // Defer first position calc so the modal can animate in.
                this.$nextTick(() => this.position());
                // Re-position on viewport changes (orientation, soft kb).
                this._onResize = () => this.position();
                window.addEventListener('orientationchange', this._onResize);
            },

            destroy() {
                window.__tempahlahTourActive = false;
                window.removeEventListener('orientationchange', this._onResize);
                this.setSidebar(false);
            },

            isMobile() { return window.innerWidth < 1024; },

            setSidebar(open) {
                window.dispatchEvent(new CustomEvent('tour-set-sidebar', { detail: { open } }));
            },

            async position() {
                const s = this.steps[this.step];
                this.targeted = !!s.target;

                if (!s.target) {
                    // Centered step — no spotlight, hide drawer if we
                    // opened it for a previous targeted step on mobile.
                    if (this.isMobile()) this.setSidebar(false);
                    this.spotStyle = '';
                    this.cardStyle = '';
                    this.ready = true;
                    return;
                }

                // Targeted step — open the drawer on mobile so the
                // sidebar nav item we're highlighting is actually visible.
                if (this.isMobile()) {
                    this.setSidebar(true);
                    // Wait two frames for the drawer transform to settle.
                    await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
                }

                const target = document.querySelector(s.target);
                if (!target) {
                    // Fallback: target missing (e.g., sidebar not rendered)
                    // — degrade to centered card without spotlight.
                    this.targeted = false;
                    this.spotStyle = '';
                    this.cardStyle = '';
                    this.ready = true;
                    return;
                }

                // Make sure the target is within scroll.
                target.scrollIntoView({ block: 'center', behavior: 'instant' });

                const rect = target.getBoundingClientRect();
                const pad = 6;
                const spotTop    = Math.round(rect.top - pad);
                const spotLeft   = Math.round(rect.left - pad);
                const spotWidth  = Math.round(rect.width + pad * 2);
                const spotHeight = Math.round(rect.height + pad * 2);
                this.spotStyle = `top:${spotTop}px; left:${spotLeft}px; width:${spotWidth}px; height:${spotHeight}px;`;

                // Card placement.
                const vw = window.innerWidth;
                const vh = window.innerHeight;
                const cardW = Math.min(340, vw - 24);
                const cardH = 220; // estimate; box-sizing handles real height
                const margin = 12;

                let top, left;

                if (this.isMobile()) {
                    // Dock card at bottom of viewport so it never
                    // overlaps the spotlight. Bottom-nav is ~64px tall
                    // on mobile, plus safe-area, plus our own margin.
                    left = Math.max(12, (vw - cardW) / 2);
                    top = vh - cardH - 84;
                    // If target is in the lower half, place card at TOP
                    // of viewport instead — keeps spotlight visible.
                    if (rect.top > vh / 2) {
                        top = 16;
                    }
                } else {
                    // Desktop: try placing card to the RIGHT of the
                    // sidebar item, vertically centered on it.
                    left = rect.right + margin;
                    top  = rect.top + (rect.height / 2) - (cardH / 2);

                    // If no room to the right, try below.
                    if (left + cardW > vw - 16) {
                        left = Math.max(16, rect.left);
                        top  = rect.bottom + margin;
                    }
                    // If no room below either, place above.
                    if (top + cardH > vh - 16) {
                        top = Math.max(16, rect.top - cardH - margin);
                    }
                    // Final clamp.
                    left = Math.min(Math.max(16, left), vw - cardW - 16);
                    top  = Math.min(Math.max(16, top),  vh - cardH - 16);
                }

                this.cardStyle = `top:${Math.round(top)}px; left:${Math.round(left)}px;`;
                this.ready = true;
            },

            next() {
                if (this.step < this.total - 1) {
                    this.ready = false;
                    this.step += 1;
                    this.$nextTick(() => this.position());
                } else {
                    this.finish();
                }
            },
            goto(i) {
                if (i < 0 || i >= this.total || i === this.step) return;
                this.ready = false;
                this.step = i;
                this.$nextTick(() => this.position());
            },
            dismiss() { this.markComplete(); this.open = false; this.destroy(); },
            finish()  { this.markComplete(); /* anchor navigates */ },

            markComplete() {
                try {
                    fetch(completeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                        keepalive: true,
                    }).catch(() => {});
                } catch (e) { /* network blocked — tour re-shows next login, acceptable */ }
            },
        };
    };
</script>
