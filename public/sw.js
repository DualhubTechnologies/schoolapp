/*
 * SchoolHub service worker: lets the app be installed on a phone's home
 * screen, and shows a friendly page instead of the browser's error when
 * the phone is offline. Nothing else is cached: school data is always live.
 */
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open('schoolhub-v1').then((cache) => cache.add(OFFLINE)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Only whole-page GET loads: everything else (forms, background
    // requests) goes straight to the network untouched.
    if (event.request.mode !== 'navigate' || event.request.method !== 'GET') {
        return;
    }

    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
});
