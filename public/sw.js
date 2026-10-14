/*
 * PSU Intern-Link service worker.
 *
 * Deliberately conservative: this is a system with logins and private
 * student records, so pages are NEVER cached - only public static files
 * (css/js/logo/icons and the CDN libraries). When the phone is offline the
 * user sees a friendly offline page instead of the browser's dinosaur.
 *
 * Bump VERSION whenever you change this file or offline.html.
 */
const VERSION = 'v1';
const CACHE = 'internlink-static-' + VERSION;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
    OFFLINE_URL,
    '/images/psu-logo.png',
    '/images/icons/icon-192.png',
];

// Only these are ever stored in the cache.
const CACHEABLE_PATHS = ['/css/', '/js/', '/images/', '/build/'];
const CACHEABLE_HOSTS = ['cdn.jsdelivr.net'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Forms, uploads, logins - always go straight to the server.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Page loads: network only; show the offline page if there is no connection.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    const isStatic =
        (url.origin === self.location.origin && CACHEABLE_PATHS.some((p) => url.pathname.startsWith(p))) ||
        CACHEABLE_HOSTS.includes(url.hostname);

    if (!isStatic) return; // anything else (uploads, documents, API, avatars) is never cached

    // Static files: show the cached copy instantly, refresh it in the background.
    event.respondWith(
        caches.open(CACHE).then((cache) =>
            cache.match(request).then((cached) => {
                const network = fetch(request)
                    .then((response) => {
                        if (response && (response.status === 200 || response.type === 'opaque')) {
                            cache.put(request, response.clone());
                        }
                        return response;
                    })
                    .catch(() => cached);
                return cached || network;
            })
        )
    );
});
