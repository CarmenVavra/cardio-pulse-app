import { getJson } from './common';

/**
 * Videosprechstunde (WebRTC): Bild und Ton laufen verschlüsselt (DTLS-SRTP) direkt zwischen
 * den Browsern. Der Verbindungsaufbau läuft über die App (Polling von /signale):
 *
 * - Arzt („offerer“): schickt beim Laden ein Angebot – und ein neues, sobald der Patient
 *   „ready“ meldet (z. B. nach einem Neuladen seiner Seite).
 * - Patient („answerer“): meldet „ready“ und beantwortet jedes neuere Angebot.
 * - Antworten und Netzwerkkandidaten tragen die Nummer des Angebots („for“), damit alte
 *   Nachrichten eines vorherigen Versuchs ignoriert werden.
 */

const FAST_POLL_MS = 800;
const SLOW_POLL_MS = 3000;
const FAIL_HINT_MS = 25000;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function postSignal(url, type, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ type, payload }),
    });
    if (!response.ok) {
        throw new Error(`Signal ${type}: ${response.status}`);
    }

    return response.json();
}

async function getMedia() {
    if (!navigator.mediaDevices?.getUserMedia) {
        return null;
    }
    try {
        return await navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true },
            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' },
        });
    } catch {
        try {
            // Keine Kamera oder nicht freigegeben: wenigstens Ton.
            return await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            return null;
        }
    }
}

export function initVideoCall() {
    const root = document.querySelector('[data-video]');
    if (!root) {
        return;
    }

    const state = root.querySelector('[data-video-state]');
    const setState = (text, kind = 'info') => {
        if (state) {
            state.textContent = text;
            state.dataset.kind = kind;
        }
    };

    if (!window.RTCPeerConnection) {
        setState('Dieser Browser unterstützt keine Videogespräche. Bitte telefonieren Sie über die angezeigte Nummer.', 'error');
        return;
    }

    const url = root.dataset.signalUrl;
    const offerer = root.dataset.role === 'offerer';
    const iceServers = JSON.parse(root.dataset.ice || '[]');
    const localVideo = root.querySelector('[data-local-video]');
    const remoteVideo = root.querySelector('[data-remote-video]');
    const unmuteButton = root.querySelector('[data-video-unmute]');
    const micButton = root.querySelector('[data-video-mic]');
    const cameraButton = root.querySelector('[data-video-camera]');

    let stream = null;
    let pc = null;
    let session = null; // Nummer des Angebots, zu dem die aktuelle Verbindung gehört
    let after = 0;
    let connected = false;
    let stopped = false;
    let pendingLocal = []; // eigene Kandidaten, bevor die Angebotsnummer bekannt ist
    let pendingRemote = []; // fremde Kandidaten, bevor die Gegenseite beschrieben ist
    let failTimer = null;

    const playRemote = () => {
        remoteVideo.play().then(
            () => unmuteButton?.setAttribute('hidden', ''),
            () => unmuteButton?.removeAttribute('hidden'), // Autoplay mit Ton blockiert: Klick nötig
        );
    };
    unmuteButton?.addEventListener('click', playRemote);

    const armFailHint = () => {
        clearTimeout(failTimer);
        failTimer = setTimeout(() => {
            if (!connected) {
                setState('Keine Videoverbindung möglich – vermutlich blockiert das Netzwerk direkte Verbindungen. Bitte telefonieren Sie über die angezeigte Nummer.', 'error');
            }
        }, FAIL_HINT_MS);
    };

    const sendCandidate = (candidate) => {
        postSignal(url, 'candidate', { for: session, candidate }).catch(() => {});
    };

    const newPeer = () => {
        pc?.close();
        connected = false;
        pendingLocal = [];
        pendingRemote = [];
        pc = new RTCPeerConnection({ iceServers });

        if (stream) {
            stream.getTracks().forEach((track) => pc.addTrack(track, stream));
        }
        // Ohne eigene Kamera bzw. eigenes Mikrofon trotzdem Bild und Ton der Gegenseite empfangen.
        if (!stream?.getVideoTracks().length) {
            pc.addTransceiver('video', { direction: 'recvonly' });
        }
        if (!stream?.getAudioTracks().length) {
            pc.addTransceiver('audio', { direction: 'recvonly' });
        }

        pc.ontrack = (event) => {
            if (remoteVideo.srcObject !== event.streams[0]) {
                remoteVideo.srcObject = event.streams[0] ?? new MediaStream([event.track]);
            }
            playRemote();
        };

        pc.onicecandidate = (event) => {
            if (!event.candidate) {
                return;
            }
            const candidate = event.candidate.toJSON();
            if (session === null) {
                pendingLocal.push(candidate);
            } else {
                sendCandidate(candidate);
            }
        };

        pc.onconnectionstatechange = () => {
            switch (pc.connectionState) {
                case 'connected':
                    connected = true;
                    clearTimeout(failTimer);
                    root.classList.add('is-connected');
                    setState('Verbunden · Ende-zu-Ende verschlüsselt', 'ok');
                    break;
                case 'disconnected':
                    setState('Verbindung unterbrochen – verbinde erneut …', 'warn');
                    break;
                case 'failed':
                    connected = false;
                    root.classList.remove('is-connected');
                    setState('Videoverbindung abgebrochen – verbinde neu …', 'warn');
                    // Neuer Versuch: Arzt bietet neu an, Patient meldet sich erneut.
                    (offerer ? makeOffer() : announce()).catch(() => {});
                    break;
                default:
                    break;
            }
        };
    };

    const flushLocal = () => {
        pendingLocal.forEach(sendCandidate);
        pendingLocal = [];
    };

    const addRemoteCandidate = async (candidate) => {
        if (!pc.remoteDescription) {
            pendingRemote.push(candidate);
            return;
        }
        try {
            await pc.addIceCandidate(candidate);
        } catch {
            /* veralteter Kandidat – ignorieren */
        }
    };

    const flushRemote = async () => {
        const queued = pendingRemote;
        pendingRemote = [];
        for (const candidate of queued) {
            await addRemoteCandidate(candidate);
        }
    };

    async function makeOffer() {
        newPeer();
        session = null;
        setState('Baue Videoverbindung auf …');
        armFailHint();
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        const { id } = await postSignal(url, 'offer', { sdp: pc.localDescription.toJSON() });
        session = id;
        after = Math.max(after, id);
        flushLocal();
    }

    async function announce() {
        session = null;
        setState('Warte auf die Videoverbindung …');
        armFailHint();
        const { id } = await postSignal(url, 'ready', {});
        after = Math.max(after, id);
    }

    async function handle(signal) {
        const { type, payload } = signal;

        if (offerer) {
            if (type === 'ready') {
                await makeOffer();
            } else if (type === 'answer' && payload.for === session && pc.signalingState === 'have-local-offer') {
                await pc.setRemoteDescription(payload.sdp);
                await flushRemote();
            } else if (type === 'candidate' && payload.for === session) {
                await addRemoteCandidate(payload.candidate);
            }
            return;
        }

        if (type === 'offer') {
            newPeer();
            session = signal.id;
            await pc.setRemoteDescription(payload.sdp);
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await postSignal(url, 'answer', { for: session, sdp: pc.localDescription.toJSON() });
            flushLocal();
            await flushRemote();
        } else if (type === 'candidate' && payload.for === session) {
            await addRemoteCandidate(payload.candidate);
        }
    }

    async function poll() {
        if (stopped) {
            return;
        }
        try {
            const data = await getJson(`${url}?after=${after}`);
            if (data.status !== 'active') {
                stop();
                return;
            }
            for (const signal of data.signals) {
                after = Math.max(after, signal.id);
                try {
                    await handle(signal);
                } catch (error) {
                    console.warn('Videosprechstunde: Nachricht nicht verarbeitet', signal.type, error);
                    setState('Fehler beim Verbindungsaufbau – verbinde neu …', 'warn');
                    // Neu beginnen statt hängen zu bleiben.
                    await (offerer ? makeOffer() : announce()).catch(() => {});
                }
            }
        } catch {
            /* Netzwerkfehler: beim nächsten Intervall erneut versuchen */
        }
        setTimeout(poll, connected ? SLOW_POLL_MS : FAST_POLL_MS);
    }

    function stop() {
        stopped = true;
        clearTimeout(failTimer);
        pc?.close();
        stream?.getTracks().forEach((track) => track.stop());
    }

    const toggleTrack = (button, kind, onLabel, offLabel) => {
        button?.addEventListener('click', () => {
            const tracks = kind === 'audio' ? stream?.getAudioTracks() : stream?.getVideoTracks();
            if (!tracks?.length) {
                return;
            }
            const enabled = !tracks[0].enabled;
            tracks.forEach((track) => {
                track.enabled = enabled;
            });
            button.setAttribute('aria-pressed', enabled ? 'false' : 'true');
            const label = button.querySelector('[data-label]');
            if (label) {
                label.textContent = enabled ? onLabel : offLabel;
            }
        });
    };
    toggleTrack(micButton, 'audio', 'Stumm', 'Stumm aus');
    toggleTrack(cameraButton, 'video', 'Kamera aus', 'Kamera an');

    window.addEventListener('pagehide', stop);

    (async () => {
        setState('Kamera und Mikrofon werden gestartet …');
        stream = await getMedia();
        if (stream) {
            localVideo.srcObject = stream;
            root.classList.toggle('has-local-video', stream.getVideoTracks().length > 0);
            if (!stream.getVideoTracks().length) {
                cameraButton?.setAttribute('disabled', '');
            }
        } else {
            micButton?.setAttribute('disabled', '');
            cameraButton?.setAttribute('disabled', '');
            setState('Kamera und Mikrofon sind nicht freigegeben – Sie sehen und hören die Gegenseite, aber nicht umgekehrt.', 'warn');
        }

        try {
            if (offerer) {
                await makeOffer();
            } else {
                await announce();
            }
        } catch {
            setState('Die Videoverbindung konnte nicht vorbereitet werden.', 'error');
        }
        poll();
    })();
}
