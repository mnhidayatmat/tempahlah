{{--
    First-time onboarding walkthrough — Mobbin/Peloton-style centered
    modal. Renders only for users with NULL tour_completed_at. POSTing
    to /dashboard/onboarding/complete stamps the column so we never
    re-show. Self-contained: Alpine x-data + scoped styles. Works
    mobile + desktop (modal scales down to phone width at < 480px).
--}}
@php
    $steps = [
        [
            'eyebrow' => __('Welcome'),
            'title'   => __('Selamat datang ke Tempahlah!'),
            'body'    => __("We'll walk you through Tempahlah in under a minute so you know where everything lives."),
            'icon'    => 'wave',
        ],
        [
            'eyebrow' => __('Dashboard'),
            'title'   => __('Your home base'),
            'body'    => __('See revenue, active bookings and quick stats the moment you log in.'),
            'icon'    => 'dashboard',
        ],
        [
            'eyebrow' => __('Calendar'),
            'title'   => __('Every night at a glance'),
            'body'    => __('Tap any date to see who is checking in, who is leaving, and which rooms are free.'),
            'icon'    => 'calendar',
        ],
        [
            'eyebrow' => __('Bookings'),
            'title'   => __('Take reservations your way'),
            'body'    => __('Add walk-in, WhatsApp or marketplace bookings — Tempahlah handles deposits, tax and reminders for you.'),
            'icon'    => 'bookings',
        ],
        [
            'eyebrow' => __('Properties'),
            'title'   => __('List your homestays'),
            'body'    => __('Upload photos, set prices, mark amenities — your direct booking page updates instantly.'),
            'icon'    => 'properties',
        ],
        [
            'eyebrow' => __('Guests & Housekeeping'),
            'title'   => __('Run the day-to-day'),
            'body'    => __('Track repeat guests, blacklist troublemakers, and auto-schedule cleaning + laundry after every checkout.'),
            'icon'    => 'guests',
        ],
        [
            'eyebrow' => __('Reports & Settings'),
            'title'   => __('Know what is working'),
            'body'    => __('Trailing-12-month revenue, occupancy and per-property breakdowns. Tweak SST, brand and locale in Settings.'),
            'icon'    => 'reports',
        ],
        [
            'eyebrow' => __('You are set'),
            'title'   => __('Mari mulakan!'),
            'body'    => __('Add your first property to start taking bookings. We are here in the bottom-right if you need help.'),
            'icon'    => 'rocket',
            'ctaLabel'  => __('Add my first property'),
            'ctaRoute'  => 'tenant.properties.create',
        ],
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
    class="ob-overlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ob-title">

    <div class="ob-card"
         x-show="open"
         x-transition:enter="ob-anim-in"
         x-transition:enter-start="ob-anim-in-from"
         x-transition:enter-end="ob-anim-in-to"
         @click.outside="dismiss()">

        {{-- Close X --}}
        <button type="button"
                class="ob-close"
                @click="dismiss()"
                aria-label="{{ __('Close') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>

        {{-- Illustration column — gradient block with a per-step inline SVG --}}
        <div class="ob-illus">
            <template x-if="step === 0">
                {{-- wave --}}
                <svg viewBox="0 0 120 120" width="120" height="120" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="60" cy="60" r="46" stroke-opacity=".25" stroke-width="2"/>
                    <path d="M40 56l8 16a4 4 0 007.2 0L62 56l5 12 7-22 8 20"/>
                    <circle cx="60" cy="34" r="8" fill="#fff" stroke="none"/>
                </svg>
            </template>
            <template x-if="step === 1">
                {{-- dashboard chart --}}
                <svg viewBox="0 0 120 120" width="120" height="120" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="20" y="20" width="80" height="80" rx="10" stroke-opacity=".3" stroke-width="2"/>
                    <path d="M32 82l16-22 14 12 24-32"/>
                    <circle cx="32" cy="82" r="3.5" fill="#fff"/>
                    <circle cx="48" cy="60" r="3.5" fill="#fff"/>
                    <circle cx="62" cy="72" r="3.5" fill="#fff"/>
                    <circle cx="86" cy="40" r="3.5" fill="#fff"/>
                </svg>
            </template>
            <template x-if="step === 2">
                {{-- calendar --}}
                <svg viewBox="0 0 120 120" width="120" height="120" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="20" y="28" width="80" height="72" rx="8"/>
                    <path d="M20 48h80M40 20v16M80 20v16"/>
                    <rect x="56" y="66" width="14" height="14" rx="3" fill="#fff" stroke="none"/>
                </svg>
            </template>
            <template x-if="step === 3">
                {{-- bookings receipt --}}
                <svg viewBox="0 0 120 120" width="120" height="120" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M30 16h60v88l-10-6-10 6-10-6-10 6-10-6-10 6V16z"/>
                    <path d="M44 42h32M44 58h32M44 74h20" stroke-opacity=".7"/>
                </svg>
            </template>
            <template x-if="step === 4">
                {{-- house --}}
                <svg viewBox="0 0 120 120" width="120" height="120" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 60L60 28l38 32v40a4 4 0 01-4 4H26a4 4 0 01-4-4V60z"/>
                    <path d="M50 104V72h20v32" />
                </svg>
            </template>
            <template x-if="step === 5">
                {{-- guests --}}
                <svg viewBox="0 0 120 120" width="120" height="120" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="48" cy="48" r="14"/>
                    <circle cx="80" cy="54" r="11" stroke-opacity=".7"/>
                    <path d="M24 96c0-12 11-20 24-20s24 8 24 20"/>
                    <path d="M72 96c0-9 7-16 18-16s14 6 14 16" stroke-opacity=".7"/>
                </svg>
            </template>
            <template x-if="step === 6">
                {{-- bars + line --}}
                <svg viewBox="0 0 120 120" width="120" height="120" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 100h76"/>
                    <rect x="32" y="64" width="12" height="36" rx="2" fill="#fff" fill-opacity=".4"/>
                    <rect x="54" y="46" width="12" height="54" rx="2" fill="#fff" fill-opacity=".6"/>
                    <rect x="76" y="30" width="12" height="70" rx="2" fill="#fff" fill-opacity=".85"/>
                </svg>
            </template>
            <template x-if="step === 7">
                {{-- rocket / sparkle --}}
                <svg viewBox="0 0 120 120" width="120" height="120" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M60 16c14 12 22 28 22 46 0 10-4 18-10 24l-12-8-12 8c-6-6-10-14-10-24 0-18 8-34 22-46z"/>
                    <circle cx="60" cy="56" r="6" fill="#fff" stroke="none"/>
                    <path d="M44 92l-8 12M76 92l8 12M60 96v14"/>
                </svg>
            </template>
        </div>

        {{-- Body --}}
        <div class="ob-body">
            <div class="ob-eyebrow" x-text="steps[step].eyebrow"></div>
            <h2 id="ob-title" class="ob-title" x-text="steps[step].title"></h2>
            <p class="ob-text" x-text="steps[step].body"></p>

            {{-- Dot progress --}}
            <div class="ob-dots" role="tablist" aria-label="{{ __('Progress') }}">
                <template x-for="i in total" :key="i">
                    <button type="button"
                            class="ob-dot"
                            :class="(i - 1) === step ? 'is-active' : ((i - 1) < step ? 'is-done' : '')"
                            @click="goto(i - 1)"
                            :aria-label="`Step ${i}`"
                            :aria-current="(i - 1) === step ? 'step' : 'false'"></button>
                </template>
            </div>

            {{-- Primary CTA --}}
            <template x-if="step < total - 1">
                <button type="button" class="ob-cta" @click="next()">
                    <span>{{ __('Next') }}</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-left:8px;"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </template>
            <template x-if="step === total - 1">
                <a href="{{ route('tenant.properties.create') }}"
                   class="ob-cta"
                   @click="finish()">
                    <span>{{ __('Add my first property') }}</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-left:8px;"><path d="M12 5v14M5 12h14"/></svg>
                </a>
            </template>

            {{-- Secondary action --}}
            <button type="button" class="ob-skip" @click="dismiss()" x-text="step < total - 1 ? '{{ __('Skip tour') }}' : '{{ __('Done') }}'"></button>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    .ob-overlay {
        position: fixed; inset: 0; z-index: 1000;
        background: color-mix(in oklab, var(--ink) 55%, transparent);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        display: flex; align-items: center; justify-content: center;
        padding: 16px;
    }

    .ob-card {
        position: relative;
        width: 100%; max-width: 420px;
        background: var(--bg-elev);
        color: var(--ink);
        border-radius: 24px;
        box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.35), 0 0 0 1px var(--line);
        overflow: hidden;
        max-height: calc(100dvh - 32px);
        overflow-y: auto;
    }

    .ob-anim-in { transition: transform 280ms cubic-bezier(.2,.9,.3,1.2), opacity 220ms ease; }
    .ob-anim-in-from { opacity: 0; transform: translateY(20px) scale(.94); }
    .ob-anim-in-to   { opacity: 1; transform: translateY(0) scale(1); }

    .ob-close {
        position: absolute; top: 14px; right: 14px; z-index: 2;
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,0.18);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.28);
        border-radius: 999px;
        cursor: pointer;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        transition: background 140ms ease, transform 140ms ease;
        touch-action: manipulation;
    }
    .ob-close:hover { background: rgba(255,255,255,0.3); transform: scale(1.05); }

    /* Illustration block — teal gradient using brand tokens */
    .ob-illus {
        height: 200px;
        display: flex; align-items: center; justify-content: center;
        background:
            radial-gradient(circle at 25% 25%, color-mix(in oklab, var(--secondary) 65%, transparent), transparent 55%),
            radial-gradient(circle at 80% 80%, color-mix(in oklab, var(--primary-deep) 75%, transparent), transparent 55%),
            linear-gradient(135deg, var(--primary) 0%, var(--primary-deep) 100%);
        position: relative;
        overflow: hidden;
    }
    .ob-illus::after {
        content: ''; position: absolute; inset: 0;
        background-image: repeating-linear-gradient(45deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 14px);
        pointer-events: none;
    }
    .ob-illus svg { position: relative; z-index: 1; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.18)); }

    .ob-body { padding: 28px 28px 24px; text-align: center; }

    .ob-eyebrow {
        font-size: 11px; font-weight: 700; letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--primary);
        margin-bottom: 10px;
    }
    .ob-title {
        font-size: 22px; font-weight: 800; line-height: 1.2;
        letter-spacing: -0.01em;
        color: var(--ink);
        margin: 0 0 10px;
    }
    .ob-text {
        font-size: 15px; line-height: 1.55;
        color: var(--ink-2);
        margin: 0 0 22px;
    }

    .ob-dots {
        display: flex; align-items: center; justify-content: center;
        gap: 6px; margin-bottom: 20px;
    }
    .ob-dot {
        width: 8px; height: 8px; border-radius: 999px;
        border: 0; padding: 0;
        background: var(--line-2);
        cursor: pointer;
        transition: width 200ms ease, background 200ms ease, transform 200ms ease;
        touch-action: manipulation;
    }
    .ob-dot:hover { transform: scale(1.2); }
    .ob-dot.is-done   { background: color-mix(in oklab, var(--primary) 50%, transparent); }
    .ob-dot.is-active { background: var(--primary); width: 22px; }

    .ob-cta {
        display: inline-flex; align-items: center; justify-content: center;
        width: 100%;
        padding: 14px 20px;
        font-size: 16px; font-weight: 700;
        color: #fff; text-decoration: none;
        background: linear-gradient(180deg, var(--primary), var(--primary-deep));
        border: 0; border-radius: 999px;
        box-shadow: 0 10px 20px -8px color-mix(in oklab, var(--primary) 60%, transparent), inset 0 1px 0 rgba(255,255,255,0.15);
        cursor: pointer;
        transition: transform 140ms ease, box-shadow 140ms ease, filter 140ms ease;
        touch-action: manipulation;
        min-height: 48px;
    }
    .ob-cta:hover  { transform: translateY(-1px); filter: brightness(1.04); }
    .ob-cta:active { transform: translateY(0); }

    .ob-skip {
        display: block; width: 100%;
        margin-top: 14px;
        padding: 10px;
        background: transparent; border: 0;
        font-size: 14px; font-weight: 600;
        color: var(--ink-3);
        cursor: pointer;
        touch-action: manipulation;
        min-height: 44px;
    }
    .ob-skip:hover { color: var(--ink); }

    /* Mobile tuning — keep readable but tighter padding */
    @media (max-width: 480px) {
        .ob-overlay { padding: 12px; }
        .ob-card    { border-radius: 22px; }
        .ob-illus   { height: 170px; }
        .ob-illus svg { width: 100px; height: 100px; }
        .ob-body    { padding: 24px 22px 22px; }
        .ob-title   { font-size: 20px; }
        .ob-text    { font-size: 14.5px; }
        .ob-cta     { font-size: 16px; padding: 13px 18px; } /* >=16px keeps iOS auto-zoom off */
    }

    /* Dark-mode polish — keep the teal hero, lift the card surface */
    html[data-theme="dark"] .ob-card {
        box-shadow: 0 30px 80px -20px rgba(0,0,0,0.6), 0 0 0 1px var(--line);
    }

    @media (prefers-reduced-motion: reduce) {
        .ob-anim-in { transition: none; }
        .ob-cta, .ob-dot, .ob-close { transition: none; }
    }
</style>

<script>
    // Alpine component — exposed via window so layout doesn't need a module build step.
    // Posts to /dashboard/onboarding/complete on dismiss/finish to flip
    // users.tour_completed_at = now() so the modal never returns.
    window.onboardingTour = function ({ total, completeUrl, csrf }) {
        return {
            open: true,
            step: 0,
            total,
            steps: @json($steps),
            next() {
                if (this.step < this.total - 1) {
                    this.step += 1;
                } else {
                    this.finish();
                }
            },
            goto(i) { if (i >= 0 && i < this.total) this.step = i; },
            dismiss() { this.markComplete(); this.open = false; },
            finish()  { this.markComplete(); /* navigation happens via the anchor href */ },
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
                        keepalive: true, // survives page navigation on finish()
                    }).catch(() => {});
                } catch (e) { /* network-blocked = next login still shows tour, acceptable */ }
            },
        };
    };
</script>
