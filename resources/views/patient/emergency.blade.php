<x-layouts.patient title="Gefährlich hoher Wert" theme="red">
    <section class="emergency" role="alertdialog" aria-labelledby="emergency-title" aria-describedby="emergency-advice">
        <h1 class="emergency__kicker" id="emergency-title">GEFÄHRLICH HOHER WERT</h1>
        <div class="emergency__bp" aria-label="{{ $measurement->systolic }} zu {{ $measurement->diastolic }} mmHg">{{ $measurement->reading() }}</div>
        <div>mmHg · Puls {{ $measurement->pulse ?? '—' }}@if ($measurement->symptoms) · {{ $measurement->symptomLabels() }}@endif</div>
        <div class="emergency__category">{{ $measurement->category()->label() }} · {{ $measurement->category()->description() }}</div>
        <p class="emergency__advice" id="emergency-advice">{{ $measurement->category()->advice() }}</p>

        <a class="btn btn--white btn--xl btn--block btn--start" href="tel:{{ config('cardiopulse.emergency_number') }}"><x-icon name="phone" size="28" stroke="2.4" />Notruf {{ config('cardiopulse.emergency_number') }}</a>

        <form method="POST" action="{{ route('patient.calls.store') }}">
            @csrf
            <input type="hidden" name="target" value="doctor">
            <button type="submit" class="btn btn--outline-light btn--lg btn--block btn--start" style="font-size:17px">Meinen Arzt anrufen</button>
        </form>

        <div class="emergency__notified" role="status"><x-icon name="check" size="18" stroke="2.6" />Das Krankenhaus wurde sofort benachrichtigt</div>

        @if ($measurement->symptom_free_confirmed_at)
            <p><b>Sie haben bestätigt, dass Sie keine akuten Beschwerden haben.</b></p>
            <a class="btn btn--white btn--lg btn--block push-down" href="{{ route('patient.home') }}">Zur Startseite</a>
        @else
            <form method="POST" action="{{ route('patient.measurements.confirm', $measurement) }}" class="stack stack--sm push-down" data-symptom-free-form>
                @csrf
                <b style="font-size:15px">Keine akuten Beschwerden?</b>
                <label class="checkbox">
                    <input type="checkbox" name="symptom_free" value="1" data-symptom-free>
                    Kein Brustschmerz, keine Atemnot
                </label>
                @error('symptom_free')<div role="alert" style="font-weight:600">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn--dim btn--lg btn--block" disabled data-symptom-free-submit>Ich habe keine Beschwerden</button>
            </form>
        @endif
    </section>
</x-layouts.patient>
