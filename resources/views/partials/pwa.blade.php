{{-- PWA install + offline support. Shared across every entry layout (landing,
     auth, dashboard, public booking) so the app is installable from wherever
     the user first lands. Provides: the web app manifest, iOS/Android
     standalone meta, the home-screen icon, and — critically — the
     service-worker registration (without this the SW at /sw.js is dead code).
     theme-color is intentionally NOT set here: each layout sets its own
     (the auth screens use a darker brand shade). --}}
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Tempahlah">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () { /* SW optional — page still works */ });
        });
    }
</script>
