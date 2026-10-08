<x-layouts.hospital title="Ärzte" active="doctors">
    <div class="list-page">
        <div class="list-page__head">
            <div>
                <h1>Ärzte</h1>
                <p class="meta">{{ $doctors->count() }} {{ $doctors->count() === 1 ? 'Arzt' : 'Ärzte' }} mit Zugang zum Überwachungsscreen</p>
            </div>
            <a class="btn btn--primary" href="{{ route('doctors.create') }}"><x-icon name="plus" size="16" stroke="2.6" />Arzt anlegen</a>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <caption class="sr-only">Ärzte mit Benutzerkennung, Kontakt und Anzahl zugewiesener Patienten</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Benutzerkennung</th>
                        <th scope="col" class="hide-sm">E-Mail</th>
                        <th scope="col" class="hide-md">Telefon</th>
                        <th scope="col" class="hide-md">Erreichbar bis</th>
                        <th scope="col">Patienten</th>
                        <th scope="col"><span class="sr-only">Aktion</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($doctors as $doctor)
                        <tr>
                            <td>
                                <span class="doctor-name">
                                    <span class="avatar" aria-hidden="true">{{ $doctor->initials() }}</span>
                                    <b>{{ $doctor->displayName() }}</b>
                                    @if ($doctor->is(auth()->user()))
                                        <span class="meta">(Sie)</span>
                                    @endif
                                </span>
                            </td>
                            <td>{{ $doctor->username }}</td>
                            <td class="hide-sm">{{ $doctor->email }}</td>
                            <td class="hide-md">{{ $doctor->phone ?? '—' }}</td>
                            <td class="hide-md">{{ $doctor->available_until ? $doctor->available_until.' Uhr' : '—' }}</td>
                            <td class="tabular">{{ $doctor->patients_count }}</td>
                            <td><a class="btn btn--outline btn--sm" href="{{ route('doctors.edit', $doctor) }}">Bearbeiten<span class="sr-only">: {{ $doctor->displayName() }}</span></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.hospital>
