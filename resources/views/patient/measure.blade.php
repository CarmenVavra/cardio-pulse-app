<x-layouts.patient title="Blutdruck eintragen" tab="measure">
    <x-slot:header>
        <header class="p-head">
            <div class="p-head__title">
                <a class="p-head__back" href="{{ route('patient.home') }}" aria-label="Zurück zur Startseite"><x-icon name="chevron-left" size="24" stroke="2.2" /></a>
                <h1 style="font-size:20px">Neue Messung · <span data-now>{{ now()->format('H:i') }}</span></h1>
            </div>
        </header>
    </x-slot:header>

    <form method="POST" action="{{ route('patient.measurements.store') }}" class="p-content" data-measure-form novalidate>
        @csrf

        @if ($errors->any())
            <div class="alert alert--error" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{-- Bluetooth-Import und Foto-Scan sind noch offen (README) – bis dahin nur Eintippen. --}}
        <input type="hidden" name="method" value="manual">

        <div class="value-fields">
            <label class="value-field is-active" data-value-field>
                <span class="value-field__label">OBERER</span>
                <input type="text" name="systolic" inputmode="numeric" pattern="[0-9]*" maxlength="3" placeholder="—" value="{{ old('systolic') }}" aria-label="Oberer Wert (systolisch) in mmHg" required data-value-input autocomplete="off">
            </label>
            <label class="value-field" data-value-field>
                <span class="value-field__label">UNTERER</span>
                <input type="text" name="diastolic" inputmode="numeric" pattern="[0-9]*" maxlength="3" placeholder="—" value="{{ old('diastolic') }}" aria-label="Unterer Wert (diastolisch) in mmHg" required data-value-input autocomplete="off">
            </label>
            <label class="value-field" data-value-field>
                <span class="value-field__label">PULS</span>
                <input type="text" name="pulse" inputmode="numeric" pattern="[0-9]*" maxlength="3" placeholder="—" value="{{ old('pulse') }}" aria-label="Puls pro Minute (optional)" data-value-input autocomplete="off">
            </label>
        </div>

        <div class="numpad" role="group" aria-label="Ziffernblock">
            @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $key)
                <button type="button" data-key="{{ $key }}">{{ $key }}</button>
            @endforeach
            <button type="button" data-key="back" aria-label="Löschen">⌫</button>
            <button type="button" data-key="0">0</button>
            <button type="button" data-key="next" aria-label="Nächstes Feld">→</button>
        </div>

        <fieldset class="fieldset-reset stack stack--sm" style="gap:8px">
            <legend class="section-title" style="margin-bottom:8px">Beschwerden?</legend>
            <div class="chips">
                @foreach (['kopfschmerz', 'schwindel', 'brustdruck', 'atemnot'] as $key)
                    <label class="chip"><input type="checkbox" name="symptoms[]" value="{{ $key }}" @checked(in_array($key, old('symptoms', []), true)) data-symptom>{{ $symptoms[$key] }}</label>
                @endforeach
                <label class="chip"><input type="checkbox" name="symptoms[]" value="keine" @checked(old('symptoms') === null || in_array('keine', old('symptoms', []), true)) data-symptom-none>Keine</label>
            </div>
        </fieldset>

        <fieldset class="fieldset-reset">
            <legend class="section-title" style="margin-bottom:8px">Zur Messung</legend>
            <div class="chips">
                <label class="chip"><input type="checkbox" name="rested" value="1" @checked(old('rested'))>5 Min Ruhe eingehalten</label>
                <label class="chip"><input type="checkbox" name="medication_taken" value="1" @checked(old('medication_taken'))>Medikation genommen</label>
            </div>
        </fieldset>

        <button type="submit" class="btn btn--primary btn--lg btn--block" style="font-size:17px">Speichern <span aria-hidden="true">→</span></button>
    </form>
</x-layouts.patient>
