<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>{{ __('Leave a testimonial') }} — {{ $tenant->business_name }}</title>
    {{-- Magic-link page — links carry a signature. --}}
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style id="tenant-theme">:root { {!! $tenant->themeCssVariables() !!} }</style>
    <meta name="theme-color" content="{{ $tenant->themePrimary() }}">
    <style>
        body { background: var(--bg); color: var(--ink); font-family: 'Geist', system-ui, sans-serif; margin: 0; }
        .rv-wrap { max-width: 480px; margin: 0 auto; padding: 28px 18px 60px; }
        .rv-card { background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--r-xl, 20px); padding: 24px; }
        .rv-brand { display:flex; align-items:center; gap: 10px; margin-bottom: 22px; }
        .rv-brand img { width: 34px; height: 34px; border-radius: 8px; }
        .rv-brand span { font-weight: 700; font-size: 16px; }
        .rv-eyebrow { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-3); }
        .rv-title { font-size: 20px; font-weight: 700; margin: 4px 0 4px; }
        .rv-sub { color: var(--ink-3); font-size: 13.5px; margin: 0 0 22px; line-height: 1.5; }
        .rv-field { display:flex; flex-direction:column; gap: 8px; margin-bottom: 18px; }
        .rv-field label { font-size: 12px; font-weight: 600; color: var(--ink-2); }
        .rv-field input[type=text], .rv-field textarea { width: 100%; box-sizing: border-box; padding: 11px 13px; border: 1px solid var(--line);
            border-radius: 10px; background: var(--bg); color: var(--ink); font-size: 15px; font-family: inherit; }
        .rv-field textarea { min-height: 110px; resize: vertical; line-height: 1.5; }
        .rv-field input:focus, .rv-field textarea:focus { outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent); }
        /* Star picker */
        .rv-stars { display:flex; gap: 6px; }
        .rv-star { width: 40px; height: 40px; border: 0; background: transparent; cursor: pointer; padding: 0;
            color: var(--line); transition: color 120ms ease, transform 120ms ease; line-height: 0; }
        .rv-star svg { width: 34px; height: 34px; display:block; }
        .rv-star.is-on { color: #f5b301; }
        .rv-star:hover { transform: scale(1.08); }
        .rv-btn { width: 100%; padding: 13px; border: 0; border-radius: 12px; background: var(--primary); color: #fff;
            font-size: 15px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .rv-btn:disabled { opacity: .55; cursor: not-allowed; }
        .rv-note { font-size: 11.5px; color: var(--ink-3); margin-top: 14px; line-height: 1.5; }
        .rv-err { background: var(--err-tint); color: var(--err); border: 1px solid var(--err); border-radius: 10px;
            padding: 10px 12px; font-size: 12.5px; margin-bottom: 16px; }
        .rv-stay { display:inline-flex; align-items:center; gap: 6px; margin-bottom: 18px; padding: 6px 11px;
            background: var(--bg-sunk); border-radius: 999px; font-size: 12px; color: var(--ink-2); }
        .rv-ok { text-align: center; padding: 8px 0; }
        .rv-ok-ic { width: 56px; height: 56px; border-radius: 50%; background: var(--ok-tint); color: var(--ok);
            display:flex; align-items:center; justify-content:center; margin: 0 auto 14px; font-size: 28px; }
        .rv-ok-stars { color: #f5b301; font-size: 22px; letter-spacing: 2px; margin: 8px 0; }
    </style>
</head>
<body>
    @php $isBM = app()->getLocale() === 'ms'; @endphp
    <div class="rv-wrap">
        <div class="rv-brand">
            <img src="{{ asset('icons/logo.svg') }}" alt="">
            <span>{{ $tenant->business_name }}</span>
        </div>

        <div class="rv-card">
            @if ($existing && ! $errors->any())
                {{-- Already submitted / thank-you --}}
                <div class="rv-ok">
                    <div class="rv-ok-ic">✓</div>
                    <div style="font-size: 18px; font-weight: 700; margin-bottom: 4px;">{{ __('Testimonial received') }}</div>
                    <div class="rv-ok-stars">{{ str_repeat('★', (int) $existing->rating_overall).str_repeat('☆', 5 - (int) $existing->rating_overall) }}</div>
                    <p style="color: var(--ink-3); font-size: 13.5px; margin: 0;">
                        {{ __('Thank you for sharing your stay at :business. Your words help other guests book with confidence.', ['business' => $tenant->business_name]) }}
                    </p>
                    @if (trim((string) $existing->comment) !== '')
                        <div style="margin-top: 16px; padding: 14px; background: var(--bg-sunk); border-radius: 10px; text-align: left; font-size: 13.5px; line-height: 1.55; color: var(--ink-2);">
                            "{{ $existing->comment }}"
                            <div style="margin-top: 8px; font-size: 12px; color: var(--ink-3);">— {{ $existing->displayName() }}</div>
                        </div>
                    @endif
                </div>
            @else
                <div class="rv-eyebrow">{{ __('Your stay') }}</div>
                <div class="rv-title">{{ __('How was :property?', ['property' => $booking->property?->name]) }}</div>
                <p class="rv-sub">
                    {{ __('Share a short testimonial. It appears on the :business booking page to help future guests.', ['business' => $tenant->business_name]) }}
                </p>

                @if ($booking->check_out)
                    <div class="rv-stay">📅 {{ __('Stayed :when', ['when' => $booking->check_out->translatedFormat('M Y')]) }}</div>
                @endif

                @if ($errors->any())
                    <div class="rv-err">
                        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ url()->current() }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}" id="rv-form">
                    @csrf
                    <div class="rv-field">
                        <label>{{ __('Your rating') }}</label>
                        <div class="rv-stars" id="rv-stars" role="radiogroup" aria-label="{{ __('Star rating') }}">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button" class="rv-star" data-val="{{ $i }}" aria-label="{{ trans_choice('{1} :count star|[2,*] :count stars', $i, ['count' => $i]) }}">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.26 6.9.54-5.25 4.48 1.62 6.72L12 16.9l-6.17 3.6 1.62-6.72L2.2 8.8l6.9-.54z"/></svg>
                                </button>
                            @endfor
                        </div>
                        <input type="hidden" name="rating_overall" id="rv-rating" value="{{ old('rating_overall') }}">
                    </div>

                    <div class="rv-field">
                        <label>{{ __('Your review') }}</label>
                        <textarea name="comment" maxlength="1500" required
                                  placeholder="{{ $isBM ? 'Ceritakan pengalaman anda — kebersihan, layanan tuan rumah, lokasi…' : 'Tell us about your stay — cleanliness, the host, location…' }}">{{ old('comment') }}</textarea>
                    </div>

                    <div class="rv-field">
                        <label>{{ __('Display name') }}</label>
                        <input type="text" name="guest_name" maxlength="120"
                               value="{{ old('guest_name', $booking->guestName()) }}"
                               placeholder="{{ __('e.g. Ain from Kuala Lumpur') }}">
                    </div>

                    <button type="submit" class="rv-btn">{{ __('Submit testimonial') }}</button>
                </form>

                <p class="rv-note">🔒 {{ __('Your testimonial is published on the homestay page. Only a Tempahlah admin can remove it — the host cannot edit or delete it.') }}</p>
            @endif
        </div>
    </div>

    <script>
        (function () {
            var wrap = document.getElementById('rv-stars');
            if (!wrap) return;
            var hidden = document.getElementById('rv-rating');
            var stars = Array.prototype.slice.call(wrap.querySelectorAll('.rv-star'));
            function paint(n) {
                stars.forEach(function (s) {
                    s.classList.toggle('is-on', parseInt(s.dataset.val, 10) <= n);
                });
            }
            stars.forEach(function (s) {
                s.addEventListener('mouseenter', function () { paint(parseInt(s.dataset.val, 10)); });
                s.addEventListener('click', function () {
                    hidden.value = s.dataset.val;
                    paint(parseInt(s.dataset.val, 10));
                });
            });
            wrap.addEventListener('mouseleave', function () { paint(parseInt(hidden.value || '0', 10)); });
            paint(parseInt(hidden.value || '0', 10)); // restore on validation error
        })();
    </script>
</body>
</html>
