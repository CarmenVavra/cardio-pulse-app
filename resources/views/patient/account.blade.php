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
