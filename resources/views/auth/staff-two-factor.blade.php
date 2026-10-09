<x-layouts.staff-auth title="Bestätigungscode">
    <h1>Bestätigungscode</h1>
    <p class="meta">Öffnen Sie Ihre Authenticator-App und geben Sie den 6-stelligen Code für {{ config('app.name') }} ein. Ohne Handy: einen Ihrer Wiederherstellungscodes.</p>

    @if ($errors->any())
        <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('two-factor.challenge') }}" class="stack">
        @csrf
        <div class="field">
            <label class="field__label" for="code">Code</label>
            <input class="input input--code" id="code" name="code" type="text" autocomplete="one-time-code" maxlength="20" required autofocus
                   aria-describedby="code-hint" @error('code') aria-invalid="true" @enderror>
            <p class="meta" id="code-hint">6 Ziffern aus der App oder Wiederherstellungscode, z. B. k7m2q-x9p4t</p>
        </div>
        <button type="submit" class="btn btn--primary btn--lg btn--block">Bestätigen und Überwachung starten <span aria-hidden="true">→</span></button>
    </form>

    <p class="meta"><a href="{{ route('login') }}">‹ Abbrechen und neu anmelden</a></p>
</x-layouts.staff-auth>
