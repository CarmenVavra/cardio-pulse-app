@props(['medication' => null, 'formKey'])
@php
    // Alte Eingaben / Fehler nur im Formular anzeigen, das abgeschickt wurde.
    $isCurrent = old('_form') === $formKey;
    [$morning, $noon, $evening] = $medication?->scheduleParts() ?? ['1', '0', '0'];
    $value = fn (string $field, ?string $default) => $isCurrent ? old($field, $default) : $default;
    $slots = ['morning' => ['Morgens', $morning], 'noon' => ['Mittags', $noon], 'evening' => ['Abends', $evening]];
@endphp
<input type="hidden" name="_form" value="{{ $formKey }}">

@if ($isCurrent && $errors->any())
    <div class="alert alert--error" role="alert">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="med-fields">
    <div class="field med-fields__name">
        <label class="field__label" for="{{ $formKey }}-name">Medikament</label>
        <input class="input" id="{{ $formKey }}-name" name="name" type="text" maxlength="100" required
               value="{{ $value('name', $medication?->name) }}" placeholder="z. B. Ramipril" autocomplete="off">
    </div>
    <div class="field med-fields__dose">
        <label class="field__label" for="{{ $formKey }}-dose">Dosis</label>
        <input class="input" id="{{ $formKey }}-dose" name="dose" type="text" maxlength="40" required
               value="{{ $value('dose', $medication?->dose) }}" placeholder="z. B. 10 mg" autocomplete="off">
    </div>
    <fieldset class="fieldset-reset med-fields__schedule">
        <legend class="field__label">Einnahme (Stück)</legend>
        <div class="med-schedule">
            @foreach ($slots as $field => [$label, $default])
                <div class="field">
                    <label class="meta" for="{{ $formKey }}-{{ $field }}">{{ $label }}</label>
                    <select class="select" id="{{ $formKey }}-{{ $field }}" name="{{ $field }}">
                        @foreach (\App\Models\Medication::AMOUNTS as $amount)
                            <option value="{{ $amount }}" @selected($value($field, $default) === $amount)>{{ $amount }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
    </fieldset>
</div>
