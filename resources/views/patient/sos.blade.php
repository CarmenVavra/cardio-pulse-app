@php
    $emergency = config('cardiopulse.emergency_number');
    $consent = $patient->location_consent_at !== null;
@endphp
<x-layouts.patient title="Notfall" theme="red">
    <x-slot:header>
        <header class="p-head p-head--red">
            <div class="p-head__title">
                <a class="p-head__back" href="{{ route('patient.home') }}" aria-label="Zurück zur Startseite"><x-icon name="chevron-left" size="24" stroke="2.2" /></a>
                <h1 class="p-head__h1">Notfall</h1>
            </div>
        </header>
    </x-slot:header>

    <section class="sos" data-sos
             data-status-url="{{ route('patient.sos.status') }}"
             data-location-url="{{ route('patient.sos.location') }}"
             data-consent="{{ $consent ? '1' : '0' }}"
             data-open="{{ $alarm ? '1' : '0' }}"
             data-located="{{ $alarm?->location ? '1' : '0' }}">
        @if (session('status'))
            <div class="sos__note" role="status">{{ session('status') }}</div>
        @endif

        @if ($alarm)
            {{-- Notruf läuft: Krankenhaus ist alarmiert, jetzt selbst 144 anrufen. --}}
            <div class="sos__done" role="status">
                <x-icon name="check" size="22" stroke="2.8" />
                <span><b>Das Krankenhaus ist alarmiert</b> · seit {{ $alarm->triggered_at->format('H:i') }} Uhr</span>
            </div>

            <a class="btn btn--white btn--xl btn--block btn--start sos__call" href="tel:{{ $emergency }}" data-sos-call>
                <x-icon name="phone" size="28" stroke="2.4" />Notruf {{ $emergency }} anrufen
            </a>
            <p class="sos__hint">Die App kann keinen Rettungswagen schicken – bitte rufen Sie jetzt selbst den Notruf an und sagen Sie, wo Sie sind.</p>

            <ul class="sos__facts">
                <li data-sos-claimed>
                    @if ($alarm->claimedBy)
                        <b>{{ $alarm->claimedBy->displayName() }}</b> kümmert sich um Ihren Notruf.
                    @else
                        Ein Arzt wird gerade informiert …
                    @endif
                </li>
                <li data-sos-location>
                    @if (! $consent)
                        Ihr Standort wird nicht übermittelt (keine Freigabe).
                    @elseif ($alarm->location)
                        Ihr Standort wurde an das Krankenhaus übermittelt.
                    @else
                        Ihr Standort wird ermittelt … Bitte erlauben Sie den Zugriff, wenn Ihr Handy fragt.
                    @endif
                </li>
            </ul>

            @if ($alarm->false_alarm_at)
                <p class="sos__note" role="status">Sie haben einen Fehlalarm gemeldet. Das Krankenhaus meldet sich bei Bedarf bei Ihnen.</p>
            @else
                <form method="POST" action="{{ route('patient.sos.false-alarm') }}" class="push-down">
                    @csrf
                    <button type="submit" class="btn btn--outline-light btn--lg btn--block" data-confirm="Fehlalarm melden? Das Krankenhaus wird trotzdem nachsehen, ob alles in Ordnung ist.">Fehlalarm – ich brauche keine Hilfe</button>
                </form>
            @endif
        @else
            @if ($handled)
                <p class="sos__note" role="status">Ihr Notruf von {{ $handled->triggered_at->format('H:i') }} Uhr wurde vom Krankenhaus bearbeitet.</p>
            @endif

            <p class="sos__lead" id="sos-help">Halten Sie die Taste <b>2 Sekunden</b> gedrückt. Das Krankenhaus wird sofort alarmiert, danach rufen Sie den Notruf {{ $emergency }} an.</p>

            <form method="POST" action="{{ route('patient.sos.store') }}" class="sos__form" data-sos-form>
                @csrf
                <input type="hidden" name="lat" data-sos-lat>
                <input type="hidden" name="lng" data-sos-lng>
                <input type="hidden" name="accuracy" data-sos-accuracy>
                <button type="submit" class="sos__button" aria-describedby="sos-help" data-sos-button>
                    <span class="sos__ring" aria-hidden="true"></span>
                    <span class="sos__label">SOS</span>
                    <span class="sos__sub" data-sos-sub>gedrückt halten</span>
                </button>
            </form>

            <div class="sos__countdown" role="alertdialog" aria-modal="true" aria-labelledby="sos-countdown-title" data-sos-countdown hidden>
                <p class="sos__countdown-title" id="sos-countdown-title">Alarm wird ausgelöst in</p>
                <div class="sos__countdown-number" aria-live="assertive"><span data-sos-seconds>5</span> s</div>
                <button type="button" class="btn btn--white btn--xl btn--block" data-sos-cancel>Abbrechen</button>
            </div>

            <p class="sos__consent">
                @if ($consent)
                    <x-icon name="map-pin" size="18" stroke="2.4" /> Ihr Standort wird im Notfall mitgeschickt.
                @else
                    <x-icon name="map-pin" size="18" stroke="2.4" /> Ihr Standort wird <b>nicht</b> mitgeschickt.
                    <a href="{{ route('patient.account.edit') }}#standort">Freigabe einschalten</a>
                @endif
            </p>

            <a class="btn btn--outline-light btn--lg btn--block btn--start push-down" href="tel:{{ $emergency }}">
                <x-icon name="phone" size="22" stroke="2.4" />Nur Notruf {{ $emergency }} anrufen
            </a>
        @endif
    </section>
</x-layouts.patient>
