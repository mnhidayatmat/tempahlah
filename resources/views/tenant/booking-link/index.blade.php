<x-app-layout :title="__('Booking link')">
    @php
        $isBM = app()->getLocale() === 'ms';

        // Ready-to-paste social captions. Newlines survive the copy; the share
        // deep-links URL-encode them below.
        $captionBM = "🏡 Tempah homestay kami terus — tanpa perantara, tanpa komisen!\n\n✅ Tengok tarikh yang kosong\n✅ Harga terus dari tuan rumah\n✅ Sahkan tempahan segera\n\nTempah di sini 👉 {$publicUrl}";
        $captionEN = "🏡 Book our homestay directly — no middleman, no commission!\n\n✅ See available dates\n✅ Best direct price from the host\n✅ Instant booking confirmation\n\nBook here 👉 {$publicUrl}";
        $caption = $isBM ? $captionBM : $captionEN;

        // Share deep-links. WhatsApp/Telegram/X take the caption+URL; Facebook
        // only accepts a URL (it scrapes its own preview).
        $enc = rawurlencode($caption);
        $encUrl = rawurlencode($publicUrl);
        $waHref  = "https://wa.me/?text={$enc}";
        $tgHref  = 'https://t.me/share/url?url='.$encUrl.'&text='.rawurlencode($isBM ? 'Tempah homestay kami terus' : 'Book our homestay directly');
        $xHref   = 'https://twitter.com/intent/tweet?text='.$enc;
        $fbHref  = 'https://www.facebook.com/sharer/sharer.php?u='.$encUrl;
        $mailHref = 'mailto:?subject='.rawurlencode($isBM ? 'Tempah homestay kami' : 'Book our homestay').'&body='.$enc;
    @endphp

    @once
    <style>
        .bl-root{ display:flex; flex-direction:column; gap:18px; max-width:760px; }
        .bl-link-row{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .bl-link{ flex:1; min-width:220px; font-family:var(--font-mono, monospace); font-size:14px; padding:12px 14px; border:.5px solid var(--line); border-radius:var(--r-sm); background:var(--bg-sunk); color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .bl-share-grid{ display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:10px; }
        .bl-share-btn{ display:flex; align-items:center; gap:10px; padding:12px 14px; border:.5px solid var(--line); border-radius:var(--r-md); background:var(--bg-elev); color:var(--ink); font-size:13.5px; font-weight:600; text-decoration:none; transition:border-color 120ms, background 120ms; cursor:pointer; }
        .bl-share-btn:hover{ border-color:var(--ink-4); background:var(--bg-sunk); }
        .bl-share-ic{ width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; color:#fff; }
        .bl-caption{ width:100%; min-height:150px; resize:vertical; font-size:13.5px; line-height:1.6; padding:12px 14px; border:.5px solid var(--line); border-radius:var(--r-sm); background:var(--bg-sunk); color:var(--ink); font-family:inherit; }
        .bl-cap-tabs{ display:inline-flex; gap:3px; padding:3px; background:var(--bg-sunk); border:1px solid var(--line); border-radius:var(--r-md); }
        .bl-cap-tab{ border:0; background:transparent; padding:5px 12px; border-radius:calc(var(--r-md) - 3px); font-size:12px; font-weight:600; color:var(--ink-3); cursor:pointer; }
        .bl-cap-tab.is-active{ background:var(--bg-elev); color:var(--ink); box-shadow:0 1px 2px rgba(0,0,0,.06); }
        @media (max-width:640px){
            .bl-link{ flex-basis:100%; min-width:0; }
            .bl-link-row .btn{ flex:1; height:44px; justify-content:center; font-size:13.5px; }
        }
    </style>
    @endonce

    <div class="bl-root"
         x-data="bookingLink({
            url: @js($publicUrl),
            captionBM: @js($captionBM),
            captionEN: @js($captionEN),
            isBM: @js($isBM),
            markSharedUrl: @js(route('tenant.booking-link.shared')),
            csrf: @js(csrf_token()),
            alreadyShared: @js($alreadyShared),
         })">

        {{-- Header --}}
        <div>
            <div class="kicker">{{ __('Grow') }}</div>
            <div class="display-2" style="margin-top:4px;">{{ __('Your booking link') }}</div>
            <div style="margin-top:6px; color:var(--ink-3); font-size:14px;">
                {{ __('This is your public booking page. Share it on Facebook, Instagram, WhatsApp, TikTok — anywhere your guests are. They pick dates and book you directly, with no commission.') }}
            </div>
        </div>

        {{-- The link --}}
        <div class="hauz-card" style="padding:18px;">
            <div style="font-weight:700; font-size:14px; color:var(--ink); margin-bottom:12px;">{{ __('Your public link') }}</div>
            <div class="bl-link-row">
                <div class="bl-link" x-ref="link">{{ $displayUrl }}</div>
                <button type="button" class="btn btn-sm" @click="copyLink()">
                    <span x-show="!linkCopied"><x-icon name="link" :size="14"/> {{ __('Copy link') }}</span>
                    <span x-show="linkCopied" x-cloak>✓ {{ __('Copied!') }}</span>
                </button>
                <a class="btn btn-primary btn-sm" :href="url" target="_blank" rel="noopener" @click="stampShared()">
                    {{ __('Open my page') }}
                </a>
            </div>
            <div style="margin-top:10px; font-size:12px; color:var(--ink-4);">
                {{ __('Tip: paste this link into your Instagram or TikTok bio, or your Facebook page “Book Now” button.') }}
            </div>
        </div>

        {{-- Share buttons --}}
        <div class="hauz-card" style="padding:18px;">
            <div style="font-weight:700; font-size:14px; color:var(--ink); margin-bottom:4px;">{{ __('Share now') }}</div>
            <div style="font-size:12.5px; color:var(--ink-3); margin-bottom:14px;">
                {{ __('Post your link with a ready-made caption in one tap.') }}
            </div>
            <div class="bl-share-grid">
                <a class="bl-share-btn" href="{{ $waHref }}" target="_blank" rel="noopener" @click="stampShared()">
                    <span class="bl-share-ic" style="background:#25D366;"><x-icon name="message" :size="16"/></span>
                    <span>WhatsApp</span>
                </a>
                <a class="bl-share-btn" href="{{ $fbHref }}" target="_blank" rel="noopener" @click="stampShared()">
                    <span class="bl-share-ic" style="background:#1877F2; font-weight:800;">f</span>
                    <span>Facebook</span>
                </a>
                <a class="bl-share-btn" href="{{ $tgHref }}" target="_blank" rel="noopener" @click="stampShared()">
                    <span class="bl-share-ic" style="background:#229ED9;"><x-icon name="arrow-up" :size="16"/></span>
                    <span>Telegram</span>
                </a>
                <a class="bl-share-btn" href="{{ $xHref }}" target="_blank" rel="noopener" @click="stampShared()">
                    <span class="bl-share-ic" style="background:#000; font-weight:800;">𝕏</span>
                    <span>X</span>
                </a>
                <a class="bl-share-btn" href="{{ $mailHref }}" @click="stampShared()">
                    <span class="bl-share-ic" style="background:var(--ink-3);"><x-icon name="mail" :size="16"/></span>
                    <span>{{ __('Email') }}</span>
                </a>
                <button type="button" class="bl-share-btn" x-show="canNativeShare" x-cloak @click="nativeShare()">
                    <span class="bl-share-ic" style="background:var(--primary);"><x-icon name="arrow-up" :size="16"/></span>
                    <span>{{ __('More…') }}</span>
                </button>
            </div>
        </div>

        {{-- Ready-to-paste caption --}}
        <div class="hauz-card" style="padding:18px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
                <div style="font-weight:700; font-size:14px; color:var(--ink);">{{ __('Ready-to-post caption') }}</div>
                <div class="bl-cap-tabs">
                    <button type="button" class="bl-cap-tab" :class="capLang==='bm' ? 'is-active' : ''" @click="capLang='bm'">BM</button>
                    <button type="button" class="bl-cap-tab" :class="capLang==='en' ? 'is-active' : ''" @click="capLang='en'">EN</button>
                </div>
            </div>
            <div style="font-size:12.5px; color:var(--ink-3); margin-bottom:10px;">
                {{ __('Copy this and paste it into your Instagram, TikTok, or Facebook post.') }}
            </div>
            <textarea class="bl-caption" x-ref="caption" x-model="captionText" readonly></textarea>
            <div style="margin-top:12px;">
                <button type="button" class="btn btn-primary btn-sm" @click="copyCaption()">
                    <span x-show="!capCopied">{{ __('Copy caption') }}</span>
                    <span x-show="capCopied" x-cloak>✓ {{ __('Copied!') }}</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        function bookingLink(cfg) {
            return {
                url: cfg.url,
                capLang: cfg.isBM ? 'bm' : 'en',
                linkCopied: false,
                capCopied: false,
                shared: cfg.alreadyShared,
                canNativeShare: typeof navigator !== 'undefined' && !!navigator.share,

                get captionText() {
                    return this.capLang === 'bm' ? cfg.captionBM : cfg.captionEN;
                },

                copy(text) {
                    return navigator.clipboard.writeText(text);
                },

                copyLink() {
                    this.copy(this.url).then(() => {
                        this.linkCopied = true;
                        setTimeout(() => this.linkCopied = false, 2000);
                        this.stampShared();
                    });
                },

                copyCaption() {
                    this.copy(this.captionText).then(() => {
                        this.capCopied = true;
                        setTimeout(() => this.capCopied = false, 2000);
                        this.stampShared();
                    });
                },

                nativeShare() {
                    if (!navigator.share) return;
                    navigator.share({ title: document.title, text: this.captionText, url: this.url })
                        .then(() => this.stampShared())
                        .catch(() => {});
                },

                // Fire-and-forget: stamp booking_link_shared_at once so the
                // onboarding checklist step completes. Never blocks the share.
                stampShared() {
                    if (this.shared) return;
                    this.shared = true;
                    fetch(cfg.markSharedUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' },
                        keepalive: true,
                    }).catch(() => {});
                },
            };
        }
    </script>
</x-app-layout>
