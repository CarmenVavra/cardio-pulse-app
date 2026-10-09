@if ($openAlarms->isNotEmpty())
    @php
        $alarm = $openAlarms->first();
        $more = $openAlarms->count() - 1;
        $sos = $alarm->isSos();
    @endphp
    <div @class(['alarm-banner', 'alarm-banner--sos' => $sos]) role="alert" data-alarm-active="{{ $alarm->id }}">
        <span class="alarm-banner__dot blink" aria-hidden="true"></span>
        <b class="alarm-banner__title">{{ $sos ? 'NOTFALL · NOTFALLTASTE' : 'GEFÄHRLICH HOHER WERT' }}</b>
        <span class="alarm-banner__text">
            {{ $locked ? $alarm->patient->patient_number : $alarm->patient->fullName() }}
            @if ($sos)
                · {{ $alarm->location ? 'Standort übermittelt' : 'ohne Standort' }}
                @if ($alarm->false_alarm_at) · Patient meldet Fehlalarm @endif
            @elseif ($alarm->measurement)
                · {{ $alarm->measurement->reading() }} mmHg
                @if ($alarm->measurement->symptoms) · {{ $alarm->measurement->symptomLabels() }} @endif
            @endif
            · {{ \App\Support\Format::ago($alarm->triggered_at) }}
            @if ($alarm->isClaimed()) · übernommen von {{ $alarm->claimedBy?->shortName() ?? 'Kollegen' }} @endif
            @if ($more > 0) · <b>+ {{ $more }} {{ $more === 1 ? 'weiterer' : 'weitere' }}</b> @endif
        </span>
        <div class="alarm-banner__actions">
            <button type="button" class="btn btn--outline-light" data-sound-toggle aria-pressed="false">
                <span class="btn__lead"><x-icon name="volume-2" size="18" stroke="2.2" /><span data-sound-label>Signalton an</span></span>
            </button>
            @unless ($locked)
                <button type="button" class="btn btn--white" data-open-alarm>{{ $alarm->isClaimed() ? 'Alarm öffnen' : 'Alarm bearbeiten' }} <span aria-hidden="true">→</span></button>
            @endunless
        </div>
    </div>
@elseif ($acknowledged)
    <div class="alarm-banner alarm-banner--acked" role="status">
        <b>Alarm quittiert</b>
        <span class="alarm-banner__text">
            {{ $locked ? $acknowledged->patient->patient_number : $acknowledged->patient->fullName() }}
            @if ($acknowledged->isSos()) · Notfalltaste @endif
            · durch {{ $acknowledged->acknowledgedBy?->shortName() ?? 'unbekannt' }}
            · {{ $acknowledged->acknowledged_at?->format('H:i') }}
            @if ($acknowledged->action_note && ! $locked) · {{ \Illuminate\Support\Str::limit($acknowledged->action_note, 80) }} @endif
        </span>
    </div>
@endif
