@props(['dismissible' => false])
{{--
    „Als App installieren“ – erscheint nur im Browser, nicht in der schon installierten App.
    Welche Anleitung passt (Android-Knopf, iPhone-Anleitung, allgemeiner Hinweis), entscheidet install-app.js.
--}}
<section class="card card__pad install-app" aria-labelledby="install-title" data-install @if ($dismissible) data-install-dismissible @endif hidden>
    <div class="install-app__head">
        <img src="{{ asset('app-icons/icon-192.png') }}" alt="" width="44" height="44">
        <div>
            <h2 id="install-title" class="install-app__title">CardioPulse als App</h2>
            <p class="meta">Mit eigenem Symbol am Startbildschirm, ohne Browser-Leiste – Sie bleiben angemeldet.</p>
        </div>
    </div>

    <button type="button" class="btn btn--primary btn--lg btn--block" data-install-prompt hidden>App installieren</button>

    <ol class="install-app__steps" data-install-ios hidden>
        <li>Tippen Sie unten in Safari auf <b>Teilen</b> (Quadrat mit Pfeil nach oben).</li>
        <li>Wählen Sie <b>„Zum Home-Bildschirm“</b> und dann <b>„Hinzufügen“</b>.</li>
        <li>Öffnen Sie CardioPulse ab jetzt über das neue Symbol.</li>
    </ol>

    <p class="meta" data-install-other hidden>Öffnen Sie das Menü Ihres Browsers (⋮) und wählen Sie <b>„App installieren“</b> bzw. <b>„Zum Startbildschirm hinzufügen“</b>.</p>

    @if ($dismissible)
        <button type="button" class="link-button install-app__later" data-install-dismiss>Später</button>
    @endif
</section>
