<x-layouts.hospital title="Anrufe" active="calls">
    <div class="list-page">
        <h1>Anrufe</h1>

        <section aria-labelledby="open-calls">
            <h2 id="open-calls" class="micro">Aktive Anrufe</h2>
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
        </section>

        <section aria-labelledby="recent-calls">
            <h2 id="recent-calls" class="micro">Letzte Gespräche</h2>
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
        </section>
    </div>
</x-layouts.hospital>
