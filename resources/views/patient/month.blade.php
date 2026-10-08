@php
    $stats = $summary->stats;
    $avgStatus = $stats->averageStatus();
    $monthKey = $summary->month->format('Y-m');
@endphp
<x-layouts.patient title="Monatsübersicht" tab="month">
    <x-slot:header>
        <header class="p-head">
            <div class="month-nav">
                <h1>{{ \App\Support\Format::monthName($summary->month) }}</h1>
                <div class="month-nav__arrows">
                    <a href="{{ route('patient.month', $previousMonth->format('Y-m')) }}" aria-label="Vorheriger Monat"><x-icon name="chevron-left" size="22" /></a>
                    @if ($hasNext)
                        <a href="{{ route('patient.month', $nextMonth->format('Y-m')) }}" aria-label="Nächster Monat"><x-icon name="chevron-right" size="22" /></a>
                    @else
                        <span aria-hidden="true"><x-icon name="chevron-right" size="22" /></span>
                    @endif
                </div>
            </div>
        </header>
    </x-slot:header>

    <div class="p-content">
        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif
        @error('month')
            <div class="alert alert--error" role="alert">{{ $message }}</div>
        @enderror

        <section class="card card__pad" aria-label="Kalender">
            <div class="calendar" aria-hidden="true">
                @foreach (['MO', 'DI', 'MI', 'DO', 'FR', 'SA', 'SO'] as $dow)
                    <span class="calendar__dow">{{ $dow }}</span>
                @endforeach
            </div>
            <ul class="calendar">
                @for ($i = 0; $i < $summary->leadingBlanks(); $i++)
                    <li class="calendar__day is-blank" aria-hidden="true"></li>
                @endfor
                @foreach ($summary->days as $day => $status)
                    <li class="calendar__day st-{{ $status?->value ?? 'none' }}"
                        aria-label="{{ $day }}. {{ \App\Support\Format::monthName($summary->month) }}: {{ $status?->label() ?? 'keine Messung' }}">{{ $day }}</li>
                @endforeach
                @for ($i = 0; $i < $summary->trailingBlanks(); $i++)
                    <li class="calendar__day is-blank" aria-hidden="true"></li>
                @endfor
            </ul>
            <p class="meta" style="font-size:12px;margin-top:8px">Farbe = höchster Wert des Tages</p>
        </section>

        <section class="mini-stats" aria-label="Kennzahlen">
            <div><small>Messungen</small><b>{{ $stats->count }}</b></div>
            <div class="{{ $avgStatus ? 'st-'.$avgStatus->value : '' }}"><small>Durchschnitt</small><b class="bp">{{ $stats->average() }}</b></div>
            <div><small>Im Normalbereich</small><b>{{ $stats->greenPercent() }} %</b></div>
        </section>

        <div class="card card__pad push-down" style="font-size:14px;line-height:1.4">
            @if ($summary->report)
                Gesendet am <b>{{ $summary->report->sent_at->format('d.m.Y H:i') }}</b> an <b>{{ config('cardiopulse.clinic.name') }} · {{ config('cardiopulse.clinic.ward') }}</b>.
            @elseif ($summary->canSend())
                Ihr Monatsbericht ist bereit. Er geht an <b>{{ config('cardiopulse.clinic.name') }} · {{ config('cardiopulse.clinic.ward') }}</b>.
            @elseif ($stats->count === 0)
                In diesem Monat wurden keine Messungen erfasst.
            @else
                Ihr Monatsbericht kann ab dem <b>{{ $summary->sendableFrom()->format('d.m.') }}</b> an das Krankenhaus gesendet werden.
            @endif
        </div>

        <form method="POST" action="{{ route('patient.month.send', $monthKey) }}">
            @csrf
            <button type="submit" class="btn btn--primary btn--lg btn--block btn--start" style="font-size:17px;min-height:58px" @disabled(! $summary->canSend())>
                <x-icon name="upload" size="20" stroke="2.4" />{{ $summary->report ? 'Erneut an Krankenhaus senden' : 'An Krankenhaus senden' }}
            </button>
        </form>
        <a class="btn btn--outline btn--block" href="{{ route('patient.month.print', $monthKey) }}" style="min-height:48px;font-size:15px">Als PDF für Hausarzt speichern</a>
    </div>
</x-layouts.patient>
