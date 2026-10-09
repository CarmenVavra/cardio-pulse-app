<x-layouts.hospital title="Zwei-Faktor-Anmeldung einrichten" active="account">
    <div class="list-page form-page">
        <div>
            @unless ($required && ! $user->hasTwoFactor())
                <a class="meta" href="{{ route('account.edit') }}">‹ Zurück zu Mein Konto</a>
            @endunless
            <h1>Zwei-Faktor-Anmeldung einrichten</h1>
            <p class="meta">Nach dem Passwort fragt {{ config('app.name') }} zusätzlich nach einem Code aus einer App auf Ihrem Handy. Wer nur Ihr Passwort kennt, kommt so nicht hinein.</p>
            @if ($user->hasTwoFactor())
                <div class="alert" role="note">Die Zwei-Faktor-Anmeldung ist bereits eingerichtet. Richten Sie sie hier neu ein, z. B. für ein neues Handy – der bisherige Schlüssel gilt danach nicht mehr.</div>
            @endif
        </div>

        <ol class="setup-steps">
            <li>
                <h2>App installieren</h2>
                <p class="meta">z. B. Google Authenticator, Microsoft Authenticator, 1Password oder FreeOTP – aus dem App Store bzw. Google Play.</p>
            </li>
            <li>
                <h2>QR-Code scannen</h2>
                <p class="meta">In der App „Konto hinzufügen“ bzw. „+“ wählen und diesen Code scannen.</p>
                {{-- SVG der QR-Bibliothek: enthält nur Pfade, keine Benutzereingaben. --}}
                <div class="qr-box" role="img" aria-label="QR-Code für die Authenticator-App">{!! $qrCode !!}</div>
                <p class="meta">Scannen nicht möglich? Diesen Schlüssel in der App eintippen (zeitbasiert):</p>
                <p class="secret-key"><code>{{ $secretKey }}</code></p>
            </li>
            <li>
                <h2>Code bestätigen</h2>
                <form method="POST" action="{{ route('account.two-factor.store') }}" class="stack" novalidate>
                    @csrf
                    @if ($errors->twoFactor->any())
                        <div class="alert alert--error" role="alert">{{ $errors->twoFactor->first() }}</div>
                    @endif
                    <div class="form-grid">
                        <div class="field">
                            <label class="field__label" for="code">6-stelliger Code aus der App</label>
                            <input class="input input--code" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required
                                   @if ($errors->twoFactor->has('code')) aria-invalid="true" @endif>
                        </div>
                        <x-password-field name="current_password" label="Ihr Passwort" bag="twoFactor" />
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary btn--lg">Zwei-Faktor-Anmeldung aktivieren <span aria-hidden="true">→</span></button>
                    </div>
                </form>
            </li>
        </ol>
    </div>
</x-layouts.hospital>
