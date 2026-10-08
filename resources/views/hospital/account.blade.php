<x-layouts.hospital title="Mein Konto" active="account">
    <div class="list-page form-page">
        <div>
            <h1>Mein Konto</h1>
            <p class="meta">{{ $user->displayName() }} · Benutzerkennung <b>{{ $user->username }}</b> · {{ $user->email }}</p>
        </div>

        <form method="POST" action="{{ route('account.password') }}" class="stack" novalidate>
            @csrf
            @method('PUT')
            <fieldset class="form-section">
                <legend>Passwort ändern</legend>
                <p class="meta">Mindestens 8 Zeichen mit Buchstaben und Ziffern. Danach werden Sie an allen anderen Rechnern abgemeldet.</p>
                @if ($errors->password->any())
                    <div class="alert alert--error" role="alert">Das Passwort wurde nicht geändert. Bitte prüfen Sie die markierten Felder.</div>
                @endif
                <div class="form-grid">
                    <x-password-field name="current_password" label="Aktuelles Passwort" bag="password" />
                    <x-password-field name="password" label="Neues Passwort" bag="password" autocomplete="new-password" />
                    <x-password-field name="password_confirmation" label="Neues Passwort wiederholen" bag="password" autocomplete="new-password" />
                </div>
            </fieldset>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary btn--lg">Passwort ändern <span aria-hidden="true">→</span></button>
            </div>
        </form>

        <form method="POST" action="{{ route('account.pin') }}" class="stack" novalidate>
            @csrf
            @method('PUT')
            <fieldset class="form-section">
                <legend>PIN für den Privacy-Lock ändern</legend>
                <p class="meta">6 Ziffern zum Entsperren des Überwachungsscreens. Zur Bestätigung bitte Ihr Passwort eingeben.</p>
                @if ($errors->pin->any())
                    <div class="alert alert--error" role="alert">Die PIN wurde nicht geändert. Bitte prüfen Sie die markierten Felder.</div>
                @endif
                <div class="form-grid">
                    <x-password-field name="pin_current_password" label="Passwort" bag="pin" />
                    <x-password-field name="pin" label="Neue PIN (6 Ziffern)" bag="pin" autocomplete="off" inputmode="numeric" maxlength="6" />
                    <x-password-field name="pin_confirmation" label="Neue PIN wiederholen" bag="pin" autocomplete="off" inputmode="numeric" maxlength="6" />
                </div>
            </fieldset>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary btn--lg">PIN ändern <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </div>
</x-layouts.hospital>
