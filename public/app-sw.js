/*
 * Service Worker der Patienten-App (installierbare Web-App, Bereich /app).
 *
 * Bewusst minimal: Es werden KEINE Gesundheitsdaten zwischengespeichert – jede Seite
 * kommt immer frisch vom Server. Nur ohne Internet erscheint eine Hinweisseite mit
 * der Notrufnummer statt der Fehlerseite des Browsers.
 */

const CACHE = 'cardiopulse-offline-v1';
const OFFLINE_URL = '/app/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' }))),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(async () => (await caches.match(OFFLINE_URL)) ?? Response.error()),
    );
});
