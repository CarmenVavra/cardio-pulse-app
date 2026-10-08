import { initCommon } from './common';

initCommon();

/*
 * D1: Der Klick auf "Anmelden" ist eine Benutzergeste – damit wird die Audio-Ausgabe
 * für diesen Bildschirm freigeschaltet (Browser erlauben Ton erst nach einer Geste).
 */
const loginForm = document.querySelector('[data-audio-unlock]');
if (loginForm) {
    loginForm.addEventListener('submit', () => {
        const checkbox = loginForm.querySelector('[data-sound-checkbox]');
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!checkbox?.checked || !Ctx) {
            return;
        }
        try {
            const ctx = new Ctx();
            const gain = ctx.createGain();
            gain.gain.value = 0.0001;
            const osc = ctx.createOscillator();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.05);
        } catch {
            /* Audio nicht verfügbar */
        }
    });
}
