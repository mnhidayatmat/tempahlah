const CACHE = 'tempahlah-v4';
const OFFLINE_URL = '/offline.html';
const ASSETS = [
  '/offline.html',
  '/manifest.webmanifest',
  '/icons/logo.svg',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) =>
    Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
  ));
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET') return;
  if (url.pathname.startsWith('/livewire')) return;
  if (url.pathname.startsWith('/api')) return;

  // Never store HTML page documents (they're per-user, auth'd, and change
  // often) — always fetch fresh so a deploy is visible immediately. Only
  // fall back to a cached copy when the network is unavailable.
  const isPageDocument =
    e.request.mode === 'navigate' ||
    (e.request.headers.get('accept') || '').includes('text/html');

  if (isPageDocument) {
    // Network-only with an offline fallback: never serve a stale cached page,
    // but show the offline page when there's no connection.
    e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  // Static assets (CSS/JS/images/fonts): network-first, cache a copy.
  e.respondWith(
    fetch(e.request).then((res) => {
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(e.request, copy));
      return res;
    }).catch(() => caches.match(e.request))
  );
});
