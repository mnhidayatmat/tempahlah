{{--
    Shared busy-state behaviour, rendered once per page. Covers:

      * <x-btn-submit>  — any button tagged `data-busy-submit`. Spins on submit,
                          blocks a second submit while the request is in flight.
      * <x-btn-link>    — any anchor tagged `data-busy-link` whose target is a
                          generated file (PDF, CSV). See the handshake below.
      * .bs-spinner--inline — the bare spinner, for states driven by something
                          else entirely (e.g. Livewire's wire:loading).

    Self-contained inline <style>/<script> rather than a Vite entry: the pages
    that need this do not share a CSS bundle — the dashboard loads app.css, the
    auth pages are standalone with their own inline styles, and the public
    booking page loads its own Alpine. Vanilla JS for the same reason.
--}}
@once
    <style>
        .bs-spinner {
            display: none;
            flex: none;
            width: 1em;
            height: 1em;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: bs-spin .6s linear infinite;
        }
        [data-busy-submit][aria-busy="true"] .bs-spinner,
        [data-busy-link][aria-busy="true"] .bs-spinner { display: inline-block; }

        /* For spinners driven by something other than a form submit (e.g. Livewire's
           wire:loading), which show the element themselves. */
        .bs-spinner--inline { display: inline-block; vertical-align: -0.15em; margin-right: 6px; }

        /* The [disabled] variant needs the extra attribute to outrank rules like
           `.bk-doc-btn[disabled] { opacity: .4 }` that style the not-yet-usable
           state of the same buttons. */
        [data-busy-submit][aria-busy="true"],
        [data-busy-submit][aria-busy="true"][disabled],
        [data-busy-link][aria-busy="true"] {
            opacity: .72;
            cursor: progress;
            pointer-events: none;
        }

        @keyframes bs-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .bs-spinner { animation-duration: 1.8s; } }
    </style>

    <script>
        (function () {
            // A second copy of this listener would see the in-flight flag already set
            // and preventDefault() the very submission the first copy just allowed.
            // Blade dedupes this block server-side; the guard covers a client-side
            // re-insert (e.g. Livewire morphing a component that contains it).
            //
            // NB: never write the once-directive's literal name in this file — Blade
            // compiles directives inside JS comments too, and it would unbalance the
            // block. Same trap as a literal component tag inside a CSS comment.
            if (window.__busyUiReady) return;
            window.__busyUiReady = true;

            /* ---------------------------------------------------------------
             * Forms
             * ------------------------------------------------------------- */
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!(form instanceof HTMLFormElement)) return;

                // Bubble phase on `document`, so an inline `onsubmit="return confirm(…)"`
                // — registered on the form at parse time — has already run. If the host
                // cancelled the confirm, the event is cancelled and we leave the button alone.
                if (e.defaultPrevented) return;

                var btn = (e.submitter && e.submitter.matches('[data-busy-submit]'))
                    ? e.submitter
                    : form.querySelector('[data-busy-submit]');
                if (!btn) return;

                if (form.dataset.busySubmitting === '1') { e.preventDefault(); return; }
                form.dataset.busySubmitting = '1';
                btn.setAttribute('aria-busy', 'true');

                // Deferred by one macrotask: disabling the submitter from inside the
                // submit handler drops its name/value from the payload, and Chrome
                // cancels the submission outright. By now the request is on its way.
                setTimeout(function () { btn.disabled = true; }, 0);
            });

            /* ---------------------------------------------------------------
             * Links to generated files (PDF / CSV)
             *
             * These never leave the current document: an `attachment` download
             * keeps the page, and an inline PDF opens in a new tab. So there is
             * no navigation event to hang a spinner off, and nothing to clear it.
             *
             * Handshake: tag the request with a nonce, and the server echoes it
             * back as a plain (unencrypted, non-httpOnly) `dl_token` cookie once
             * it starts responding. Cookies are shared across tabs on the same
             * origin, so this reports "the bytes are coming" for both cases.
             * See App\Support\Http\DownloadToken.
             * ------------------------------------------------------------- */
            function cookieHas(nonce) {
                return document.cookie.split('; ').indexOf('dl_token=' + nonce) !== -1;
            }

            document.addEventListener('click', function (e) {
                var a = e.target.closest ? e.target.closest('a[data-busy-link]') : null;
                if (!a || e.defaultPrevented) return;

                // Leave "open in new tab/window" and middle-clicks completely alone:
                // the current page keeps its idle state and no cookie will come back.
                if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

                if (a.getAttribute('aria-busy') === 'true') { e.preventDefault(); return; }

                var nonce = 'd' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
                var url = new URL(a.href, window.location.href);
                url.searchParams.set('_dl', nonce);
                a.href = url.toString();   // default navigation reads href after listeners run
                a.setAttribute('aria-busy', 'true');

                var poll, bail;
                function idle() {
                    clearInterval(poll);
                    clearTimeout(bail);
                    a.removeAttribute('aria-busy');
                }
                poll = setInterval(function () {
                    if (!cookieHas(nonce)) return;
                    document.cookie = 'dl_token=; Max-Age=0; path=/';
                    idle();
                }, 150);
                // Never strand the link if the response fails or carries no cookie.
                bail = setTimeout(idle, 30000);
            });

            // Back-button / bfcache restores the page with things still spinning.
            window.addEventListener('pageshow', function () {
                document.querySelectorAll('[data-busy-submit][aria-busy="true"]').forEach(function (btn) {
                    btn.removeAttribute('aria-busy');
                    btn.disabled = false;
                    if (btn.form) delete btn.form.dataset.busySubmitting;
                });
                document.querySelectorAll('[data-busy-link][aria-busy="true"]').forEach(function (a) {
                    a.removeAttribute('aria-busy');
                });
            });
        })();
    </script>
@endonce
