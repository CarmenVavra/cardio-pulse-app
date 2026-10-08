@php
    $hour = now()->hour;
    $greeting = $hour < 11 ? 'Guten Morgen' : ($hour < 18 ? 'Guten Tag' : 'Guten Abend');
    $latest = $patient->latestMeasurement;
    $monthLabel = $month->month->locale('de')->translatedFormat('F');
@endphp
<x-layouts.patient title="Start" tab="home">
    <x-slot:header>
        <header class="p-head">
            <div class="p-head__bar">
                <x-logo />
                <a class="bell" href="{{ route('patient.doctor') }}#nachrichten" aria-label="Nachrichten der Klinik{{ $unreadMessages ? ', '.$unreadMessages.' ungelesen' : '' }}">
                    <x-icon name="bell" size="24" />
                    @if ($unreadMessages)
                        <span class="bell__count" aria-hidden="true">{{ $unreadMessages }}</span>
                    @endif
                </a>
            </div>
            <h1 class="p-greeting">{{ $greeting }}, {{ $patient->first_name }}</h1>
            <p class="p-connection">Verbunden mit {{ config('cardiopulse.clinic.name') }} · {{ \Illuminate\Support\Str::afterLast(config('cardiopulse.clinic.ward'), ' ') }}</p>
        </header>
    </x-slot:header>

    <div class="p-content">
        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif

        @if ($latest)
            <section class="last-card st-{{ $latest->status->value }}" aria-label="Letzte Messung">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                    <span class="meta" style="font-weight:600">Letzte Messung · {{ $latest->measured_at->isToday() ? $latest->measured_at->format('H:i') : \App\Support\Format::day($latest->measured_at) }}</span>
                    <span class="tag tag--solid st-{{ $latest->status->value }}">{{ $latest->status->label() }}</span>
                </div>
                <div class="last-card__bp">{{ $latest->reading() }}</div>
                <div>mmHg · Puls {{ $latest->pulse ?? '—' }}</div>
            </section>
        @else
            <section class="card card__pad">
                <b>Noch keine Messung</b>
                <p class="meta">Tragen Sie Ihren ersten Blutdruckwert ein – er wird sofort an Ihr Behandlungsteam übertragen.</p>
            </section>
        @endif

        <a class="btn btn--primary btn--lg btn--block btn--start" href="{{ route('patient.measurements.create') }}" style="font-size:17px;min-height:58px">
            <x-icon name="plus" size="22" stroke="2.6" />Blutdruck eintragen
        </a>

        <section class="card" aria-labelledby="today-title">
            <div class="list-head">
                <h2 id="today-title" style="font-size:13px;font-weight:600;letter-spacing:0">Heute · {{ $today->count() }} {{ $today->count() === 1 ? 'Messung' : 'Messungen' }}</h2>
                <span>{{ now()->locale('de')->isoFormat('dd DD.MM.') }}</span>
            </div>
            @forelse ($today as $measurement)
                <a class="m-row st-{{ $measurement->status->value }}" href="{{ route('patient.measurements.show', $measurement) }}" style="text-decoration:none;color:inherit">
                    <span class="stripe"></span>
                    <span class="m-row__time">{{ $measurement->measured_at->format('H:i') }}</span>
                    <b class="m-row__bp bp">{{ $measurement->reading() }}</b>
                </a>
            @empty
                <p class="meta" style="padding:6px 16px 14px">Heute noch keine Messung.</p>
            @endforelse
        </section>

        <section class="card card__pad" aria-labelledby="month-title">
            <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:600">
                <h2 id="month-title" style="font-size:13px;font-weight:600;letter-spacing:0">Monatsbericht {{ $monthLabel }}</h2>
                <span class="muted">{{ $month->daysWithData() }} / {{ count($month->days) }} Tage</span>
            </div>
            <div class="month-bar" style="grid-template-columns:repeat({{ count($month->days) }},1fr)" role="img" aria-label="An {{ $month->daysWithData() }} von {{ count($month->days) }} Tagen gemessen">
                @foreach ($month->days as $status)
                    <span class="st-{{ $status?->value ?? 'none' }}"></span>
                @endforeach
            </div>
            <p class="meta" style="font-size:12px;margin-top:6px">
                @if ($month->report)
                    Gesendet am {{ $month->report->sent_at->format('d.m.') }} · <a href="{{ route('patient.month') }}">Ansehen</a>
                @elseif ($month->canSend())
                    Bereit zum Senden · <a href="{{ route('patient.month') }}">Jetzt senden</a>
                @else
                    Versand an das Krankenhaus ab {{ $month->sendableFrom()->format('d.m.') }} möglich
                @endif
            </p>
        </section>

        <section class="card" aria-labelledby="meds-title">
            <div class="list-head">
                <h2 id="meds-title" class="med-home-title"><x-icon name="pill" size="16" />Medikation · Erinnerung</h2>
            </div>
            @forelse ($patient->medications as $medication)
                <div class="med-row med-row--home"><span><b>{{ $medication->name }}</b> {{ $medication->dose }}</span><span class="tabular">{{ $medication->schedule }}</span></div>
            @empty
                <p class="meta med-home-empty">Noch keine Medikation erfasst.</p>
            @endforelse
            <div class="med-home-foot">
                <span class="meta">Schema: morgens – mittags – abends</span>
                <a class="btn btn--outline btn--sm" href="{{ route('patient.medications.index') }}">Bearbeiten<span class="sr-only"> – Medikation</span></a>
            </div>
        </section>

        <form method="POST" action="{{ route('logout') }}" class="push-down">
            @csrf
            <button type="submit" class="link-button" style="font-size:13px">Abmelden</button>
        </form>
    </div>
</x-layouts.patient>
