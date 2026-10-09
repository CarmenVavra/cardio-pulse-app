<x-layouts.hospital title="Mein Konto" active="account">
    <div class="list-page form-page">
        <div>
            <h1>Mein Konto</h1>
            <p class="meta">{{ $user->displayName() }} · Benutzerkennung <b>{{ $user->username }}</b> · {{ $user->email }}</p>
        </div>

        @if ($freshRecoveryCodes)
            <section class="recovery-box" aria-labelledby="recovery-heading">
                <h2 id="recovery-heading">Ihre Wiederherstellungscodes</h2>
                <p>Jetzt ausdrucken oder sicher notieren – sie werden <b>nur dieses eine Mal</b> angezeigt. Ohne Handy melden Sie sich mit einem dieser Codes an; jeder funktioniert einmal.</p>
                <ol class="recovery-codes">
                    @foreach ($freshRecoveryCodes as $code)
                        <li><code>{{ $code }}</code></li>
                    @endforeach
                </ol>
                <button type="button" class="btn btn--outline" data-print>Drucken</button>
            </section>
        @endif

        <fieldset class="form-section">
            <legend>Zwei-Faktor-Anmeldung</legend>
            @if ($user->hasTwoFactor())
                <p class="meta">
                    <b>Eingerichtet</b> seit {{ $user->two_factor_confirmed_at?->format('d.m.Y') }} · noch {{ $recoveryCodesLeft }} {{ $recoveryCodesLeft === 1 ? 'Wiederherstellungscode' : 'Wiederherstellungscodes' }}.
                    Neues Handy? <a href="{{ route('account.two-factor.create') }}">Neu einrichten</a>
                </p>
                <form method="POST" action="{{ route('account.two-factor.recovery-codes') }}" class="stack" novalidate>
                    @csrf
                    @if ($errors->twoFactorManage->any())
                        <div class="alert alert--error" role="alert">{{ $errors->twoFactorManage->first() }}</div>
                    @endif
                    <div class="form-grid">
                        <x-password-field name="two_factor_password" label="Passwort zur Bestätigung" bag="twoFactorManage" />
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn--outline btn--lg">Neue Wiederherstellungscodes</button>
                        @unless ($twoFactorRequired)
                            <button type="submit" class="btn btn--outline btn--lg" formaction="{{ route('account.two-factor.destroy') }}"
                                    data-confirm="Zwei-Faktor-Anmeldung wirklich abschalten? Danach genügt wieder das Passwort.">Abschalten</button>
                        @endunless
                    </div>
                </form>
            @else
                <p class="meta">Schützt Ihr Konto zusätzlich: Nach dem Passwort fragt {{ config('app.name') }} nach einem Code aus einer App auf Ihrem Handy.</p>
                <div class="form-actions">
                    <a class="btn btn--primary btn--lg" href="{{ route('account.two-factor.create') }}">Einrichten <span aria-hidden="true">→</span></a>
                </div>
            @endif
        </fieldset>

        <form method="POST" action="{{ route('account.password') }}" class="stack" novalidate>
            @csrf
            @method('PUT')
            <fieldset class="form-section">
                <legend>Passwort ändern</legend>
                <p class="meta">Mindestens 8 Zeichen mit Buchstaben und Ziffern. Danach werden Sie an allen anderen Rechnern abgemeldet.</p>
                @if ($errors->password->any())
                    <div class="alert alert--error" role="alert">Das Passwort wurde nicht geändert. Bitte prüfen Sie die markierten Felder.</div>
                @endif
                <div class="form-grid">
                    <x-password-field name="current_password" label="Aktuelles Passwort" bag="password" />
                    <x-password-field name="password" label="Neues Passwort" bag="password" autocomplete="new-password" />
                    <x-password-field name="password_confirmation" label="Neues Passwort wiederholen" bag="password" autocomplete="new-password" />
                </div>
            </fieldset>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary btn--lg">Passwort ändern <span aria-hidden="true">→</span></button>
            </div>
        </form>

        <form method="POST" action="{{ route('account.pin') }}" class="stack" novalidate>
            @csrf
            @method('PUT')
            <fieldset class="form-section">
                <legend>PIN für den Privacy-Lock ändern</legend>
                <p class="meta">6 Ziffern zum Entsperren des Überwachungsscreens. Zur Bestätigung bitte Ihr Passwort eingeben.</p>
                @if ($errors->pin->any())
                    <div class="alert alert--error" role="alert">Die PIN wurde nicht geändert. Bitte prüfen Sie die markierten Felder.</div>
                @endif
                <div class="form-grid">
                    <x-password-field name="pin_current_password" label="Passwort" bag="pin" />
                    <x-password-field name="pin" label="Neue PIN (6 Ziffern)" bag="pin" autocomplete="off" inputmode="numeric" maxlength="6" />
                    <x-password-field name="pin_confirmation" label="Neue PIN wiederholen" bag="pin" autocomplete="off" inputmode="numeric" maxlength="6" />
                </div>
            </fieldset>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary btn--lg">PIN ändern <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </div>
</x-layouts.hospital>
