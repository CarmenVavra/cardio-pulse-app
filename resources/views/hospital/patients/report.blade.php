@php
    $patient = $overview->patient;
    $stats = $overview->stats;
    $range = $overview->from->format('d.m.Y').' – '.$overview->to->format('d.m.Y');
    $monthQuery = request()->query('month') ? ['month' => request()->query('month')] : [];
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    @include('partials.head', ['title' => 'Bericht '.$patient->fullName()])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="print-page">
    <div class="print-actions no-print">
        <button type="button" class="btn btn--primary" data-print><span class="btn__lead"><x-icon name="printer" size="18" />Drucken / als PDF speichern</span></button>
        <a class="btn btn--outline" href="{{ route('patients.fhir', [$patient, ...$monthQuery]) }}"><span class="btn__lead"><x-icon name="download" size="18" />KIS-Export (HL7 FHIR R4)</span></a>
        <a class="btn btn--outline" href="{{ route('patients.show', $patient) }}">Zurück</a>
    </div>

    <header class="print-head">
        <div>
            <x-logo light />
            <h1>Blutdruck-Verlaufsbericht</h1>
            <p class="meta">{{ config('cardiopulse.clinic.name') }} · {{ config('cardiopulse.clinic.ward') }} · erstellt {{ now()->format('d.m.Y H:i') }}</p>
        </div>
        <div>
            <b>{{ $patient->fullName() }}</b><br>
            <span class="meta">{{ $patient->patient_number }} · geb. {{ $patient->birth_date->format('d.m.Y') }}<br>{{ $patient->address() }}<br>Hausarzt: {{ $patient->gp_name ?? '—' }}</span>
        </div>
    </header>

    <p><b>Zeitraum:</b> {{ $range }} · <b>Messungen:</b> {{ $stats->count }} · <b>Durchschnitt:</b> {{ $stats->average() }} mmHg · <b>Im Normalbereich:</b> {{ $stats->greenPercent() }} %</p>
    <p class="meta">{{ $stats->distributionLabel() }}</p>

    <div class="chart-block" style="padding-left:0;padding-right:0">
        @include('partials.trend-chart', ['chart' => $overview->chart, 'title' => 'Verlauf '.$range, 'stats' => $stats])
    </div>

    <table class="print-table">
        <thead><tr><th>Datum</th><th>RR mmHg</th><th>Puls</th><th>Ampel</th><th>Symptome</th></tr></thead>
        <tbody>
            @foreach ($measurements as $measurement)
                <tr class="st-{{ $measurement->status->value }}">
                    <td>{{ $measurement->measured_at->format('d.m.Y H:i') }}</td>
                    <td class="bp">{{ $measurement->reading() }}</td>
                    <td>{{ $measurement->pulse ?? '—' }}</td>
                    <td>{{ $measurement->status->label() }}</td>
                    <td>{{ $measurement->symptomLabels() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 style="font-size:16px;margin-top:20px">Medikation</h2>
    @foreach ($patient->medications as $medication)
        <div class="med-row"><span><b>{{ $medication->name }}</b> {{ $medication->dose }}</span><span>{{ $medication->schedule }}</span></div>
    @endforeach

    <p class="meta" style="margin-top:24px"><b>Haftungsausschluss:</b> {{ config('cardiopulse.disclaimer') }}</p>
</main>
</body>
</html>
