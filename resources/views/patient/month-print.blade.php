@php
    $stats = $summary->stats;
    $monthName = \App\Support\Format::monthName($summary->month);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    @include('partials.head', ['title' => 'Monatsbericht '.$monthName])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="print-page">
    <div class="print-actions no-print">
        <button type="button" class="btn btn--primary" data-print><span class="btn__lead"><x-icon name="printer" size="18" />Drucken / als PDF speichern</span></button>
        <a class="btn btn--outline" href="{{ route('patient.month', $summary->month->format('Y-m')) }}">Zurück</a>
    </div>

    <header class="print-head">
        <div>
            <x-logo light />
            <h1>Monatsbericht {{ $monthName }}</h1>
            <p class="meta">Blutdruck-Selbstmessung · erstellt {{ now()->format('d.m.Y H:i') }}</p>
        </div>
        <div>
            <b>{{ $patient->fullName() }}</b><br>
            <span class="meta">geb. {{ $patient->birth_date->format('d.m.Y') }} · {{ $patient->patient_number }}<br>Hausarzt: {{ $patient->gp_name ?? '—' }}<br>Klinik: {{ config('cardiopulse.clinic.name') }} · {{ $patient->doctor?->displayName() }}</span>
        </div>
    </header>

    <p><b>Messungen:</b> {{ $stats->count }} · <b>Durchschnitt:</b> {{ $stats->average() }} mmHg · <b>Im Normalbereich:</b> {{ $stats->greenPercent() }} %</p>
    <p class="meta">{{ $stats->distributionLabel() }}</p>

    <div class="chart-block" style="padding-left:0;padding-right:0">
        @include('partials.trend-chart', ['chart' => $chart, 'title' => 'Verlauf '.$monthName, 'stats' => $stats])
    </div>

    <table class="print-table">
        <thead><tr><th>Datum</th><th>RR mmHg</th><th>Puls</th><th>Ampel</th><th>Beschwerden</th></tr></thead>
        <tbody>
            @foreach ($summary->measurements as $measurement)
                <tr class="st-{{ $measurement->status->value }}">
                    <td>{{ $measurement->measured_at->format('d.m. H:i') }}</td>
                    <td class="bp">{{ $measurement->reading() }}</td>
                    <td>{{ $measurement->pulse ?? '—' }}</td>
                    <td>{{ $measurement->category()->label() }}</td>
                    <td>{{ $measurement->symptomLabels() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($patient->medications->isNotEmpty())
        <h2 style="font-size:16px;margin-top:20px">Medikation</h2>
        @foreach ($patient->medications as $medication)
            <div class="med-row"><span><b>{{ $medication->name }}</b> {{ $medication->dose }}</span><span>{{ $medication->schedule }}</span></div>
        @endforeach
    @endif

    <p class="meta" style="margin-top:24px"><b>Haftungsausschluss:</b> {{ config('cardiopulse.disclaimer') }}</p>
</main>
</body>
</html>
