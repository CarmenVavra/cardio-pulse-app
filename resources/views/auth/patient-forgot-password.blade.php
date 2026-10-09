<x-layouts.patient title="Passwort vergessen" :poll="false">
    <x-slot:header>
        <x-patient-auth-header heading="Passwort vergessen" />
    </x-slot:header>

    <div class="p-login">
        <p class="advice">Geben Sie Ihre E-Mail-Adresse ein. Sie erhalten einen Link, mit dem Sie ein neues Passwort festlegen.</p>

        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('patient.password.email') }}" class="stack stack--sm">
            @csrf
            <div class="field">
                <label class="field__label" for="email">E-Mail</label>
                <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus @error('email') aria-invalid="true" @enderror>
            </div>
            <button type="submit" class="btn btn--primary btn--lg btn--block">Link senden <span aria-hidden="true">→</span></button>
        </form>

        <p class="meta push-down">Keine E-Mail erhalten? Prüfen Sie den Spam-Ordner oder wenden Sie sich an Ihr Behandlungsteam.</p>
        <p class="meta"><a href="{{ route('patient.login') }}">‹ Zurück zur Anmeldung</a></p>
    </div>
</x-layouts.patient>
