@php
    use App\Enums\CallDirection;
    use App\Enums\CallStatus;

    $patient = $call->patient;
    $isOpen = $call->status->isOpen();
    $kicker = match ($call->status) {
        CallStatus::Active => 'LAUFENDES GESPRÄCH · VERSCHLÜSSELT',
        CallStatus::Ringing => $call->direction === CallDirection::ToPatient ? 'VERBINDE · KLINGELT BEI PATIENT' : 'EINGEHENDER ANRUF · APP',
        CallStatus::Ended => 'GESPRÄCH BEENDET',
        CallStatus::Declined => 'NICHT ANGENOMMEN',
    };
@endphp
<x-layouts.hospital :title="'Telefonat '.$patient->fullName()" active="calls" page="call">
    <div class="call-grid"
         data-call
         data-status-url="{{ route('calls.status', $call) }}"
         data-status="{{ $call->status->value }}"
         data-elapsed="{{ $call->durationSeconds() }}">
        <section class="call-stage" aria-labelledby="call-name">
            <div class="call-stage__kicker">{{ $kicker }}</div>
            <h1 class="call-stage__name" id="call-name">{{ $patient->fullName() }}</h1>
            <div class="call-stage__info">
                App-Anruf · {{ $patient->phone }} · {{ $call->answered_at ? 'seit '.$call->answered_at->format('H:i') : 'gestartet '.$call->created_at->format('H:i') }}
                @if ($call->user) · {{ $call->user->shortName() }} @endif
            </div>
            <div class="call-stage__timer" data-call-timer aria-live="off">
                {{ $call->answered_at ? \App\Support\Format::duration($call->durationSeconds()) : '--:--' }}
            </div>
            <div @class(['wave', 'is-idle' => $call->status !== CallStatus::Active]) data-wave aria-hidden="true">
                @for ($i = 0; $i < 40; $i++)
                    <span style="height:{{ 6 + (($i * 37) % 30) }}px"></span>
                @endfor
            </div>

            @if ($isOpen)
                <div class="call-controls">
                    @if ($call->status === CallStatus::Ringing && $call->direction === CallDirection::ToClinic)
                        <form method="POST" action="{{ route('calls.answer', $call) }}" style="grid-column:1 / span 3">
                            @csrf
                            <button type="submit" class="btn btn--success btn--block" style="min-height:60px">Annehmen <x-icon name="phone" size="20" stroke="2.4" /></button>
                        </form>
                    @else
                        <button type="button" class="btn btn--outline-taupe" aria-pressed="false" data-toggle><span class="btn__lead"><x-icon name="mic-off" size="18" />Stumm</span></button>
                        <button type="button" class="btn btn--outline-taupe" aria-pressed="false" data-toggle><span class="btn__lead"><x-icon name="volume-2" size="18" />Lautsprecher</span></button>
                        <button type="button" class="btn btn--outline-taupe" aria-pressed="false" data-toggle><span class="btn__lead"><x-icon name="pause" size="18" />Halten</span></button>
                    @endif
                    <form method="POST" action="{{ route('calls.end', $call) }}">
                        @csrf
                        <button type="submit" class="btn btn--danger btn--block" style="min-height:60px">Auflegen <span aria-hidden="true">→</span></button>
                    </form>
                </div>
            @else
                <div class="call-controls">
                    <a class="btn btn--primary" href="{{ route('board') }}" style="grid-column:1 / span 2">Zur Überwachung <span aria-hidden="true">→</span></a>
                    <form method="POST" action="{{ route('calls.store', $patient) }}">
                        @csrf
                        <button type="submit" class="btn btn--outline-taupe btn--block" style="min-height:60px"><span class="btn__lead"><x-icon name="phone" size="18" />Erneut anrufen</span></button>
                    </form>
                </div>
            @endif
        </section>

        <aside class="call-side" aria-labelledby="call-values">
            <div class="call-side__head">
                <div class="micro">Während des Gesprächs</div>
                <b id="call-values" style="font-size:20px">Werte von heute</b>
            </div>
            @foreach ($todayMeasurements as $measurement)
                <div class="call-side__row st-{{ $measurement->status->value }}">
                    <span class="stripe"></span>
                    <span style="padding:0 16px;font-size:14px"><b>{{ \App\Support\Format::day($measurement->measured_at) }}</b> · {{ $measurement->symptomLabels() }}</span>
                    <b class="bp" style="padding-right:24px;font-size:20px">{{ $measurement->reading() }}</b>
                </div>
            @endforeach
            @if ($openAlarmCount > 0)
                <div class="alert alert--error" style="margin:16px 24px 0">Offener Alarm für diesen Patienten – nach dem Gespräch auf dem Überwachungsscreen quittieren.</div>
            @endif

            <form method="POST" action="{{ route('calls.note', $call) }}" class="call-side__note">
                @csrf
                @method('PUT')
                <label class="field__label" for="call-note">Gesprächsnotiz</label>
                <textarea class="textarea" id="call-note" name="note" maxlength="5000" required placeholder="z. B. Brustdruck rückläufig, Medikation angepasst, erneute Messung in 15 Min …">{{ old('note', $call->note) }}</textarea>
                @error('note')<div class="field-error" role="alert">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn--primary btn--lg btn--block">Notiz speichern <span aria-hidden="true">→</span></button>
            </form>
        </aside>
    </div>
</x-layouts.hospital>
