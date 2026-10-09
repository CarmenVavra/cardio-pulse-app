<x-layouts.patient title="Meine Medikation" tab="home">
    <x-slot:header>
        <header class="p-head">
            <div class="p-head__title">
                <a class="p-head__back" href="{{ route('patient.home') }}" aria-label="Zurück zur Startseite"><x-icon name="chevron-left" size="24" stroke="2.2" /></a>
                <h1 class="p-head__h1">Meine Medikation</h1>
            </div>
        </header>
    </x-slot:header>

    <div class="p-content">
        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif

        <p class="meta">Tippen Sie auf ein Medikament, um es zu ändern. Änderungen sieht Ihr Behandlungsteam sofort. Bitte ändern Sie Ihre Medikation nur nach Rücksprache mit Ihrem Arzt.</p>

        <section class="card card__pad" aria-label="Medikamente">
            <p class="meta med-schema-hint">Schema: morgens – mittags – abends</p>
            <x-medication-list :patient="$patient" viewer="patient" :routes="[
                'store' => route('patient.medications.store'),
                'update' => fn ($medication) => route('patient.medications.update', $medication),
                'destroy' => fn ($medication) => route('patient.medications.destroy', $medication),
            ]" />
        </section>

        <p class="meta push-down">Im Notfall immer zuerst <b class="emergency-number">{{ config('cardiopulse.emergency_number') }}</b> wählen.</p>
    </div>
</x-layouts.patient>
