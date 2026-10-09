import { getJson, initCallScreen, initCommon, storage } from './common';
import { initInstallApp } from './install-app';
import { initSos } from './sos';
import { initVideoCall } from './video-call';

/**
 * Patienten-App: Numpad-Erfassung, Notfall-Bestätigung, Erinnerung, eingehende Anrufe.
 */

initCommon();
initCallScreen();
initVideoCall();
initSos();
initInstallApp();

/* ------------------------------------------------------------------ Eingehender Anruf (M7) */

const incomingUrl = document.body.dataset.incomingUrl;
if (incomingUrl) {
    const interval = Number(document.body.dataset.poll) || 4000;
    const check = async () => {
        try {
            const data = await getJson(incomingUrl);
            if (data.call?.url) {
                window.location.href = data.call.url;
                return;
            }
        } catch {
            /* offline – erneut versuchen */
        }
        setTimeout(check, interval);
    };
    setTimeout(check, interval);
}

/* ------------------------------------------------------------------ Erfassen (M2) */

const measureForm = document.querySelector('[data-measure-form]');
if (measureForm) {
    const fields = [...measureForm.querySelectorAll('[data-value-field]')];
    const inputs = fields.map((field) => field.querySelector('[data-value-input]'));
    let active = 0;

    const activate = (index) => {
        active = Math.max(0, Math.min(inputs.length - 1, index));
        fields.forEach((field, i) => field.classList.toggle('is-active', i === active));
    };

    inputs.forEach((input, index) => {
        input.addEventListener('focus', () => activate(index));
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 3);
        });
    });

    measureForm.querySelectorAll('[data-key]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = inputs[active];
            const key = button.dataset.key;

            if (key === 'back') {
                input.value = input.value.slice(0, -1);
            } else if (key === 'next') {
                if (active < inputs.length - 1) {
                    activate(active + 1);
                } else {
                    measureForm.requestSubmit();
                    return;
                }
            } else if (input.value.length < 3) {
                input.value += key;
                // Nach drei Ziffern (bzw. zwei beim unteren Wert/Puls ab 30) automatisch weiter.
                if (input.value.length === 3 && active < inputs.length - 1) {
                    activate(active + 1);
                }
            }
        });
    });

    // Erfassungsart: Hinweis für Bluetooth / Foto-Scan
    const hints = [...measureForm.querySelectorAll('[data-method-hint]')];
    const syncHints = () => {
        const method = measureForm.querySelector('[data-method]:checked')?.value;
        hints.forEach((hint) => {
            hint.hidden = hint.dataset.methodHint !== method;
        });
    };
    measureForm.querySelectorAll('[data-method]').forEach((radio) => radio.addEventListener('change', syncHints));
    syncHints();

    // Beschwerden: "Keine" schließt andere Symptome aus
    const none = measureForm.querySelector('[data-symptom-none]');
    const symptoms = [...measureForm.querySelectorAll('[data-symptom]')];
    none?.addEventListener('change', () => {
        if (none.checked) {
            symptoms.forEach((s) => {
                s.checked = false;
            });
        }
    });
    symptoms.forEach((s) =>
        s.addEventListener('change', () => {
            if (s.checked && none) {
                none.checked = false;
            }
        }),
    );

    // Uhrzeit im Kopf aktuell halten
    const now = document.querySelector('[data-now]');
    if (now) {
        setInterval(() => {
            now.textContent = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        }, 15000);
    }
}

/* ------------------------------------------------------------------ Notfall (M4) */

const symptomFree = document.querySelector('[data-symptom-free]');
const symptomFreeSubmit = document.querySelector('[data-symptom-free-submit]');
if (symptomFree && symptomFreeSubmit) {
    const sync = () => {
        symptomFreeSubmit.disabled = !symptomFree.checked;
    };
    symptomFree.addEventListener('change', sync);
    sync();
}

/* ------------------------------------------------------------------ Erinnerung "in 5 Min" (M3) */

const REMINDER_KEY = 'cp.reminderAt';

function showReminder() {
    storage.remove(REMINDER_KEY);
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('CardioPulse', { body: 'Zeit für die Kontrollmessung.' });
    }
    navigator.vibrate?.([200, 100, 200]);

    const toast = document.createElement('div');
    toast.className = 'alert alert--success reminder-toast';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = '<b>Zeit für die Kontrollmessung.</b> <a href="/app/messen">Jetzt messen →</a>';
    document.body.appendChild(toast);
}

function scheduleReminder() {
    const at = Number(storage.get(REMINDER_KEY));
    if (!at) {
        return;
    }
    setTimeout(showReminder, Math.max(0, at - Date.now()));
}

document.querySelectorAll('[data-reminder]').forEach((button) => {
    button.addEventListener('click', async () => {
        const seconds = Number(button.dataset.reminder) || 300;
        storage.set(REMINDER_KEY, String(Date.now() + seconds * 1000));
        if ('Notification' in window && Notification.permission === 'default') {
            try {
                await Notification.requestPermission();
            } catch {
                /* ignorieren */
            }
        }
        scheduleReminder();
        const status = document.querySelector('[data-reminder-status]');
        if (status) {
            const time = new Date(Date.now() + seconds * 1000).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
            status.textContent = `Erinnerung um ${time} Uhr gesetzt. Lassen Sie die App geöffnet.`;
            status.hidden = false;
        }
        button.disabled = true;
    });
});

scheduleReminder();
