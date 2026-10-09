<x-layouts.patient title="Anmelden" :poll="false">
    <x-slot:header>
        <x-patient-auth-header heading="Willkommen" />
    </x-slot:header>

    <div class="p-login">
        <p class="advice">Melden Sie sich an, um Ihre Blutdruckwerte zu erfassen und an Ihr Behandlungsteam zu übertragen.</p>

        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('patient.login') }}" class="stack stack--sm">
            @csrf
            <div class="field">
                <label class="field__label" for="email">E-Mail</label>
                <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus @error('email') aria-invalid="true" @enderror>
            </div>
            <div class="field">
                <label class="field__label" for="password">Passwort</label>
                <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn--primary btn--lg btn--block">Anmelden <span aria-hidden="true">→</span></button>
        </form>

        <p class="meta"><a href="{{ route('patient.password.request') }}">Passwort vergessen?</a></p>
        <p class="meta push-down">Im Notfall immer zuerst <b class="emergency-number">{{ config('cardiopulse.emergency_number') }}</b> wählen.</p>
        <p class="meta"><a href="{{ route('login') }}">Zum Krankenhaus-Login</a></p>
    </div>
</x-layouts.patient>
