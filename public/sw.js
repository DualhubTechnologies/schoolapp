/*
 * SchoolHub service worker: lets the app be installed on a phone's home
 * screen, shows a friendly page instead of the browser's error when the
 * phone is offline, and keeps styles, scripts and fonts once downloaded.
 * Only files whose address carries their version (?v=, ?id=, /build/) are
 * kept, so a new version is always fetched fresh; pages and school data
 * are never cached. This saves phones data, and the Windows app (whose
 * PHP server answers one request at a time) about twenty requests a page.
 */
const OFFLINE = '/offline.html';
const STATIC = 'schoolhub-static-v1';

const isVersionedFile = (url) => url.origin === self.location.origin
    && (url.pathname.startsWith('/build/') || url.searchParams.has('v') || url.searchParams.has('id'))
    && /\.(css|js|woff2?|ttf|svg|png|jpe?g|webp)$/.test(url.pathname);

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open('schoolhub-v1').then((cache) => cache.add(OFFLINE)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    // A versioned file: from the cache, or fetched once and kept (older
    // versions of the same file are dropped).
    if (isVersionedFile(url)) {
        event.respondWith(caches.open(STATIC).then(async (cache) => {
            const kept = await cache.match(event.request);

            if (kept) {
                return kept;
            }

            const response = await fetch(event.request);

            if (response.ok) {
                const old = await cache.keys();
                await Promise.all(old.filter((request) => new URL(request.url).pathname === url.pathname).map((request) => cache.delete(request)));
                await cache.put(event.request, response.clone());
            }

            return response;
        }));

        return;
    }

    // Otherwise only whole-page loads: everything else (forms, background
    // requests) goes straight to the network untouched.
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
});
