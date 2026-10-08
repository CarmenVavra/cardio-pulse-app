@props(['patient', 'routes', 'viewer' => 'staff'])
{{--
    Medikationsliste mit Bearbeiten/Löschen/Hinzufügen.
    $routes: ['store' => url, 'update' => fn(Medication) => url, 'destroy' => fn(Medication) => url]
--}}
<div class="med-list">
    @forelse ($patient->medications as $medication)
        @php $formKey = 'med-'.$medication->id; @endphp
        <details class="med-item" @if (old('_form') === $formKey) open @endif>
            <summary class="med-item__summary">
                <span>
                    <b>{{ $medication->name }}</b> {{ $medication->dose }}
                    @if ($viewer === 'staff' && $medication->changedByPatient())
                        <span class="med-flag">vom Patienten geändert · {{ $medication->updated_at->format('d.m.') }}</span>
                    @elseif ($viewer === 'patient' && $medication->updatedBy?->isStaff())
                        <span class="med-flag med-flag--staff">verordnet von {{ $medication->updatedBy->shortName() }}</span>
                    @endif
                </span>
                <span class="tabular" aria-label="Einnahme morgens–mittags–abends: {{ $medication->schedule }}">{{ $medication->schedule }}</span>
                <span class="med-item__toggle">Bearbeiten<span class="sr-only">: {{ $medication->name }}</span></span>
            </summary>

            <form method="POST" action="{{ $routes['update']($medication) }}" class="med-form">
                @csrf
                @method('PUT')
                <x-medication-fields :medication="$medication" :form-key="$formKey" />
                <div class="med-form__actions">
                    <button type="submit" class="btn btn--primary">Speichern <span aria-hidden="true">→</span></button>
                    <button type="submit" class="btn btn--outline btn--danger-outline" form="{{ $formKey }}-delete">Löschen</button>
                </div>
            </form>
            <form id="{{ $formKey }}-delete" method="POST" action="{{ $routes['destroy']($medication) }}"
                  data-confirm="{{ $medication->name }} {{ $medication->dose }} wirklich aus der Medikation löschen?">
                @csrf
                @method('DELETE')
            </form>
        </details>
    @empty
        <p class="meta med-empty">Keine Medikation hinterlegt.</p>
    @endforelse

    <details class="med-add" @if (old('_form') === 'med-new') open @endif>
        <summary class="btn btn--outline btn--sm"><x-icon name="plus" size="16" stroke="2.4" />Medikament hinzufügen</summary>
        <form method="POST" action="{{ $routes['store'] }}" class="med-form">
            @csrf
            <x-medication-fields form-key="med-new" />
            <div class="med-form__actions">
                <button type="submit" class="btn btn--primary">Hinzufügen <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </details>
</div>
