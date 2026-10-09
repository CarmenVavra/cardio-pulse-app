/*
 * Service Worker der Patienten-App (installierbare Web-App, Bereich /app).
 *
 * Bewusst minimal: Es werden KEINE Gesundheitsdaten zwischengespeichert – jede Seite
 * kommt immer frisch vom Server. Nur ohne Internet erscheint eine Hinweisseite mit
 * der Notrufnummer statt der Fehlerseite des Browsers.
 *
 * Außerdem zeigt er Push-Benachrichtigungen (Anruf, Nachricht, Termin) und öffnet
 * beim Antippen die passende Seite der App.
 */

const CACHE = 'cardiopulse-offline-v2';
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

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = {};
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'CardioPulse', {
            body: data.body || 'Öffnen Sie CardioPulse.',
            icon: '/app-icons/icon-192.png',
            tag: data.tag || 'cardiopulse',
            renotify: Boolean(data.tag),
            requireInteraction: Boolean(data.requireInteraction),
            lang: 'de',
            data: { url: data.url || '/app' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/app', self.location.origin);
    // Nur Seiten der eigenen App öffnen.
    const url = target.origin === self.location.origin ? target.href : `${self.location.origin}/app`;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (windows) => {
            const open = windows.find((client) => new URL(client.url).pathname.startsWith('/app'));
            if (open) {
                await open.focus();
                return open.navigate(url).catch(() => self.clients.openWindow(url));
            }
            return self.clients.openWindow(url);
        }),
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
