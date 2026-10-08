<x-layouts.hospital :title="$overview->patient->fullName()" active="patients">
    <div class="detail-grid">
        <nav class="side-list" aria-label="Patientenliste">
            <div class="side-list__head">
                <span class="micro">Patienten zu Hause · {{ $rows->count() }}</span>
                <a class="btn btn--primary btn--sm" href="{{ route('patients.create') }}"><x-icon name="plus" size="14" stroke="2.6" />Patient anlegen</a>
            </div>
            <ul>
                @foreach ($rows as $row)
                    <li>
                        <a href="{{ route('patients.show', $row->patient) }}"
                           class="side-item st-{{ $row->status?->value ?? 'none' }}"
                           @if ($row->patient->is($overview->patient)) aria-current="page" @endif>
                            <span class="stripe"></span>
                            <span class="side-item__text">
                                <span class="side-item__name">{{ $row->patient->fullName() }}</span>
                                <span class="side-item__meta">{{ $row->patient->patient_number }} · {{ $row->uploadedAt ? \App\Support\Format::ago($row->uploadedAt) : '—' }}</span>
                            </span>
                            <span class="side-item__bp bp">{{ $row->measurement?->reading() ?? '—' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @include('hospital.partials.patient-detail', [
            'periodLabel' => '30 Tage',
            'periodShort' => '30 T',
            'chartTitle' => '30-Tage-Verlauf',
        ])
    </div>
</x-layouts.hospital>
