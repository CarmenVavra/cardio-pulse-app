@php
    $editing = $doctor->exists;
    $title = $editing ? $doctor->displayName().' bearbeiten' : 'Arzt anlegen';
    $isSelf = $editing && $doctor->is(auth()->user());
    $profileFields = [
        ['title', 'Titel', 'text', false, 'z. B. Dr. oder Prof. Dr.'],
        ['name', 'Vor- und Nachname', 'text', true, 'z. B. Miriam Weber'],
        ['email', 'E-Mail', 'email', true, 'name@klinikum-nord.de'],
        ['phone', 'Telefon', 'tel', false, 'z. B. 040 1234 5671'],
        ['available_until', 'Erreichbar bis', 'time', false, ''],
    ];
@endphp
<x-layouts.hospital :title="$title" active="doctors">
    <div class="list-page form-page">
        <div>
            <a class="meta" href="{{ route('doctors.index') }}">‹ Zurück zur Ärzteliste</a>
            <h1>{{ $title }}</h1>
            @if ($editing)
                <p class="meta">{{ $doctor->patients_count }} {{ $doctor->patients_count === 1 ? 'Patient' : 'Patienten' }} zugewiesen · angelegt {{ $doctor->created_at?->format('d.m.Y') }}</p>
            @endif
        </div>

        @if ($errors->hasAny(['title', 'name', 'username', 'email', 'phone', 'available_until', 'password', 'pin']))
            <div class="alert alert--error" role="alert">Bitte prüfen Sie die markierten Felder.</div>
        @endif

        <form method="POST" action="{{ $editing ? route('doctors.update', $doctor) : route('doctors.store') }}" class="stack" novalidate>
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <fieldset class="form-section">
                <legend>Profil</legend>
                <div class="form-grid">
                    @foreach ($profileFields as [$name, $label, $type, $required, $placeholder])
                        <div class="field">
                            <label class="field__label" for="{{ $name }}">{{ $label }}</label>
                            <input class="input" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" autocomplete="off"
                                   value="{{ old($name, $doctor->{$name}) }}" placeholder="{{ $placeholder }}" @if ($required) required @endif
                                   @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
                            @error($name)<div class="field-error" id="{{ $name }}-error">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Zugang zum Überwachungsscreen</legend>
                <p class="meta">
                    Benutzerkennung und Passwort für die Anmeldung, 6-stellige PIN zum Entsperren des Privacy-Locks. Passwort: mindestens 8 Zeichen mit Buchstaben und Ziffern.
                    {{ $editing ? 'Passwort und PIN leer lassen, um sie nicht zu ändern.' : '' }}
                </p>
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="username">Benutzerkennung</label>
                        <input class="input" id="username" name="username" type="text" autocomplete="off" required placeholder="z. B. m.weber"
                               value="{{ old('username', $doctor->username) }}" @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                        @error('username')<div class="field-error" id="username-error">{{ $message }}</div>@enderror
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
                    <div class="field">
                        <label class="field__label" for="pin">{{ $editing ? 'Neue PIN (6 Ziffern)' : 'PIN (6 Ziffern)' }}</label>
                        <input class="input" id="pin" name="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" pattern="\d{6}" @unless ($editing) required @endunless
                               @error('pin') aria-invalid="true" aria-describedby="pin-error" @enderror>
                        @error('pin')<div class="field-error" id="pin-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Berechtigung</legend>
                <input type="hidden" name="is_admin" value="0">
                <label class="checkbox">
                    <input type="checkbox" name="is_admin" value="1" aria-describedby="is-admin-hint"
                           @checked(old('is_admin', $doctor->is_admin)) @disabled($isSelf)>
                    Admin – darf Ärzte anlegen, bearbeiten und löschen
                </label>
                <p class="meta" id="is-admin-hint">
                    {{ $isSelf ? 'Ihre eigenen Admin-Rechte kann nur ein anderer Admin entziehen.' : 'Ärzte ohne Admin-Rechte sehen den Menüpunkt „Ärzte“ nicht.' }}
                </p>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary btn--lg">{{ $editing ? 'Änderungen speichern' : 'Arzt anlegen' }} <span aria-hidden="true">→</span></button>
                <a class="btn btn--outline btn--lg" href="{{ route('doctors.index') }}">Abbrechen</a>
            </div>
        </form>

        @if ($editing)
            <form method="POST" action="{{ route('doctors.destroy', $doctor) }}" class="danger-zone"
                  data-confirm="{{ $doctor->displayName() }} wirklich löschen? Die Anmeldung wird sofort gesperrt.">
                @csrf
                @method('DELETE')
                <h2>Arzt löschen</h2>

                @error('doctor')<div class="alert alert--error" role="alert">{{ $message }}</div>@enderror

                @if ($isSelf)
                    <p class="meta">Sie können Ihr eigenes Konto nicht löschen. Bitten Sie eine Kollegin oder einen Kollegen darum.</p>
                @elseif ($replacements->isEmpty())
                    <p class="meta">Der letzte Arzt kann nicht gelöscht werden. Legen Sie zuerst einen weiteren Arzt an.</p>
                @else
                    <p class="meta">
                        Die Anmeldung wird gesperrt und laufende Anrufe werden beendet. Der Name bleibt in Alarm-Quittierungen,
                        Anrufen und im Prüfprotokoll erhalten (Nachverfolgbarkeit nach MDR / IEC 62304).
                    </p>

                    @if ($doctor->patients_count > 0)
                        <div class="field">
                            <label class="field__label" for="replacement_id">{{ $doctor->patients_count }} {{ $doctor->patients_count === 1 ? 'Patient übernimmt' : 'Patienten übernimmt' }}</label>
                            <select class="select" id="replacement_id" name="replacement_id" required @error('replacement_id') aria-invalid="true" aria-describedby="replacement-error" @enderror>
                                <option value="">Bitte Arzt wählen …</option>
                                @foreach ($replacements as $replacement)
                                    <option value="{{ $replacement->id }}" @selected((int) old('replacement_id') === $replacement->id)>{{ $replacement->displayName() }}</option>
                                @endforeach
                            </select>
                            @error('replacement_id')<div class="field-error" id="replacement-error">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <label class="checkbox">
                        <input type="checkbox" name="confirm" value="1" required @error('confirm') aria-invalid="true" aria-describedby="doctor-confirm-error" @enderror>
                        Ich bestätige, dass {{ $doctor->displayName() }} gelöscht werden soll.
                    </label>
                    @error('confirm')<div class="field-error" id="doctor-confirm-error" role="alert">{{ $message }}</div>@enderror
                    <div>
                        <button type="submit" class="btn btn--danger"><span class="btn__lead"><x-icon name="x" size="18" stroke="2.4" />Arzt löschen</span></button>
                    </div>
                @endif
            </form>
        @endif
    </div>
</x-layouts.hospital>
