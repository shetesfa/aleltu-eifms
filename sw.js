// Aleltu EIFMS - minimal PWA service worker
// Strategy: cache only static assets (css/icons/manifest). All PHP pages
// (dashboards, stock, finance, etc.) always go to the network first because
// this data changes constantly and must never be served stale. If the
// network is unreachable, a small offline notice is shown instead.

const CACHE_NAME = 'aleltu-eifms-static-v1';
const OFFLINE_URL = '/aleltu-eifms/offline.html';

const STATIC_ASSETS = [
  '/aleltu-eifms/assets/css/app.css',
  '/aleltu-eifms/assets/icons/icon-192.png',
  '/aleltu-eifms/assets/icons/icon-512.png',
  '/aleltu-eifms/manifest.json',
  OFFLINE_URL
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return; // never intercept POST (forms, CSRF, payments)

  const url = new URL(req.url);
  const isStaticAsset = STATIC_ASSETS.some((p) => url.pathname === p);

  if (isStaticAsset) {
    // Cache-first for static assets only
    event.respondWith(
      caches.match(req).then((cached) => cached || fetch(req))
    );
    return;
  }

  // Network-first for every page (dynamic PHP content)
  event.respondWith(
    fetch(req).catch(() =>
      caches.match(req).then((cached) => cached || caches.match(OFFLINE_URL))
    )
  );
});
