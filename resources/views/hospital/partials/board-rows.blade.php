@forelse ($rows as $row)
    @php
        $patient = $row->patient;
        $measurement = $row->measurement;
        $isRed = $row->status === \App\Enums\BloodPressureStatus::Red;
    @endphp
    <tr class="board-row st-{{ $row->status?->value ?? 'none' }}"
        data-upload-key="{{ $row->uploadKey() }}"
        data-search="{{ $locked ? mb_strtolower($patient->patient_number) : mb_strtolower($patient->fullName().' '.$patient->patient_number) }}">
        <td class="stripe-cell"><span class="stripe"></span></td>
        <td class="col-tag col-pad"><x-status-tag :status="$row->status" short /></td>
        <td class="col-name col-pad">
            <div class="board-row__name">
                @if ($locked)
                    <span>{{ $patient->patient_number }} ·</span>
                    <span class="masked" role="img" aria-label="Name ausgeblendet"></span>
                @else
                    <a href="{{ route('patients.show', $patient) }}">{{ $patient->fullName() }}</a>
                    @if ($row->unreadMessages)
                        <a class="msg-badge" href="{{ route('patients.show', $patient) }}#nachrichten"><x-icon name="message-square" size="12" stroke="2.4" />{{ $row->unreadMessages }}<span class="sr-only"> {{ $row->unreadMessages === 1 ? 'neue Nachricht' : 'neue Nachrichten' }} von {{ $patient->fullName() }}</span></a>
                    @endif
                    <span class="board-row__meta">{{ $patient->age() }} J. · zu Hause · {{ $patient->city }}</span>
                @endif
            </div>
        </td>
        <td class="col-bp">
            @if ($measurement)
                <span class="bp board-row__bp">{{ $measurement->reading() }}</span>
            @else
                <span class="muted">—</span>
            @endif
        </td>
        @unless ($locked)
            <td class="col-pulse">{{ $measurement?->pulse ?? '—' }}</td>
            <td class="col-trend">
                @if ($row->sparkline)
                    <svg class="spark" width="100" height="28" viewBox="0 0 100 28" role="img" aria-label="Systolischer Verlauf der letzten 7 Tage"><path d="{{ $row->sparkline }}" fill="none" stroke-width="2"/></svg>
                @endif
            </td>
            <td class="col-symptoms"><div class="board-row__symptoms">{{ $measurement?->symptomLabels() ?? '—' }}</div></td>
        @endunless
        <td class="col-upload">
            <div class="board-row__upload">
                @if ($row->uploadedAt)
                    <b>{{ \App\Support\Format::ago($row->uploadedAt) }}</b>
                    <small>{{ $row->uploadKind }}</small>
                @else
                    <span class="muted">—</span>
                @endif
            </div>
        </td>
        @unless ($locked)
            <td class="col-action">
                <form method="POST" action="{{ route('calls.store', $patient) }}">
                    @csrf
                    <button type="submit" @class(['btn', 'call-btn', 'call-btn--urgent' => $isRed]) aria-label="{{ $isRed ? 'Sofort anrufen' : 'Anrufen' }}: {{ $patient->fullName() }}">
                        <x-icon name="phone" size="14" stroke="2.4" />{{ $isRed ? 'Sofort anrufen' : 'Anrufen' }}
                    </button>
                </form>
            </td>
        @endunless
    </tr>
@empty
    <tr><td colspan="9" class="board-empty">Noch keine Patientinnen oder Patienten im Telemonitoring.</td></tr>
@endforelse
