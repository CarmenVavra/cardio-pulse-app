<x-layouts.staff-auth title="Anmelden">
    <h1>Anmelden</h1>

    @if (session('patient_hint'))
        <div class="alert alert--error stack stack--sm" role="alert">
            <span>{{ $errors->first() }}</span>
            <span><b>Patientin oder Patient?</b> Sie melden sich in der Patienten-App mit Ihrer E-Mail-Adresse an.</span>
            <a class="btn btn--primary btn--block" href="{{ route('patient.login') }}">Zur Patienten-App <span aria-hidden="true">→</span></a>
        </div>
    @elseif ($errors->any())
        <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" data-audio-unlock class="stack">
        @csrf
        <div class="field">
            <label class="field__label" for="username">Benutzerkennung</label>
            <input class="input" id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" required autofocus @error('username') aria-invalid="true" @enderror>
        </div>
        <div class="field">
            <label class="field__label" for="password">Passwort</label>
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <div class="field">
            <label class="field__label" for="department">Abteilung</label>
            <select class="select" id="department" name="department" required>
                @foreach ($departments as $key => $label)
                    <option value="{{ $key }}" @selected(old('department', 'telemonitoring') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <label class="checkbox">
            <input type="checkbox" name="sound" value="1" checked data-sound-checkbox>
            Signalton für diesen Bildschirm aktivieren
        </label>
        <button type="submit" class="btn btn--primary btn--lg btn--block">Anmelden und Überwachung starten <span aria-hidden="true">→</span></button>
    </form>

    <p class="meta"><a href="{{ route('password.request') }}">Passwort vergessen?</a></p>
    <p class="meta">Patientin oder Patient? <a href="{{ route('patient.login') }}">Zur Patienten-App</a></p>
</x-layouts.staff-auth>
