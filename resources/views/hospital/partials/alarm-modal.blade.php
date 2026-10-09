@php
    $patient = $alarm->patient;
    $measurement = $alarm->measurement;
@endphp
<div class="alarm-layer__backdrop"></div>
<div class="alarm-layer__frame blink" aria-hidden="true"></div>
<div class="alarm-modal" role="alertdialog" aria-modal="true" aria-labelledby="alarm-title" aria-describedby="alarm-desc"
     data-alarm-id="{{ $alarm->id }}" data-triggered="{{ $alarm->triggered_at->toIso8601String() }}">
    <div class="alarm-modal__head">
        <x-icon name="alert-triangle" size="28" stroke="2.2" />
        <div>
            <h2 class="alarm-modal__title" id="alarm-title">GEFÄHRLICH HOHER BLUTDRUCK</h2>
            <div id="alarm-desc">Signalton aktiv – wiederholt bis zur Quittierung</div>
        </div>
        <div class="alarm-modal__timer">
            <div>seit</div>
            <b data-alarm-elapsed>00:00:00</b>
        </div>
    </div>

    <div class="alarm-modal__body">
        <div>
            <div class="alarm-modal__name">{{ $patient->fullName() }}</div>
            <div class="meta">
                {{ $patient->age() }} J. · {{ $patient->patient_number }} · zu Hause<br>
                {{ $patient->address() }} · Tel. <a href="tel:{{ preg_replace('/\s+/', '', $patient->phone) }}">{{ $patient->phone }}</a>
            </div>
            @if ($measurement->symptoms)
                <div class="symptom-list">
                    @foreach (explode(', ', $measurement->symptomLabels()) as $symptom)
                        <span class="symptom-tag">{{ $symptom }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="alarm-modal__value">
            <div class="micro">Upload {{ $measurement->measured_at->format('H:i') }}</div>
            <div class="alarm-modal__bp">{{ $measurement->reading() }}</div>
            <div>mmHg · Puls {{ $measurement->pulse ?? '—' }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('alarms.acknowledge', $alarm) }}" data-ack-form>
        @csrf
        <div class="alarm-modal__note field">
            <label class="field__label" for="alarm-note">Maßnahme dokumentieren</label>
            <textarea class="textarea" id="alarm-note" name="note" rows="2" maxlength="2000" placeholder="z. B. Patient zu Hause angerufen, Rettungsdienst an Wohnadresse alarmiert …"></textarea>
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
        Patient ist zu Hause – bei Bedarf Rettungsdienst ({{ config('cardiopulse.emergency_number') }}) an die Wohnadresse schicken. Quittierung wird protokolliert.
        @if ($more > 0) <b>Weitere offene Alarme: {{ $more }}</b> @endif
        <button type="button" class="link-button" data-minimize-alarm>Board anzeigen – Alarm und Signalton bleiben aktiv</button>
    </div>
</div>
