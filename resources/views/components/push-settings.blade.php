@props(['dismissible' => false])
{{--
    Push-Benachrichtigungen ein-/ausschalten. Den Zustand (unterstützt, erlaubt, eingeschaltet)
    kennt nur der Browser – push.js zeigt den passenden Text und Knopf.
--}}
@if ($pushPublicKey)
    <section class="card card__pad push-settings" aria-labelledby="push-title-{{ $dismissible ? 'home' : 'account' }}" data-push-card @if ($dismissible) data-push-dismissible @endif hidden>
        <h2 id="push-title-{{ $dismissible ? 'home' : 'account' }}" class="push-settings__title"><x-icon name="bell" size="20" stroke="2.4" />Benachrichtigungen</h2>
        <p class="meta">Ihr Handy meldet sich, wenn Ihr Arzt anruft, eine Nachricht schickt oder eine Videosprechstunde ansteht – auch wenn CardioPulse geschlossen ist. Auf dem Sperrbildschirm stehen keine Gesundheitsdaten.</p>
        <p class="push-settings__state" data-push-state role="status" aria-live="polite"></p>
        <button type="button" class="btn btn--primary btn--lg btn--block" data-push-enable hidden>Benachrichtigungen einschalten</button>
        <button type="button" class="btn btn--outline btn--lg btn--block" data-push-disable hidden>Benachrichtigungen ausschalten</button>
        @if ($dismissible)
            <button type="button" class="link-button push-settings__later" data-push-dismiss>Später</button>
        @endif
    </section>
@endif
