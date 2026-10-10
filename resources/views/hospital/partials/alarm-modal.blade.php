@php
    $patient = $alarm->patient;
    $measurement = $alarm->measurement;
    $sos = $alarm->isSos();
    $mine = $alarm->claimed_by !== null && $alarm->claimed_by === auth()->id();
    $rescue = $alarm->rescueState();
    $emergency = config('cardiopulse.emergency_number');
    $responseSeconds = (int) config('cardiopulse.sos_response_seconds');
@endphp
<div class="alarm-layer__backdrop"></div>
<div class="alarm-layer__frame blink" aria-hidden="true"></div>
<div @class(['alarm-modal', 'alarm-modal--sos' => $sos]) role="alertdialog" aria-modal="true" aria-labelledby="alarm-title" aria-describedby="alarm-desc"
     data-alarm-id="{{ $alarm->id }}" data-alarm-version="{{ $alarm->version() }}" data-triggered="{{ $alarm->triggered_at->toIso8601String() }}">
    <div class="alarm-modal__head">
        <x-icon name="alert-triangle" size="28" stroke="2.2" />
        <div>
            <h2 class="alarm-modal__title" id="alarm-title">{{ $sos ? 'NOTFALL – PATIENT HAT DIE NOTFALLTASTE GEDRÜCKT' : 'GEFÄHRLICH HOHER BLUTDRUCK' }}</h2>
            <div id="alarm-desc">
                @if ($alarm->needsRescueByHospital())
                    Signalton aktiv – bis „Rettung verständigt“ eingetragen oder der Alarm quittiert ist
                @elseif ($alarm->isClaimed())
                    Übernommen – Signalton aus, Alarm bleibt bis zur Quittierung offen
                @else
                    Signalton aktiv – wiederholt, bis jemand übernimmt oder quittiert
                @endif
            </div>
        </div>
        <div class="alarm-modal__timer">
            <div>seit</div>
            <b data-alarm-elapsed>00:00:00</b>
        </div>
    </div>

    <div class="alarm-modal__claim" role="status">
        @if ($alarm->isClaimed())
            <x-icon name="check" size="18" stroke="2.6" />
            <span>
                @if ($mine)
                    <b>Sie kümmern sich um diesen Alarm</b> (seit {{ $alarm->claimed_at?->format('H:i') }})
                @else
                    <b>Übernommen von {{ $alarm->claimedBy?->shortName() ?? 'einem Kollegen' }}</b> um {{ $alarm->claimed_at?->format('H:i') }}
                @endif
            </span>
        @else
            <span><b>Noch niemand kümmert sich.</b> Übernehmen Sie den Alarm, damit Kollegen sich um weitere Alarme kümmern können.</span>
            <form method="POST" action="{{ route('alarms.claim', $alarm) }}" data-claim-form>
                @csrf
                <button type="submit" class="btn btn--primary">Ich übernehme</button>
            </form>
        @endif
    </div>

    @if ($sos && $alarm->false_alarm_at)
        <div class="alarm-modal__false" role="status">
            <b>Patient meldet um {{ $alarm->false_alarm_at->format('H:i') }}: Fehlalarm – keine Hilfe nötig.</b>
            Bitte trotzdem zurückrufen und den Alarm danach quittieren.
        </div>
    @endif

    {{-- Wer ruft die Rettung? Antwort des Patienten bzw. „Rettung verständigt“. --}}
    <div @class(['alarm-modal__rescue', 'alarm-modal__rescue--urgent' => $alarm->needsRescueByHospital(), 'alarm-modal__rescue--done' => $rescue === 'rescue_called']) role="status" data-rescue-state="{{ $rescue ?? 'none' }}">
        <span>
            @switch ($rescue)
                @case ('rescue_called')
                    <b>Rettung verständigt</b> von {{ $alarm->rescueCalledBy?->shortName() ?? 'einem Kollegen' }} um {{ $alarm->rescue_called_at?->format('H:i') }} – der Patient sieht das in der App.
                    @break
                @case ('hospital')
                    <b>Patient bittet um {{ $alarm->responded_at?->format('H:i') }}: Das Krankenhaus soll die Rettung rufen.</b>
                    Notruf {{ $emergency }} wählen und Standort bzw. Wohnadresse durchgeben.
                    @break
                @case ('no_response')
                    <b>Keine Rückmeldung vom Patienten</b> – er kann vielleicht nicht mehr telefonieren. Sofort zurückrufen und bei Bedarf die Rettung ({{ $emergency }}) verständigen.
                    @break
                @case ('self')
                    <b>Patient ruft laut eigener Angabe selbst {{ $emergency }} an</b> ({{ $alarm->responded_at?->format('H:i') }}, nicht bestätigt). Zurückrufen und nachfragen.
                    @break
                @case ('waiting')
                    <b>Patient wird gefragt, wer die Rettung ruft.</b> Ohne Antwort gilt nach {{ $responseSeconds }} Sekunden: Das Krankenhaus ruft.
                    @break
                @default
                    Rettungsdienst verständigt? Hier eintragen, damit Kollegen nicht doppelt anrufen.
            @endswitch
        </span>
        @if ($rescue !== 'rescue_called')
            <form method="POST" action="{{ route('alarms.rescue', $alarm) }}" data-rescue-form>
                @csrf
                <button type="submit" @class(['btn', 'btn--white' => $alarm->needsRescueByHospital(), 'btn--outline-taupe' => ! $alarm->needsRescueByHospital()])>Rettung verständigt</button>
            </form>
        @endif
    </div>

    <div class="alarm-modal__body">
        <div>
            <div class="alarm-modal__name">{{ $patient->fullName() }}</div>
            <div class="meta">
                {{ $patient->age() }} J. · {{ $patient->patient_number }}<br>
                Wohnadresse: {{ $patient->address() }} · Tel. <a href="tel:{{ preg_replace('/\s+/', '', $patient->phone) }}">{{ $patient->phone }}</a>
            </div>
            @if ($measurement?->symptoms)
                <div class="symptom-list">
                    @foreach (explode(', ', $measurement->symptomLabels()) as $symptom)
                        <span class="symptom-tag">{{ $symptom }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        @if ($sos)
            <div class="alarm-modal__location">
                <div class="micro">Standort</div>
                @if ($alarm->location)
                    <a class="btn btn--primary btn--sm btn--start" href="{{ $alarm->mapUrl() }}" target="_blank" rel="noopener noreferrer">
                        <x-icon name="map-pin" size="16" stroke="2.4" />Karte öffnen<span class="sr-only"> (neues Fenster)</span>
                    </a>
                    <div class="alarm-modal__coords">{{ $alarm->coordinates() }}</div>
                    <div class="meta">übermittelt {{ $alarm->located_at?->format('H:i:s') }} Uhr</div>
                @elseif ($patient->location_consent_at === null)
                    <b>Kein Standort</b>
                    <div class="meta">Patient hat der Übermittlung nicht zugestimmt – zuerst an der Wohnadresse oder telefonisch klären.</div>
                @else
                    <b>Noch kein Standort</b>
                    <div class="meta">Wird ermittelt oder wurde am Handy nicht freigegeben. Erscheint hier automatisch.</div>
                @endif
            </div>
        @elseif ($measurement)
            <div class="alarm-modal__value">
                <div class="micro">Upload {{ $measurement->measured_at->format('H:i') }}</div>
                <div class="alarm-modal__bp">{{ $measurement->reading() }}</div>
                <div>mmHg · Puls {{ $measurement->pulse ?? '—' }}</div>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('alarms.acknowledge', $alarm) }}" data-ack-form>
        @csrf
        <div class="alarm-modal__note field">
            <label class="field__label" for="alarm-note">Maßnahme dokumentieren</label>
            <textarea class="textarea" id="alarm-note" name="note" rows="2" maxlength="2000" placeholder="{{ $sos ? 'z. B. Patient zurückgerufen, Rettung (144) mit Standort verständigt …' : 'z. B. Patient zu Hause angerufen, Rettungsdienst an Wohnadresse alarmiert …' }}"></textarea>
        </div>
        <div class="alarm-modal__actions">
            <button type="submit" class="btn btn--danger">Alarm quittieren <span aria-hidden="true">✓</span></button>
            <button type="submit" form="alarm-call-form" class="btn btn--primary btn--start"><x-icon name="phone" size="18" stroke="2.4" />Patient anrufen</button>
        </div>
    </form>
    <form id="alarm-call-form" method="POST" action="{{ route('calls.store', $patient) }}">
        @csrf
    </form>

    <div class="alarm-modal__foot">
        @if ($sos)
            Der Patient wird in der App gefragt, ob er selbst den Notruf {{ $emergency }} wählt oder das Krankenhaus die Rettung rufen soll. Standort bzw. Adresse an die Rettungsleitstelle durchgeben. Der Standort wird beim Quittieren gelöscht.
        @else
            Patient ist zu Hause – bei Bedarf Rettungsdienst ({{ $emergency }}) an die Wohnadresse schicken. Quittierung wird protokolliert.
        @endif
        @if ($more > 0) <b>Weitere offene Alarme: {{ $more }}</b> @endif
        <button type="button" class="link-button" data-minimize-alarm>Board anzeigen – Alarm bleibt aktiv</button>
    </div>
</div>
