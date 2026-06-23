// Alpine for the PUBLIC, non-Livewire surfaces (tenant subdomain booking page
// + the public booking-public layout).
//
// Why a dedicated entry instead of the jsdelivr CDN:
//   These pages were depending on a single third-party CDN <script> tag for
//   Alpine. When that request failed/timed out (e.g. on some Malaysian mobile
//   carriers, or a slow 4G connection), Alpine never started — leaving the
//   whole interactive page (calendar, stat numbers, booking form) blank while
//   the static Blade chrome still rendered. Bundling Alpine and serving it
//   from our own origin removes that fragility entirely.
//
// Why NOT in app.js: app.js is shared with the authenticated dashboard, which
// loads Livewire 4 — and Livewire bundles + starts its own Alpine instance.
// A second Alpine there triggers "Detected multiple instances of Alpine
// running" and breaks reactivity. These public layouts do NOT load Livewire,
// so they get this standalone Alpine instead.
//
// Ordering note: this is an ES module (Vite output is type="module"), so it is
// deferred and runs after the document is fully parsed. The page's inline
// component functions (e.g. `function wafa(opts)`) are defined in a <script>
// before </body>, so they're already on `window` by the time Alpine.start()
// evaluates `x-data="wafa(...)"`. Same guarantee the old `<script defer>` CDN
// tag gave us.
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
