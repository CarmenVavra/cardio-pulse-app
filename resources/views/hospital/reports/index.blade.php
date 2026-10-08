@php
    $monthLabel = $month->locale('de')->translatedFormat('F');
@endphp
<x-layouts.hospital title="Monatsberichte" active="reports">
    <div class="detail-grid">
        <nav class="side-list" aria-label="Eingegangene Monatsberichte">
            <div class="side-list__head">
                <span class="micro">Monatsberichte {{ $monthLabel }} · {{ $reports->count() }} eingegangen</span>
                <span class="micro">
                    <a href="{{ route('reports.index', ['month' => $previousMonth->format('Y-m')]) }}" aria-label="Vorheriger Monat">‹</a>
                    @if ($hasNext)
                        <a href="{{ route('reports.index', ['month' => $nextMonth->format('Y-m')]) }}" aria-label="Nächster Monat">›</a>
                    @endif
                </span>
            </div>
            <ul>
                @forelse ($reports as $report)
                    @php $avgStatus = $report->avg_systolic ? \App\Enums\BloodPressureStatus::classify($report->avg_systolic, $report->avg_diastolic) : null; @endphp
                    <li>
                        <a href="{{ route('reports.index', ['month' => $month->format('Y-m'), 'patient' => $report->patient_id]) }}"
                           class="side-item st-{{ $report->worst_status?->value ?? 'none' }}"
                           @if ($selected?->is($report)) aria-current="page" @endif>
                            <span class="stripe"></span>
                            <span class="side-item__text">
                                <span class="side-item__name">{{ $report->patient->fullName() }}</span>
                                <span class="side-item__meta">{{ $report->patient->patient_number }} · {{ $report->sent_at->format('d.m. H:i') }} · {{ $report->measurement_count }} Mess.</span>
                            </span>
                            <span class="side-item__bp bp st-{{ $avgStatus?->value ?? 'none' }}" title="Monatsdurchschnitt">Ø {{ $report->avg_systolic }}/{{ $report->avg_diastolic }}</span>
                        </a>
                    </li>
                @empty
                    <li class="board-empty">Für {{ $monthLabel }} sind noch keine Monatsberichte eingegangen.</li>
                @endforelse
            </ul>
        </nav>

        @if ($overview)
            @include('hospital.partials.patient-detail', [
                'periodLabel' => $monthLabel,
                'periodShort' => $month->locale('de')->translatedFormat('M'),
                'periodMonth' => $month->format('Y-m'),
                'chartTitle' => 'Monatsverlauf '.$month->locale('de')->translatedFormat('F Y'),
            ])
        @else
            <section class="detail list-page">
                <h1>Keine Berichte</h1>
                <p class="meta">Wählen Sie einen anderen Monat.</p>
            </section>
        @endif
    </div>
</x-layouts.hospital>
