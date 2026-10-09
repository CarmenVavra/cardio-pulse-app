import { getJson, postForm } from './common';

/**
 * Notfalltaste (SOS) der Patienten-App.
 *
 * Gegen versehentliches Auslösen: Taste 2 Sekunden gedrückt halten, danach 5 Sekunden
 * zum Abbrechen. Erst dann wird das Krankenhaus alarmiert. Mit Einwilligung wird der
 * Standort einmalig ermittelt und mitgeschickt (keine laufende Ortung). Ohne JavaScript
 * löst die Taste sofort aus (normales Formular).
 */

const HOLD_MS = 2000;
const COUNTDOWN_SECONDS = 5;
const STATUS_POLL_MS = 5000;

function locate() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) {
            resolve(null);
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => resolve(position.coords),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 60000 },
        );
    });
}

function initTrigger(root, consent) {
    const form = root.querySelector('[data-sos-form]');
    const button = root.querySelector('[data-sos-button]');
    const sub = root.querySelector('[data-sos-sub]');
    const overlay = root.querySelector('[data-sos-countdown]');
    const seconds = root.querySelector('[data-sos-seconds]');
    const cancel = root.querySelector('[data-sos-cancel]');
    if (!form || !button || !overlay) {
        return;
    }

    let holdStart = null;
    let frame = null;
    let timer = null;
    let coords = null;
    let sent = false;

    const setHold = (value) => button.style.setProperty('--hold', String(value));

    const stopHold = () => {
        cancelAnimationFrame(frame);
        holdStart = null;
        setHold(0);
        sub.textContent = 'gedrückt halten';
    };

    const send = async () => {
        if (sent) {
            return;
        }
        sent = true;
        clearInterval(timer);
        seconds.textContent = '0';
        overlay.querySelector('.sos__countdown-title').textContent = 'Krankenhaus wird alarmiert …';
        cancel.hidden = true;

        if (coords) {
            form.querySelector('[data-sos-lat]').value = coords.latitude;
            form.querySelector('[data-sos-lng]').value = coords.longitude;
            form.querySelector('[data-sos-accuracy]').value = Math.round(coords.accuracy);
        }

        try {
            await postForm(form.action, new FormData(form));
            window.location.reload();
        } catch {
            // Zur Sicherheit als normales Formular senden – der Alarm darf nie verloren gehen.
            form.submit();
        }
    };

    const startCountdown = () => {
        navigator.vibrate?.(200);
        let left = COUNTDOWN_SECONDS;
        seconds.textContent = String(left);
        overlay.hidden = false;
        cancel.focus();

        if (consent) {
            locate().then((found) => {
                coords = found;
            });
        }

        timer = setInterval(() => {
            left -= 1;
            seconds.textContent = String(Math.max(left, 0));
            if (left <= 0) {
                send();
            }
        }, 1000);
    };

    const tick = (now) => {
        if (holdStart === null) {
            return;
        }
        const progress = Math.min((now - holdStart) / HOLD_MS, 1);
        setHold(progress);
        if (progress >= 1) {
            stopHold();
            startCountdown();
            return;
        }
        frame = requestAnimationFrame(tick);
    };

    const startHold = () => {
        if (holdStart !== null || !overlay.hidden) {
            return;
        }
        holdStart = performance.now();
        sub.textContent = 'weiter halten …';
        frame = requestAnimationFrame(tick);
    };

    button.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        button.setPointerCapture?.(event.pointerId);
        startHold();
    });
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((type) => button.addEventListener(type, stopHold));
    button.addEventListener('contextmenu', (event) => event.preventDefault());

    // Tastatur: Leertaste oder Enter gedrückt halten.
    button.addEventListener('keydown', (event) => {
        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            if (!event.repeat) {
                startHold();
            }
        }
    });
    button.addEventListener('keyup', (event) => {
        if (event.key === ' ' || event.key === 'Enter') {
            stopHold();
        }
    });

    // Ein einfacher Klick löst nicht aus – nur Gedrückthalten.
    form.addEventListener('submit', (event) => {
        if (!sent) {
            event.preventDefault();
        }
    });

    cancel.addEventListener('click', () => {
        clearInterval(timer);
        overlay.hidden = true;
        coords = null;
        button.focus();
    });
}

function initActive(root, consent) {
    const claimed = root.querySelector('[data-sos-claimed]');
    const location = root.querySelector('[data-sos-location]');

    if (consent && root.dataset.located !== '1' && location) {
        locate().then(async (coords) => {
            if (!coords) {
                location.textContent = 'Ihr Standort konnte nicht ermittelt werden (Ortung am Handy aus oder nicht erlaubt). Sagen Sie beim Notruf, wo Sie sind.';
                return;
            }
            const body = new FormData();
            body.append('lat', coords.latitude);
            body.append('lng', coords.longitude);
            body.append('accuracy', Math.round(coords.accuracy));
            try {
                const state = await postForm(root.dataset.locationUrl, body);
                if (state.located) {
                    location.textContent = 'Ihr Standort wurde an das Krankenhaus übermittelt.';
                }
            } catch {
                /* Netz kurz weg – der Arzt sieht weiterhin die Wohnadresse */
            }
        });
    }

    const poll = async () => {
        try {
            const state = await getJson(root.dataset.statusUrl);
            if (!state.open) {
                window.location.reload();
                return;
            }
            if (state.claimed_by && claimed) {
                claimed.textContent = `${state.claimed_by} kümmert sich um Ihren Notruf.`;
            }
        } catch {
            /* offline – erneut versuchen */
        }
        setTimeout(poll, STATUS_POLL_MS);
    };
    setTimeout(poll, STATUS_POLL_MS);
}

export function initSos() {
    const root = document.querySelector('[data-sos]');
    if (!root) {
        return;
    }
    const consent = root.dataset.consent === '1';

    if (root.dataset.open === '1') {
        initActive(root, consent);
    } else {
        initTrigger(root, consent);
    }
}
