<x-layouts.staff-auth title="Passwort vergessen">
    <h1>Passwort vergessen</h1>
    <p class="meta">Geben Sie die E-Mail-Adresse aus Ihrem Arzt-Profil ein. Sie erhalten einen Link, mit dem Sie ein neues Passwort festlegen. Ihre PIN für den Privacy-Lock bleibt unverändert.</p>

    @if ($errors->any())
        <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="stack">
        @csrf
        <div class="field">
            <label class="field__label" for="email">E-Mail</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus @error('email') aria-invalid="true" @enderror>
        </div>
        <button type="submit" class="btn btn--primary btn--lg btn--block">Link senden <span aria-hidden="true">→</span></button>
    </form>

    <p class="meta"><a href="{{ route('login') }}">‹ Zurück zur Anmeldung</a></p>
</x-layouts.staff-auth>
