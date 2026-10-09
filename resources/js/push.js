import { csrfToken, storage } from './common';

/**
 * Push-Benachrichtigungen der Patienten-App: ein-/ausschalten und das Abo des Geräts
 * mit dem Server abgleichen. Die Anzeige übernimmt der Service Worker (public/app-sw.js).
 */

const DISMISSED_KEY = 'cp.push.dismissed';
const SYNCED_KEY = 'cp.push.synced';

const isStandalone = () =>
    window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;

const isIos = () =>
    /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

const supported = () =>
    'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;

function urlBase64ToUint8Array(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
}

async function send(url, method, body) {
    const response = await fetch(url, {
        method,
        body: JSON.stringify(body),
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
    });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
}

async function registration() {
    // Der Service Worker wird in install-app.js angemeldet; hier nur auf ihn warten –
    // höchstens 10 s, falls der Browser ihn nicht zulässt.
    return Promise.race([
        navigator.serviceWorker.ready,
        new Promise((_, reject) => setTimeout(() => reject(new Error('Service Worker nicht verfügbar')), 10000)),
    ]);
}

export function initPush() {
    const key = document.body.dataset.pushKey;
    const url = document.body.dataset.pushUrl;
    const cards = [...document.querySelectorAll('[data-push-card]')];
    if (!key || !url) {
        return;
    }

    const render = (state) => {
        const texts = {
            on: 'Eingeschaltet auf diesem Gerät.',
            off: 'Ausgeschaltet.',
            denied: 'Benachrichtigungen sind in den Einstellungen Ihres Handys gesperrt. Erlauben Sie sie dort für CardioPulse (bzw. für diese Webseite).',
            ios: 'Auf dem iPhone funktionieren Benachrichtigungen nur in der installierten App: zuerst CardioPulse zum Home-Bildschirm hinzufügen und von dort öffnen.',
            unsupported: 'Dieser Browser unterstützt keine Benachrichtigungen.',
            error: 'Das hat nicht geklappt. Bitte versuchen Sie es später noch einmal.',
        };

        cards.forEach((card) => {
            const dismissible = card.hasAttribute('data-push-dismissible');
            // Auf der Startseite nur als Aufforderung, solange noch nicht entschieden.
            if (dismissible && (state !== 'off' || storage.get(DISMISSED_KEY) === '1')) {
                card.hidden = true;
                return;
            }
            card.querySelector('[data-push-state]').textContent = texts[state] ?? '';
            card.querySelector('[data-push-enable]').hidden = !(state === 'off' || state === 'error');
            card.querySelector('[data-push-disable]').hidden = state !== 'on';
            card.hidden = false;
        });
    };

    if (!supported()) {
        render(isIos() && !isStandalone() ? 'ios' : 'unsupported');
        return;
    }

    const refresh = async () => {
        if (Notification.permission === 'denied') {
            render('denied');
            return;
        }
        const subscription = await (await registration()).pushManager.getSubscription();
        render(subscription ? 'on' : 'off');

        // Abo täglich mit dem Server abgleichen (falls es dort verloren ging).
        const today = new Date().toISOString().slice(0, 10);
        if (subscription && Notification.permission === 'granted' && storage.get(SYNCED_KEY) !== today) {
            send(url, 'POST', subscription.toJSON())
                .then(() => storage.set(SYNCED_KEY, today))
                .catch(() => {});
        }
    };

    document.addEventListener('click', async (event) => {
        const enable = event.target.closest('[data-push-enable]');
        const disable = event.target.closest('[data-push-disable]');
        const dismiss = event.target.closest('[data-push-dismiss]');

        if (dismiss) {
            storage.set(DISMISSED_KEY, '1');
            dismiss.closest('[data-push-card]').hidden = true;
            return;
        }

        if (enable) {
            enable.disabled = true;
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    render(permission === 'denied' ? 'denied' : 'off');
                    return;
                }
                const reg = await registration();
                const subscription = (await reg.pushManager.getSubscription())
                    ?? (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(key) }));
                await send(url, 'POST', subscription.toJSON());
                storage.set(SYNCED_KEY, new Date().toISOString().slice(0, 10));
                render('on');
            } catch {
                render('error');
            } finally {
                enable.disabled = false;
            }
        }

        if (disable) {
            disable.disabled = true;
            try {
                const subscription = await (await registration()).pushManager.getSubscription();
                if (subscription) {
                    await send(url, 'DELETE', { endpoint: subscription.endpoint });
                    await subscription.unsubscribe();
                }
                render('off');
            } catch {
                render('error');
            } finally {
                disable.disabled = false;
            }
        }
    });

    refresh().catch(() => render('unsupported'));
}
