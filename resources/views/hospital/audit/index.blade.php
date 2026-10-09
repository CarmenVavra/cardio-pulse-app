<x-layouts.hospital title="Protokoll" active="audit">
    <div class="list-page">
        <div class="list-page__head">
            <div>
                <h1>Protokoll</h1>
                <p class="meta">Alle sicherheitsrelevanten Aktionen – unveränderlich gespeichert (Nachverfolgbarkeit nach MDR / IEC 62304). {{ $logs->total() }} {{ $logs->total() === 1 ? 'Eintrag' : 'Einträge' }}.</p>
            </div>
            <a class="btn btn--outline" href="{{ route('audit.export', array_filter($filters)) }}">Als CSV herunterladen</a>
        </div>

        <form method="GET" action="{{ route('audit.index') }}" class="filter-form" aria-label="Protokoll filtern">
            @if ($errors->any())
                <div class="alert alert--error filter-form__alert" role="alert">{{ $errors->first() }}</div>
            @endif
            <div class="field">
                <label class="field__label" for="from">Von</label>
                <input class="input" id="from" name="from" type="date" value="{{ $filters['from'] }}" @error('from') aria-invalid="true" @enderror>
            </div>
            <div class="field">
                <label class="field__label" for="to">Bis</label>
                <input class="input" id="to" name="to" type="date" value="{{ $filters['to'] }}" @error('to') aria-invalid="true" @enderror>
            </div>
            <div class="field">
                <label class="field__label" for="group">Bereich</label>
                <select class="select" id="group" name="group">
                    <option value="">Alle Bereiche</option>
                    @foreach ($groups as $key => $label)
                        <option value="{{ $key }}" @selected($filters['group'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="user_id">Arzt</label>
                <select class="select" id="user_id" name="user_id">
                    <option value="">Alle Benutzer</option>
                    @foreach ($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected($filters['user_id'] === $doctor->id)>{{ $doctor->displayName() }}{{ $doctor->trashed() ? ' (gelöscht)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-actions filter-form__actions">
                <button type="submit" class="btn btn--primary">Filtern</button>
                <a class="btn btn--outline" href="{{ route('audit.index') }}">Zurücksetzen</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table audit-table">
                <caption class="sr-only">Protokolleinträge, neueste zuerst</caption>
                <thead>
                    <tr>
                        <th scope="col">Zeit</th>
                        <th scope="col">Aktion</th>
                        <th scope="col">Benutzer</th>
                        <th scope="col" class="hide-sm">Betroffen</th>
                        <th scope="col" class="hide-md">Details</th>
                        <th scope="col" class="hide-md">IP-Adresse</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="tabular nowrap">{{ $log['time'] }}</td>
                            <td><span class="micro">{{ $log['group'] }}</span><br><b>{{ $log['action'] }}</b></td>
                            <td>{{ $log['actor'] }}</td>
                            <td class="hide-sm">{{ $log['subject'] ?? '—' }}</td>
                            <td class="hide-md audit-table__details">{{ $log['details'] !== '' ? $log['details'] : '—' }}</td>
                            <td class="hide-md tabular">{{ $log['ip'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="board-empty">Keine Einträge für diese Auswahl.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <nav class="pager" aria-label="Seiten">
                @if ($logs->previousPageUrl())
                    <a class="btn btn--outline btn--sm" href="{{ $logs->previousPageUrl() }}" rel="prev">‹ Neuere</a>
                @endif
                <span class="meta">Seite {{ $logs->currentPage() }} von {{ $logs->lastPage() }}</span>
                @if ($logs->nextPageUrl())
                    <a class="btn btn--outline btn--sm" href="{{ $logs->nextPageUrl() }}" rel="next">Ältere ›</a>
                @endif
            </nav>
        @endif
    </div>
</x-layouts.hospital>
