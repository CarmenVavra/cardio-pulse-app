/**
 * Gemeinsame Helfer für Krankenhaus-Dashboard und Patienten-App.
 */

export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function getJson(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    if (response.status === 401 || response.status === 419) {
        window.location.reload();
        throw new Error('Sitzung abgelaufen');
    }
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
}

export async function postForm(url, body = new FormData()) {
    const response = await fetch(url, {
        method: 'POST',
        body,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
    });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
}

export const storage = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            /* Speicher nicht verfügbar – Funktion bleibt ohne Persistenz nutzbar. */
        }
    },
    remove(key) {
        try {
            window.localStorage.removeItem(key);
        } catch {
            /* ignorieren */
        }
    },
};

export const pad = (n) => String(n).padStart(2, '0');

export const formatDuration = (seconds, withHours = false) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    return withHours ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(h * 60 + m)}:${pad(s)}`;
};

/**
 * Drucken-Buttons und <dialog>-Steuerung.
 */
export function initCommon() {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-print]')) {
            window.print();
            return;
        }

        const opener = event.target.closest('[data-dialog-open]');
        if (opener) {
            document.getElementById(opener.dataset.dialogOpen)?.showModal();
            return;
        }

        const closer = event.target.closest('[data-dialog-close]');
        if (closer) {
            closer.closest('dialog')?.close();
        }
    });
}

/**
 * Anruf-Screen (Arzt D5 und Patient M7): Status-Polling, Gesprächsdauer, Audio-Pegel.
 */
export function initCallScreen() {
    const root = document.querySelector('[data-call]');
    if (!root) {
        return;
    }

    const statusUrl = root.dataset.statusUrl;
    const initialStatus = root.dataset.status;
    let elapsed = Number(root.dataset.elapsed || 0);
    const timer = root.querySelector('[data-call-timer]');
    const bars = [...root.querySelectorAll('[data-wave] span')];

    if (initialStatus === 'active') {
        setInterval(() => {
            elapsed += 1;
            if (timer) {
                timer.textContent = formatDuration(elapsed);
            }
        }, 1000);

        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!reduced && bars.length) {
            setInterval(() => {
                bars.forEach((bar) => {
                    bar.style.height = `${6 + Math.round(Math.random() * (bar.parentElement.clientHeight - 6))}px`;
                });
            }, 160);
        }
    }

    root.querySelectorAll('[data-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            button.setAttribute('aria-pressed', button.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
        });
    });

    if (initialStatus === 'ringing' || initialStatus === 'active') {
        const check = async () => {
            try {
                const data = await getJson(statusUrl);
                if (data.status !== initialStatus) {
                    window.location.reload();
                    return;
                }
            } catch {
                /* Netzwerkfehler: beim nächsten Intervall erneut versuchen */
            }
            setTimeout(check, 2000);
        };
        setTimeout(check, 2000);
    }
}
