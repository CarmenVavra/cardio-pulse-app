@php
    $editing = $patient->exists;
    $title = $editing ? $patient->fullName().' bearbeiten' : 'Patient anlegen';
    $fields = [
        ['first_name', 'Vorname', 'text', 'given-name'],
        ['last_name', 'Nachname', 'text', 'family-name'],
        ['birth_date', 'Geburtsdatum', 'date', 'bday'],
        ['phone', 'Telefon', 'tel', 'tel'],
        ['street', 'Straße und Hausnummer', 'text', 'street-address'],
        ['postal_code', 'PLZ', 'text', 'postal-code'],
        ['city', 'Ort', 'text', 'address-level2'],
    ];
@endphp
<x-layouts.hospital :title="$title" active="patients">
    <div class="list-page form-page">
        <div>
            <a class="meta" href="{{ $editing ? route('patients.show', $patient) : route('patients.index') }}">‹ Zurück</a>
            <h1>{{ $title }}</h1>
            <p class="meta">
                @if ($editing)
                    {{ $patient->patient_number }} · zu Hause · angelegt {{ $patient->created_at?->format('d.m.Y') }}
                @else
                    Patientennummer wird automatisch vergeben: <b>{{ $nextNumber }}</b>
                @endif
            </p>
        </div>

        @if ($errors->any())
            <div class="alert alert--error" role="alert">
                Bitte prüfen Sie die markierten Felder.
            </div>
        @endif

        <form method="POST" action="{{ $editing ? route('patients.update', $patient) : route('patients.store') }}" class="stack" novalidate>
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <fieldset class="form-section">
                <legend>Stammdaten</legend>
                <div class="form-grid">
                    @foreach ($fields as [$name, $label, $type, $autocomplete])
                        <div class="field">
                            <label class="field__label" for="{{ $name }}">{{ $label }}</label>
                            <input class="input" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" autocomplete="off"
                                   value="{{ old($name, $name === 'birth_date' ? $patient->birth_date?->format('Y-m-d') : $patient->{$name}) }}"
                                   required @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
                            @error($name)<div class="field-error" id="{{ $name }}-error">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Behandlung</legend>
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="diagnosis">Diagnose</label>
                        <input class="input" id="diagnosis" name="diagnosis" type="text" value="{{ old('diagnosis', $patient->diagnosis) }}" placeholder="z. B. Hypertonie Grad 2" @error('diagnosis') aria-invalid="true" @enderror>
                        @error('diagnosis')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label class="field__label" for="gp_name">Hausarzt</label>
                        <input class="input" id="gp_name" name="gp_name" type="text" value="{{ old('gp_name', $patient->gp_name) }}" placeholder="z. B. Dr. Lenz" @error('gp_name') aria-invalid="true" @enderror>
                        @error('gp_name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label class="field__label" for="doctor_id">Behandelnder Arzt</label>
                        <select class="select" id="doctor_id" name="doctor_id" required @error('doctor_id') aria-invalid="true" @enderror>
                            @foreach ($doctors as $doctor)
                                <option value="{{ $doctor->id }}" @selected((int) old('doctor_id', $patient->doctor_id) === $doctor->id)>{{ $doctor->displayName() }}</option>
                            @endforeach
                        </select>
                        @error('doctor_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>App-Zugang</legend>
                <p class="meta">Mit diesen Daten meldet sich der Patient in der CardioPulse-App an. Passwort: mindestens 8 Zeichen mit Buchstaben und Ziffern.{{ $editing ? ' Passwort leer lassen, um es nicht zu ändern.' : '' }}</p>
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="email">E-Mail</label>
                        <input class="input" id="email" name="email" type="email" autocomplete="off" required
                               value="{{ old('email', $patient->user?->email) }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')<div class="field-error" id="email-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label class="field__label" for="password">{{ $editing ? 'Neues Passwort' : 'Passwort' }}</label>
                        <input class="input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" @unless ($editing) required @endunless
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')<div class="field-error" id="password-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label class="field__label" for="password_confirmation">Passwort wiederholen</label>
                        <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8">
                    </div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary btn--lg">{{ $editing ? 'Änderungen speichern' : 'Patient anlegen' }} <span aria-hidden="true">→</span></button>
                <a class="btn btn--outline btn--lg" href="{{ $editing ? route('patients.show', $patient) : route('patients.index') }}">Abbrechen</a>
            </div>
        </form>

        @if ($editing)
            <form method="POST" action="{{ route('patients.destroy', $patient) }}" class="danger-zone"
                  data-confirm="{{ $patient->fullName() }} ({{ $patient->patient_number }}) wirklich löschen? Der Patient verschwindet aus der Überwachung und kann sich nicht mehr in der App anmelden.">
                @csrf
                @method('DELETE')
                <h2>Patient löschen</h2>
                <p class="meta">
                    Der Patient wird aus Überwachung, Listen und Suche entfernt, laufende Anrufe werden beendet und der App-Zugang wird gesperrt.
                    Messwerte, Alarme und Protokolle bleiben wegen der gesetzlichen Aufbewahrungspflicht (§ 630f BGB) archiviert.
                </p>
                <div class="field">
                    <label class="field__label" for="reason">Grund (optional, für das Protokoll)</label>
                    <input class="input" id="reason" name="reason" type="text" maxlength="500" value="{{ old('reason') }}" placeholder="z. B. Behandlung beendet, Umzug, Patient verstorben">
                    @error('reason')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <label class="checkbox">
                    <input type="checkbox" name="confirm" value="1" required @error('confirm') aria-invalid="true" aria-describedby="confirm-error" @enderror>
                    Ich bestätige, dass {{ $patient->fullName() }} gelöscht werden soll.
                </label>
                @error('confirm')<div class="field-error" id="confirm-error" role="alert">{{ $message }}</div>@enderror
                <div>
                    <button type="submit" class="btn btn--danger"><span class="btn__lead"><x-icon name="x" size="18" stroke="2.4" />Patient löschen</span></button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.hospital>
