@php
    $status = $measurement->status;
@endphp
<x-layouts.patient title="Ihr Ergebnis">
    <x-slot:header>
        <header class="result-head st-{{ $status->value }}">
            <div class="result-head__kicker">IHR ERGEBNIS · {{ $measurement->measured_at->format('H:i') }}</div>
            <h1 class="result-head__status">{{ $status->label() }}</h1>
            <div class="result-head__bp" aria-label="{{ $measurement->systolic }} zu {{ $measurement->diastolic }} mmHg">{{ $measurement->reading() }}</div>
            <div class="result-head__meta">mmHg · Puls {{ $measurement->pulse ?? '—' }}</div>
        </header>
    </x-slot:header>

    <div class="p-content" style="gap:18px;padding-top:20px">
        <div>
            <div class="scale" role="img" aria-label="Einordnung: {{ $status->label() }}">
                <span style="background:#fff;box-shadow:var(--edge)"></span>
                <span style="background:var(--green)"></span>
                <span style="background:var(--amber)"></span>
                <span style="background:var(--red)"></span>
                <span class="scale__marker" style="left:{{ $status->scalePosition($measurement->systolic) }}%"></span>
            </div>
            <div class="scale-labels" aria-hidden="true"><span>Zu niedrig</span><span>Normal</span><span>Zu hoch</span><span>Gefährlich</span></div>
        </div>

        <p class="advice">{{ $status->advice() }}</p>

        <div class="saved-note" role="status">
            <x-icon name="check" size="22" stroke="2.6" />
            <span><b>Gespeichert &amp; ans Krankenhaus übertragen</b><br><span class="meta">Erscheint dort in derselben Farbe</span></span>
        </div>

        <div class="p-actions">
            @if ($status !== \App\Enums\BloodPressureStatus::Green)
                <button type="button" class="btn btn--navy btn--lg btn--block" data-reminder="300" style="font-size:17px">Erinnerung in 5 Min <span aria-hidden="true">→</span></button>
                <p class="meta" data-reminder-status role="status" hidden></p>
            @endif
            <a class="btn btn--outline btn--lg btn--block" href="{{ route('patient.home') }}" style="min-height:52px">Zur Startseite</a>
        </div>
    </div>
</x-layouts.patient>
