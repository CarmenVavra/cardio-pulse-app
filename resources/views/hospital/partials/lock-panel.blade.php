<section class="lock-panel" aria-labelledby="lock-title">
    <x-icon name="lock" size="48" class="lock-panel__icon" />
    <h1 id="lock-title">Bildschirm gesperrt</h1>
    <p>Nach {{ intdiv(config('cardiopulse.privacy_lock_seconds'), 60) }} Minuten ohne Eingabe werden Namen ausgeblendet. Neue Uploads, Ampelfarben und der Signalton laufen weiter.</p>

    <form method="POST" action="{{ route('unlock') }}" data-pin-form class="stack stack--sm">
        @csrf
        <fieldset class="fieldset-reset">
            <legend class="sr-only">6-stellige PIN</legend>
            <div class="pin-boxes">
                @for ($i = 1; $i <= 6; $i++)
                    <input type="password" inputmode="numeric" autocomplete="off" aria-label="PIN-Ziffer {{ $i }}" data-pin-digit @if ($i === 1) autofocus @endif>
                @endfor
            </div>
        </fieldset>
        <input type="hidden" name="pin" data-pin-value>
        @error('pin')
            <div class="field-error" role="alert">{{ $message }}</div>
        @enderror
        <button type="submit" class="btn btn--primary self-start">Entsperren <span aria-hidden="true">→</span></button>
    </form>

    <div class="lock-panel__hint">PIN eingeben oder Klinikausweis vorhalten</div>
</section>
