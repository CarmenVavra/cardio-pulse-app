<x-layouts.patient title="Anmelden" :poll="false">
    <x-slot:header>
        <header class="p-head">
            <div class="p-head__bar"><x-logo /></div>
            <h1 class="p-greeting">Willkommen</h1>
            <p class="p-connection">{{ config('cardiopulse.clinic.name') }} · {{ config('cardiopulse.clinic.ward') }}</p>
        </header>
    </x-slot:header>

    <div class="p-login">
        <p class="advice">Melden Sie sich an, um Ihre Blutdruckwerte zu erfassen und an Ihr Behandlungsteam zu übertragen.</p>

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

        <p class="meta push-down">Im Notfall immer zuerst <b class="emergency-number">112</b> wählen.</p>
        <p class="meta"><a href="{{ route('login') }}">Zum Krankenhaus-Login</a></p>
    </div>
</x-layouts.patient>
