<x-layouts.patient title="Mein Konto" tab="home">
    <x-slot:header>
        <header class="p-head">
            <div class="p-head__title">
                <a class="p-head__back" href="{{ route('patient.home') }}" aria-label="Zurück zur Startseite"><x-icon name="chevron-left" size="24" stroke="2.2" /></a>
                <h1 class="p-head__h1">Mein Konto</h1>
            </div>
        </header>
    </x-slot:header>

    <div class="p-content">
        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif

        <section class="card card__pad" aria-label="Kontodaten">
            <b style="font-size:17px">{{ $patient->fullName() }}</b>
            <div class="meta">{{ $patient->patient_number }} · Anmeldung mit {{ $patient->user->email }}</div>
        </section>

        <x-install-app />
        <x-push-settings />

        <form method="POST" action="{{ route('patient.account.location') }}" class="card card__pad stack" id="standort" aria-labelledby="location-title">
            @csrf
            @method('PUT')
            <h2 id="location-title" style="font-size:20px">Standort im Notfall</h2>
            <p class="meta">
                Wenn Sie die <b>Notfalltaste</b> drücken, kann die App Ihren aktuellen Standort an das Krankenhaus schicken – wichtig, wenn Sie unterwegs sind.
                Der Standort wird <b>nur in diesem Moment</b> ermittelt (keine laufende Ortung), verschlüsselt gespeichert und gelöscht, sobald das Krankenhaus den Notruf bearbeitet hat.
                Sie können die Freigabe jederzeit widerrufen.
            </p>
            <p @class(['location-state', 'location-state--on' => $patient->location_consent_at])>
                @if ($patient->location_consent_at)
                    Freigegeben seit {{ $patient->location_consent_at->format('d.m.Y') }}
                @else
                    Nicht freigegeben
                @endif
            </p>
            <input type="hidden" name="consent" value="{{ $patient->location_consent_at ? '0' : '1' }}">
            <button type="submit" @class(['btn', 'btn--lg', 'btn--block', 'btn--primary' => ! $patient->location_consent_at, 'btn--outline' => $patient->location_consent_at])>
                {{ $patient->location_consent_at ? 'Freigabe widerrufen' : 'Standort im Notfall mitschicken' }}
            </button>
        </form>

        <form method="POST" action="{{ route('patient.account.password') }}" class="card card__pad stack" novalidate>
            @csrf
            @method('PUT')
            <h2 style="font-size:20px">Passwort ändern</h2>
            <p class="meta">Mindestens 8 Zeichen mit Buchstaben und Ziffern. Danach werden Sie auf allen anderen Geräten abgemeldet.</p>
            @if ($errors->password->any())
                <div class="alert alert--error" role="alert">Das Passwort wurde nicht geändert. Bitte prüfen Sie die markierten Felder.</div>
            @endif
            <x-password-field name="current_password" label="Aktuelles Passwort" bag="password" />
            <x-password-field name="password" label="Neues Passwort" bag="password" autocomplete="new-password" />
            <x-password-field name="password_confirmation" label="Neues Passwort wiederholen" bag="password" autocomplete="new-password" />
            <button type="submit" class="btn btn--primary btn--lg btn--block">Passwort ändern <span aria-hidden="true">→</span></button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn--outline btn--lg btn--block">Abmelden</button>
        </form>
    </div>
</x-layouts.patient>
