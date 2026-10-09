<x-layouts.staff-auth title="Neues Passwort">
    <h1>Neues Passwort festlegen</h1>
    <p class="meta">Mindestens 8 Zeichen mit Buchstaben und Ziffern. Danach werden alle bestehenden Anmeldungen beendet.</p>

    @if ($errors->any())
        <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label class="field__label" for="email">E-Mail</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus @error('email') aria-invalid="true" @enderror>
        </div>
        <div class="field">
            <label class="field__label" for="password">Neues Passwort</label>
            <input class="input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required @error('password') aria-invalid="true" @enderror>
        </div>
        <div class="field">
            <label class="field__label" for="password_confirmation">Neues Passwort wiederholen</label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
        </div>
        <button type="submit" class="btn btn--primary btn--lg btn--block">Passwort speichern <span aria-hidden="true">→</span></button>
    </form>

    <p class="meta"><a href="{{ route('password.request') }}">Neuen Link anfordern</a></p>
</x-layouts.staff-auth>
