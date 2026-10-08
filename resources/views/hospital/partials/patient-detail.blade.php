@php
    /** @var \App\Support\PatientOverview $overview */
    $patient = $overview->patient;
    $latest = $patient->latestMeasurement;
    $alarm = $patient->latestAlarm;
    $report = $patient->latestMonthlyReport;
    $chart = $overview->chart;
    $stats = $overview->stats;
    $avgStatus = $stats->averageStatus();
    $exportQuery = isset($periodMonth) ? ['month' => $periodMonth] : [];
@endphp
<section class="detail" aria-labelledby="patient-name">
    <header class="detail__head">
        <div>
            @if ($latest)
                <x-status-tag :status="$latest->status"
                    :label="$latest->status->label().($alarm ? ($alarm->isOpen() ? ' · Alarm offen' : ' · quittiert '.$alarm->acknowledged_at?->format('H:i')) : '')" />
            @endif
            <h1 class="detail__name" id="patient-name">{{ $patient->fullName() }}</h1>
            <p class="meta">
                {{ $patient->age() }} J. · {{ $patient->patient_number }}
                @if ($patient->diagnosis) · {{ $patient->diagnosis }} @endif
                @if ($patient->gp_name) · Hausarzt {{ $patient->gp_name }} @endif
                @if ($report) · Monatsbericht {{ $report->month->locale('de')->translatedFormat('M') }} erhalten {{ $report->sent_at->format('d.m.') }} @endif
            </p>
            <p class="meta">{{ $patient->address() }} · Tel. <a href="tel:{{ preg_replace('/\s+/', '', $patient->phone) }}">{{ $patient->phone }}</a></p>
        </div>
        <div class="detail__actions">
            <form method="POST" action="{{ route('calls.store', $patient) }}">
                @csrf
                <button type="submit" class="btn btn--primary"><span class="btn__lead"><x-icon name="phone" size="18" stroke="2.2" />Anrufen</span></button>
            </form>
            <a class="btn btn--outline" href="#nachrichten"><span class="btn__lead"><x-icon name="message-square" size="18" />Nachricht</span></a>
            <a class="btn btn--outline" href="{{ route('patients.report', [$patient, ...$exportQuery]) }}"><span class="btn__lead"><x-icon name="file-text" size="18" />PDF / KIS-Export</span></a>
            <a class="btn btn--outline" href="{{ route('patients.edit', $patient) }}">Bearbeiten<span class="sr-only">: {{ $patient->fullName() }}</span></a>
        </div>
    </header>

    <div class="stat-row">
        <div class="stat {{ $latest ? 'st-'.$latest->status->value : '' }}">
            <div class="stat__label">Letzter Wert{{ $latest ? ' · '.$latest->measured_at->format('H:i') : '' }}</div>
            <div class="stat__value bp">{{ $latest?->reading() ?? '—' }}</div>
        </div>
        <div class="stat {{ $avgStatus ? 'st-'.$avgStatus->value : '' }}">
            <div class="stat__label">Ø {{ $periodLabel }}</div>
            <div class="stat__value bp">{{ $stats->average() }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Messungen {{ $periodShort }}</div>
            <div class="stat__value">{{ $stats->count }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Verteilung</div>
            <div class="dist-bar" role="img" aria-label="Verteilung: {{ $stats->distributionLabel() }}">
                <span style="flex:{{ $stats->distribution['red'] }};background:var(--red)"></span>
                <span style="flex:{{ $stats->distribution['amber'] }};background:var(--amber)"></span>
                <span style="flex:{{ $stats->distribution['green'] }};background:var(--green)"></span>
                <span style="flex:{{ $stats->distribution['blue'] }};background:var(--blue)"></span>
            </div>
            <div class="meta" style="margin-top:6px">{{ $stats->distributionLabel() }}</div>
        </div>
    </div>

    <div class="chart-block">
        <div class="chart-legend">
            <b>{{ $chartTitle }} · alle Messungen</b>
            <span><i style="background:var(--navy)"></i>Systolisch</span>
            <span><i style="background:var(--taupe)"></i>Diastolisch</span>
        </div>
        @include('partials.trend-chart', ['chart' => $chart, 'title' => $chartTitle, 'stats' => $stats])
    </div>

    <div class="detail__bottom">
        <section aria-labelledby="uploads-title">
            <h2 id="uploads-title">Letzte Uploads</h2>
            @forelse ($overview->recentUploads as $upload)
                <div class="upload-row st-{{ $upload->status->value }}">
                    <span class="stripe"></span>
                    <span style="font-weight:600">{{ \App\Support\Format::day($upload->measured_at) }}</span>
                    <span class="muted">{{ $upload->symptomLabels() }}</span>
                    <b class="bp">{{ $upload->reading() }}</b>
                </div>
            @empty
                <p class="meta">Noch keine Uploads.</p>
            @endforelse
        </section>
        <section aria-labelledby="meds-title">
            <h2 id="meds-title">Medikation</h2>
            <p class="meta">Schema: morgens – mittags – abends</p>
            <x-medication-list :patient="$patient" :routes="[
                'store' => route('patients.medications.store', $patient),
                'update' => fn ($medication) => route('patients.medications.update', [$patient, $medication]),
                'destroy' => fn ($medication) => route('patients.medications.destroy', [$patient, $medication]),
            ]" />
        </section>
    </div>

    <section class="detail__chat" id="nachrichten" aria-labelledby="chat-title" tabindex="-1">
        <h2 id="chat-title">Nachrichten</h2>
        <div class="detail__chat-grid">
            <x-message-thread :messages="$overview->messages" viewer="clinic" empty="Noch keine Nachrichten mit {{ $patient->fullName() }}." />

            <form method="POST" action="{{ route('patients.messages.store', $patient) }}" class="chat-form" novalidate>
                @csrf
                <div class="field">
                    <label class="field__label" for="message-body">Nachricht an {{ $patient->fullName() }} (erscheint in der Patienten-App)</label>
                    <textarea class="textarea" id="message-body" name="body" rows="4" maxlength="2000" required
                              placeholder="z. B. Bitte morgen früh vor der Tabletteneinnahme erneut messen."
                              @error('body') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('body') }}</textarea>
                    @error('body')<div class="field-error" id="message-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <div><button type="submit" class="btn btn--primary">Senden <span aria-hidden="true">→</span></button></div>
            </form>
        </div>
    </section>
</section>
