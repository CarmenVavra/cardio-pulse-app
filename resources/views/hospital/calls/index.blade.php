<x-layouts.hospital title="Anrufe" active="calls">
    <div class="list-page">
        <h1>Anrufe</h1>

        @if ($errors->appointment->any())
            <div class="alert alert--error" role="alert">{{ $errors->appointment->first() }}</div>
        @endif

        <section aria-labelledby="appointments">
            <h2 id="appointments" class="micro">Videosprechstunden · {{ \App\Http\Requests\AppointmentFilterRequest::DOCTORS[$appointmentFilters['doctor']] }} · {{ \App\Http\Requests\AppointmentFilterRequest::RANGES[$appointmentFilters['range']] }}</h2>
            <form method="GET" action="{{ route('calls.index') }}" class="filter-form appointment-filter" aria-label="Videosprechstunden filtern">
                <div class="field">
                    <label class="field__label" for="appointment-doctor">Ärzte</label>
                    <select class="select" id="appointment-doctor" name="doctor">
                        @foreach (\App\Http\Requests\AppointmentFilterRequest::DOCTORS as $value => $label)
                            <option value="{{ $value }}" @selected($appointmentFilters['doctor'] === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="appointment-range">Zeitraum</label>
                    <select class="select" id="appointment-range" name="range">
                        @foreach (\App\Http\Requests\AppointmentFilterRequest::RANGES as $value => $label)
                            <option value="{{ $value }}" @selected($appointmentFilters['range'] === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-actions appointment-filter__actions">
                    <button type="submit" class="btn btn--outline">Anzeigen</button>
                    <span class="meta">{{ $appointments->count() }} {{ $appointments->count() === 1 ? 'Termin' : 'Termine' }}</span>
                </div>
            </form>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Termin</th><th>Patient</th><th class="hide-sm">Arzt</th><th class="hide-md">Anlass</th><th><span class="sr-only">Aktion</span></th></tr></thead>
                    <tbody>
                        @forelse ($appointments as $appointment)
                            <tr @class(['is-due' => $appointment->canStart()])>
                                <td class="nowrap"><b>{{ \App\Support\Format::day($appointment->starts_at) }}</b><br><span class="meta">{{ $appointment->starts_at->locale('de')->translatedFormat('D') }} · {{ $appointment->durationMinutes() }} Min</span></td>
                                <td><a href="{{ route('patients.show', $appointment->patient) }}#termine">{{ $appointment->patient->fullName() }}</a><br><span class="meta">{{ $appointment->patient->patient_number }}</span></td>
                                <td class="hide-sm">
                                    {{ $appointment->doctor?->shortName() ?? '—' }}
                                    @if ($appointment->user_id === auth()->id()) <span class="meta">(Sie)</span> @endif
                                </td>
                                <td class="hide-md">{{ $appointment->reason ?? '—' }}</td>
                                <td>
                                    @if ($appointment->canStart())
                                        <form method="POST" action="{{ route('appointments.start', $appointment) }}">
                                            @csrf
                                            <button type="submit" class="btn btn--primary btn--sm"><span class="btn__lead"><x-icon name="video" size="16" />Starten</span></button>
                                        </form>
                                    @else
                                        <span class="meta">ab {{ $appointment->starts_at->copy()->subMinutes(\App\Models\Appointment::EARLY_START_MINUTES)->format('H:i') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="meta">
                                @if ($appointmentFilters['doctor'] === 'mine')
                                    Sie haben in diesem Zeitraum keine Videosprechstunden.
                                    <a href="{{ route('calls.index', ['doctor' => 'all', 'range' => $appointmentFilters['range']]) }}">Termine aller Ärzte anzeigen</a>
                                @else
                                    Keine Videosprechstunden geplant. Termine vereinbaren Sie in der Patientenansicht.
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="open-calls">
            <h2 id="open-calls" class="micro">Aktive Anrufe</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Patient</th><th>Richtung</th><th>Status</th><th class="hide-sm">Seit</th><th><span class="sr-only">Aktion</span></th></tr></thead>
                    <tbody>
                        @forelse ($openCalls as $call)
                            <tr>
                                <td><b>{{ $call->patient->fullName() }}</b><br><span class="meta">{{ $call->patient->patient_number }}</span></td>
                                <td>{{ $call->direction === \App\Enums\CallDirection::ToPatient ? 'Klinik → Patient' : 'Patient → Klinik' }}</td>
                                <td>{{ $call->status->label() }}</td>
                                <td class="hide-sm">{{ $call->created_at->format('H:i') }}</td>
                                <td>
                                    @if ($call->direction === \App\Enums\CallDirection::ToClinic && $call->status === \App\Enums\CallStatus::Ringing)
                                        <form method="POST" action="{{ route('calls.answer', $call) }}">
                                            @csrf
                                            <button type="submit" class="btn btn--success btn--sm">Annehmen</button>
                                        </form>
                                    @else
                                        <a class="btn btn--outline btn--sm" href="{{ route('calls.show', $call) }}">Öffnen</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="meta">Derzeit keine aktiven Anrufe.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="recent-calls">
            <h2 id="recent-calls" class="micro">Letzte Gespräche</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Patient</th><th>Richtung</th><th>Status</th><th class="hide-sm">Arzt</th><th>Zeit</th><th class="hide-sm">Dauer</th><th><span class="sr-only">Aktion</span></th></tr></thead>
                    <tbody>
                        @forelse ($recentCalls as $call)
                            <tr>
                                <td><b>{{ $call->patient->fullName() }}</b></td>
                                <td>{{ $call->direction === \App\Enums\CallDirection::ToPatient ? 'ausgehend' : 'eingehend' }}</td>
                                <td>{{ $call->status->label() }}</td>
                                <td class="hide-sm">{{ $call->user?->shortName() ?? 'Zentrale' }}</td>
                                <td>{{ \App\Support\Format::day($call->created_at) }}</td>
                                <td class="hide-sm">{{ $call->answered_at ? $call->durationLabel() : '—' }}</td>
                                <td><a class="btn btn--outline btn--sm" href="{{ route('calls.show', $call) }}">Details</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="meta">Noch keine Gespräche.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.hospital>
