@props(['name', 'label', 'bag', 'autocomplete' => 'current-password', 'hint' => null, 'inputmode' => null, 'maxlength' => null])
{{-- Passwort-/PIN-Feld mit Fehler aus einem benannten Error-Bag. --}}
@php
    $error = $errors->getBag($bag)->first($name);
    $describedBy = trim(($hint ? $name.'-hint ' : '').($error ? $name.'-error' : ''));
@endphp
<div class="field">
    <label class="field__label" for="{{ $name }}">{{ $label }}</label>
    <input class="input" id="{{ $name }}" name="{{ $name }}" type="password" required autocomplete="{{ $autocomplete }}"
           @if ($inputmode) inputmode="{{ $inputmode }}" @endif
           @if ($maxlength) maxlength="{{ $maxlength }}" @endif
           @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
           @if ($error) aria-invalid="true" @endif>
    @if ($hint)<p class="meta" id="{{ $name }}-hint">{{ $hint }}</p>@endif
    @if ($error)<div class="field-error" id="{{ $name }}-error">{{ $error }}</div>@endif
</div>
