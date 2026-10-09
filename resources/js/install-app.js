import { storage } from './common';

/**
 * Installierbare Web-App: Service Worker anmelden (Offline-Hinweis) und den passenden
 * Installationshinweis zeigen – Android/Chrome mit Knopf, iPhone mit Anleitung.
 */

const DISMISSED_KEY = 'cp.install.dismissed';

const isStandalone = () =>
    window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;

const isIos = () =>
    /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return;
    }
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/app-sw.js', { scope: '/app' }).catch(() => {
            /* ohne Service Worker funktioniert die App trotzdem, nur ohne Offline-Hinweis */
        });
    });
}

export function initInstallApp() {
    registerServiceWorker();

    const cards = [...document.querySelectorAll('[data-install]')];
    if (!cards.length || isStandalone()) {
        return;
    }

    const visible = cards.filter((card) => !(card.hasAttribute('data-install-dismissible') && storage.get(DISMISSED_KEY) === '1'));
    const show = (selector) => visible.forEach((card) => {
        card.querySelectorAll('[data-install-prompt], [data-install-ios], [data-install-other]').forEach((el) => {
            el.hidden = !el.matches(selector);
        });
        card.hidden = false;
    });

    let deferredPrompt = null;

    if (isIos()) {
        show('[data-install-ios]');
    } else {
        // Chrome/Edge: Installationsdialog erst nach dem Ereignis möglich; bis dahin allgemeiner Hinweis.
        show('[data-install-other]');
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredPrompt = event;
            show('[data-install-prompt]');
        });
    }

    window.addEventListener('appinstalled', () => cards.forEach((card) => {
        card.hidden = true;
    }));

    document.addEventListener('click', async (event) => {
        if (event.target.closest('[data-install-prompt]') && deferredPrompt) {
            deferredPrompt.prompt();
            await deferredPrompt.userChoice.catch(() => null);
            deferredPrompt = null;
        }
        const dismiss = event.target.closest('[data-install-dismiss]');
        if (dismiss) {
            storage.set(DISMISSED_KEY, '1');
            dismiss.closest('[data-install]').hidden = true;
        }
    });
}
