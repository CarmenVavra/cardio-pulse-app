import { formatDuration, getJson, initCallScreen, initCommon, postForm, storage } from './common';
import { initVideoCall } from './video-call';

/**
 * Krankenhaus-Dashboard: Live-Board (Polling), Alarm mit Signalton, Privacy-Lock, eingehende Anrufe.
 */

initCommon();
initCallScreen();
initVideoCall();

const body = document.body;
const page = body.dataset.page;
const liveUrl = body.dataset.liveUrl;
const lockUrl = body.dataset.lockUrl;
const pollMs = Number(body.dataset.poll) || 5000;
const lockAfterMs = (Number(body.dataset.lockAfter) || 180) * 1000;
const locked = body.dataset.locked === '1';

/* ------------------------------------------------------------------ Uhr */

const clock = document.querySelector('[data-clock]');
if (clock) {
    setInterval(() => {
        clock.textContent = new Date().toLocaleTimeString('de-DE', { hour12: false });
    }, 1000);
}

/* ------------------------------------------------------------------ Signalton */

/**
 * Zwei kurze 960-Hz-Rechteck-Pieptöne (180 ms, 250 ms Abstand), alle 1,2 s wiederholt.
 */
const tone = {
    ctx: null,
    timer: null,
    enabled: body.dataset.sound === '1' || storage.get('cp.sound') === '1',
    muted: false,

    context() {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!this.ctx && Ctx) {
            this.ctx = new Ctx();
            this.ctx.addEventListener('statechange', syncSoundButtons);
        }
        return this.ctx;
    },

    beep() {
        const ctx = this.ctx;
        if (!ctx || ctx.state !== 'running') {
            return;
        }
        [0, 0.25].forEach((offset) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            const t = ctx.currentTime + offset;
            osc.type = 'square';
            osc.frequency.value = 960;
            osc.connect(gain);
            gain.connect(ctx.destination);
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.12, t + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.18);
            osc.start(t);
            osc.stop(t + 0.2);
        });
    },

    start() {
        const ctx = this.context();
        if (!ctx) {
            return;
        }
        ctx.resume().catch(() => {});
        if (!this.timer) {
            this.beep();
            this.timer = setInterval(() => this.beep(), 1200);
        }
    },

    stop() {
        clearInterval(this.timer);
        this.timer = null;
    },

    get audible() {
        return Boolean(this.timer && this.ctx && this.ctx.state === 'running');
    },
};

if (body.dataset.sound === '1') {
    storage.set('cp.sound', '1');
}

let openAlarms = 0;
let unclaimedAlarms = 0;
let urgentAlarms = 0;
let lastAlarmId = null;
let newestAlarmId = null;

function updateTone() {
    // Ton nur, solange sich bei mindestens einem Alarm noch niemand kümmert – oder der
    // eigene Notfall-Patient darauf wartet, dass das Krankenhaus die Rettung ruft.
    const shouldPlay = (unclaimedAlarms > 0 || urgentAlarms > 0) && tone.enabled && !tone.muted && page !== 'call';
    if (shouldPlay) {
        tone.start();
    } else {
        tone.stop();
    }
    syncSoundButtons();
}

function syncSoundButtons() {
    document.querySelectorAll('[data-sound-toggle]').forEach((button) => {
        const audible = tone.audible;
        button.setAttribute('aria-pressed', audible ? 'true' : 'false');
        const label = button.querySelector('[data-sound-label]');
        if (label) {
            label.textContent = audible ? 'Ton aus' : 'Signalton an';
        }
    });
}

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-sound-toggle]')) {
        return;
    }
    if (tone.audible) {
        tone.muted = true;
    } else {
        tone.muted = false;
        tone.enabled = true;
        storage.set('cp.sound', '1');
        tone.context()?.resume().catch(() => {});
    }
    tone.stop();
    updateTone();
});

// Jede Benutzergeste gibt einen vom Browser angehaltenen AudioContext wieder frei.
['pointerdown', 'keydown'].forEach((type) => {
    document.addEventListener(
        type,
        () => {
            if (tone.ctx && tone.ctx.state !== 'running') {
                tone.ctx.resume().then(syncSoundButtons).catch(() => {});
            }
        },
        { passive: true },
    );
});

/* ------------------------------------------------------------------ Alarm-Modal (D3) */

const layer = document.querySelector('[data-alarm-layer]');
let minimizedAlarmId = null;
let lastFocus = null;

function currentModalId() {
    return layer?.querySelector('[data-alarm-id]')?.dataset.alarmId ?? null;
}

function showModal() {
    if (!layer || !layer.innerHTML.trim()) {
        return;
    }
    if (layer.hidden) {
        lastFocus = document.activeElement;
    }
    layer.hidden = false;
    layer.querySelector('textarea, button')?.focus({ preventScroll: true });
}

function hideModal() {
    if (!layer) {
        return;
    }
    layer.hidden = true;
    if (lastFocus && document.contains(lastFocus)) {
        lastFocus.focus({ preventScroll: true });
    }
}

function updateModal(data) {
    if (!layer) {
        return;
    }
    if (!data.modal_html) {
        layer.innerHTML = '';
        layer.hidden = true;
        return;
    }
    if (String(data.alarm_id) !== currentModalId()) {
        layer.innerHTML = data.modal_html;
        if (data.alarm_passive) {
            // Von einem Kollegen übernommen: nicht aufpoppen, nur im Banner zeigen.
            minimizedAlarmId = String(data.alarm_id);
            hideModal();
        } else {
            minimizedAlarmId = null;
            showModal();
        }
    } else if (data.alarm_version !== layer.querySelector('[data-alarm-version]')?.dataset.alarmVersion) {
        // Gleicher Alarm, neuer Stand (übernommen, Standort, Fehlalarm): Inhalt tauschen,
        // getippte Maßnahme und Fokus behalten.
        const note = layer.querySelector('textarea')?.value ?? '';
        const noteFocused = document.activeElement?.matches?.('.alarm-modal textarea') ?? false;
        layer.innerHTML = data.modal_html;
        const textarea = layer.querySelector('textarea');
        if (textarea) {
            textarea.value = note;
            if (noteFocused && !layer.hidden) {
                textarea.focus({ preventScroll: true });
            }
        }
        // Patient bittet um die Rettung bzw. antwortet nicht mehr: Fenster wieder öffnen.
        if (data.alarm_urgent && !data.alarm_passive && layer.hidden) {
            minimizedAlarmId = null;
            showModal();
        }
    } else if (String(minimizedAlarmId) !== String(data.alarm_id) && layer.hidden) {
        showModal();
    }
}

setInterval(() => {
    const modal = layer?.querySelector('[data-triggered]');
    const out = modal?.querySelector('[data-alarm-elapsed]');
    if (modal && out) {
        const seconds = Math.max(0, (Date.now() - Date.parse(modal.dataset.triggered)) / 1000);
        out.textContent = formatDuration(seconds, true);
    }
}, 1000);

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-open-alarm]')) {
        minimizedAlarmId = null;
        showModal();
    } else if (event.target.closest('[data-minimize-alarm]')) {
        minimizedAlarmId = currentModalId();
        hideModal();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && layer && !layer.hidden) {
        minimizedAlarmId = currentModalId();
        hideModal();
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (form.matches('[data-ack-form]')) {
        event.preventDefault();
        const button = event.submitter;
        if (button) {
            button.disabled = true;
        }
        try {
            await postForm(form.action, new FormData(form));
            layer.innerHTML = '';
            layer.hidden = true;
            await refresh();
        } catch {
            form.submit();
        }
    }

    if (form.matches('[data-claim-form], [data-rescue-form]')) {
        event.preventDefault();
        if (event.submitter) {
            event.submitter.disabled = true;
        }
        try {
            await postForm(form.action, new FormData(form));
            await refresh();
        } catch {
            form.submit();
        }
    }

    if (form.matches('[data-demo-form]')) {
        event.preventDefault();
        try {
            await postForm(form.action, new FormData(form));
            await refresh();
        } catch {
            form.submit();
        }
    }
});

if (layer && !layer.hidden) {
    lastAlarmId = currentModalId();
    showModal();
} else if (currentModalId()) {
    // Von einem Kollegen übernommener Alarm: bleibt zu, bis jemand „Alarm öffnen“ wählt.
    minimizedAlarmId = currentModalId();
}

/* ------------------------------------------------------------------ Live-Board (D2) */

const rowsBody = document.querySelector('[data-board-rows]');
const search = document.querySelector('[data-board-search]');
const announcer = document.createElement('div');
announcer.className = 'sr-only';
announcer.setAttribute('aria-live', 'polite');
document.body.appendChild(announcer);

function applySearch() {
    if (!rowsBody) {
        return;
    }
    const query = (search?.value ?? '').trim().toLowerCase();
    rowsBody.querySelectorAll('[data-search]').forEach((row) => {
        row.hidden = query !== '' && !row.dataset.search.includes(query);
    });
}

search?.addEventListener('input', applySearch);

function updateRows(data) {
    if (!rowsBody) {
        return;
    }

    const before = new Set([...rowsBody.querySelectorAll('[data-upload-key]')].map((row) => row.dataset.uploadKey));
    const focused = document.activeElement;
    const focusedRow = rowsBody.contains(focused) ? focused.closest('tr')?.dataset.uploadKey : null;

    rowsBody.innerHTML = data.rows_html;

    const added = [];
    rowsBody.querySelectorAll('[data-upload-key]').forEach((row) => {
        if (!before.has(row.dataset.uploadKey)) {
            row.classList.add('is-new');
            added.push(row);
        }
        if (focusedRow && row.dataset.uploadKey === focusedRow) {
            row.querySelector('a, button')?.focus({ preventScroll: true });
        }
    });

    if (added.length) {
        announcer.textContent = added
            .map((row) => `Neuer Upload: ${row.querySelector('.col-name')?.textContent.trim().split('\n')[0]} ${row.querySelector('.col-bp')?.textContent.trim()}`)
            .join('. ');
    }

    applySearch();

    Object.entries(data.counts ?? {}).forEach(([status, count]) => {
        const el = document.querySelector(`[data-count="${status}"]`);
        if (el) {
            el.textContent = count;
        }
    });
    const uploads = document.querySelector('[data-uploads-today]');
    if (uploads) {
        uploads.textContent = data.uploads_today;
    }
    const total = document.querySelector('[data-patients-total]');
    if (total) {
        total.textContent = data.patients_total;
    }
}

/* ------------------------------------------------------------------ Eingehender Anruf (Patient → Klinik) */

const toast = document.querySelector('[data-call-toast]');

function updateIncomingCall(call) {
    if (!toast) {
        return;
    }
    if (!call || page === 'call') {
        toast.hidden = true;
        return;
    }
    toast.querySelector('[data-call-toast-name]').textContent = call.name;
    toast.querySelector('[data-call-toast-form]').action = call.answer_url;
    toast.hidden = false;
}

/* ------------------------------------------------------------------ Polling */

let pollTimer = null;
let lastBannerHtml = null;

function apply(data) {
    if (Boolean(data.locked) !== locked) {
        window.location.reload();
        return;
    }

    openAlarms = data.open_alarms;
    unclaimedAlarms = data.unclaimed_alarms ?? data.open_alarms;
    if ((data.newest_alarm_id && data.newest_alarm_id !== newestAlarmId) || (data.urgent_alarms ?? 0) > urgentAlarms) {
        tone.muted = false; // Neuer Alarm bzw. Bitte um Rettung: Ton immer wieder einschalten.
    }
    urgentAlarms = data.urgent_alarms ?? 0;
    newestAlarmId = data.newest_alarm_id;
    lastAlarmId = data.alarm_id;

    const badge = document.querySelector('[data-alarm-badge]');
    if (badge) {
        badge.hidden = page === 'board' || openAlarms === 0;
        badge.querySelector('[data-alarm-badge-count]').textContent = openAlarms;
    }

    const banner = document.querySelector('[data-alarm-banner]');
    if (banner && data.banner_html !== lastBannerHtml) {
        const hadFocus = banner.contains(document.activeElement);
        banner.innerHTML = data.banner_html;
        lastBannerHtml = data.banner_html;
        if (hadFocus) {
            banner.querySelector('button')?.focus({ preventScroll: true });
        }
    }

    updateModal(data);
    if (data.rows_html !== undefined) {
        updateRows(data);
    }
    updateIncomingCall(data.incoming_call);
    updateTone();
}

async function refresh() {
    clearTimeout(pollTimer);
    try {
        const url = page === 'board' ? liveUrl : `${liveUrl}?scope=status`;
        apply(await getJson(url));
    } catch {
        /* Verbindung kurz unterbrochen – nächster Versuch folgt */
    } finally {
        pollTimer = setTimeout(refresh, pollMs);
    }
}

if (liveUrl) {
    refresh();
}

/* ------------------------------------------------------------------ Privacy-Lock (D6) */

if (!locked && page !== 'call' && lockUrl) {
    let idleTimer = null;
    const lockNow = async () => {
        try {
            await postForm(lockUrl);
        } finally {
            window.location.href = document.querySelector('.h-top .logo')?.href ?? '/';
        }
    };
    const resetIdle = () => {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(lockNow, lockAfterMs);
    };
    ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart', 'scroll'].forEach((type) => {
        document.addEventListener(type, resetIdle, { passive: true });
    });
    resetIdle();
}

const pinForm = document.querySelector('[data-pin-form]');
if (pinForm) {
    const digits = [...pinForm.querySelectorAll('[data-pin-digit]')];
    const hidden = pinForm.querySelector('[data-pin-value]');
    const collect = () => digits.map((d) => d.value).join('');

    digits.forEach((input, index) => {
        input.addEventListener('input', () => {
            // Mehrere Ziffern (schnelles Tippen, Autofill) auf die folgenden Felder verteilen.
            const typed = input.value.replace(/\D/g, '').split('');
            input.value = '';
            let position = index;
            typed.forEach((digit) => {
                if (digits[position]) {
                    digits[position].value = digit;
                    position += 1;
                }
            });
            digits.forEach((d) => d.classList.toggle('is-filled', d.value !== ''));
            digits[Math.min(position, digits.length - 1)].focus();
            if (collect().length === digits.length) {
                hidden.value = collect();
                pinForm.requestSubmit();
            }
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !input.value && digits[index - 1]) {
                digits[index - 1].focus();
            }
        });
        input.addEventListener('paste', (event) => {
            const pasted = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, digits.length);
            if (pasted) {
                event.preventDefault();
                digits.forEach((d, i) => {
                    d.value = pasted[i] ?? '';
                    d.classList.toggle('is-filled', d.value !== '');
                });
                hidden.value = collect();
                if (pasted.length === digits.length) {
                    pinForm.requestSubmit();
                }
            }
        });
    });

    pinForm.addEventListener('submit', () => {
        hidden.value = collect();
    });
}
