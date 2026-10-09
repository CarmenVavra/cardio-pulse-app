<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Zwei-Faktor-Anmeldung (TOTP nach RFC 6238) für Ärzte – kompatibel mit
 * Google Authenticator, Microsoft Authenticator, 1Password, FreeOTP usw.
 */
class TwoFactorService
{
    public const RECOVERY_CODES = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    /** So lange gilt ein angezeigter QR-Code bzw. bleiben neue Wiederherstellungscodes abrufbar. */
    public const SETUP_MINUTES = 15;

    /**
     * Schlüssel für die laufende Einrichtung – bis zum ersten richtigen Code noch nicht beim
     * Benutzer gespeichert. Er liegt verschlüsselt im Cache statt in der Session: Die
     * Live-Aktualisierung des Boards speichert die Session alle paar Sekunden und könnte
     * einen dort abgelegten Schlüssel überschreiben – dann passte der gescannte QR-Code nicht mehr.
     */
    public function pendingSecret(User $user): string
    {
        $secret = $this->readEncrypted($this->setupKey($user));
        if (! is_string($secret)) {
            $secret = $this->google2fa->generateSecretKey(32);
        }

        // Bei jedem Aufruf derselbe Schlüssel; die Gültigkeit beginnt neu.
        Cache::put($this->setupKey($user), Crypt::encryptString((string) json_encode($secret)), now()->addMinutes(self::SETUP_MINUTES));

        return $secret;
    }

    public function hasPendingSecret(User $user): bool
    {
        return is_string($this->readEncrypted($this->setupKey($user)));
    }

    /**
     * Neue Wiederherstellungscodes einmalig abholen (aus demselben Grund im Cache statt als Flash-Meldung).
     *
     * @return list<string>|null
     */
    public function pullFreshRecoveryCodes(User $user): ?array
    {
        $codes = $this->readEncrypted($this->codesKey($user));
        Cache::forget($this->codesKey($user));

        return is_array($codes) ? array_values(array_map('strval', $codes)) : null;
    }

    /**
     * @param  list<string>  $codes
     */
    private function rememberFreshRecoveryCodes(User $user, array $codes): void
    {
        Cache::put($this->codesKey($user), Crypt::encryptString((string) json_encode($codes)), now()->addMinutes(self::SETUP_MINUTES));
    }

    private function readEncrypted(string $key): mixed
    {
        $value = Cache::get($key);
        if (! is_string($value)) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($value), true);
        } catch (DecryptException) {
            return null;
        }
    }

    private function setupKey(User $user): string
    {
        return 'two-factor:setup:'.$user->id;
    }

    private function codesKey(User $user): string
    {
        return 'two-factor:codes:'.$user->id;
    }

    /**
     * QR-Code für die Authenticator-App als SVG (ohne XML-Kopf, zum Einbetten).
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), (string) ($user->username ?? $user->email), $secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($url);

        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }

    /**
     * Den Schlüssel in Vierergruppen zum Abtippen, z. B. „ABCD EFGH …“.
     */
    public function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /**
     * Einrichtung abschließen, wenn der Code aus der App zum angezeigten Schlüssel passt.
     * Die neuen Wiederherstellungscodes holt die Kontoseite mit {@see pullFreshRecoveryCodes()} ab.
     */
    public function enable(User $user, string $code): bool
    {
        $secret = $this->readEncrypted($this->setupKey($user));
        if (! is_string($secret)) {
            return false;
        }

        $step = $this->google2fa->verifyKeyNewer($secret, (string) preg_replace('/\D/', '', $code), 0);
        if ($step === false) {
            return false;
        }

        $codes = $this->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used' => (int) $step,
        ])->save();

        Cache::forget($this->setupKey($user));
        $this->rememberFreshRecoveryCodes($user, $codes);

        AuditLog::record('account.two_factor_enabled', $user, [], $user);

        return true;
    }

    /**
     * Abschalten – durch den Arzt selbst, durch einen Admin (z. B. Handy verloren)
     * oder ohne $by auf der Kommandozeile des Servers.
     */
    public function disable(User $user, ?User $by): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used' => null,
        ])->save();

        if ($by !== null && $by->is($user)) {
            AuditLog::record('account.two_factor_disabled', $user, [], $user);
        } else {
            AuditLog::record('doctor.two_factor_reset', $user, $by === null ? ['via' => 'console'] : [], $by);
        }
    }

    public function regenerateRecoveryCodes(User $user): void
    {
        $codes = $this->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $this->rememberFreshRecoveryCodes($user, $codes);

        AuditLog::record('account.recovery_codes_regenerated', $user, [], $user);
    }

    /**
     * Code bei der Anmeldung prüfen: 6 Ziffern aus der App (jeder Code nur einmal)
     * oder ein Wiederherstellungscode (wird danach entwertet).
     *
     * @return 'app'|'recovery'|null
     */
    public function verify(User $user, string $input): ?string
    {
        if (! $user->hasTwoFactor()) {
            return null;
        }

        // Nur reine 6-stellige Eingaben sind App-Codes – ein Wiederherstellungscode kann selbst Ziffern enthalten.
        $compact = (string) preg_replace('/\s+/', '', $input);
        if (preg_match('/^\d{6}$/', $compact) === 1) {
            $step = $this->google2fa->verifyKeyNewer((string) $user->two_factor_secret, $compact, $user->two_factor_last_used ?? 0);
            if ($step === false) {
                return null;
            }

            $user->forceFill(['two_factor_last_used' => (int) $step])->save();

            return 'app';
        }

        $code = Str::lower(trim($input));
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $index => $candidate) {
            if (hash_equals($candidate, $code)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                AuditLog::record('auth.recovery_code_used', $user, ['remaining' => count($codes)], $user);

                return 'recovery';
            }
        }

        return null;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * @return list<string> z. B. „k7m2q-x9p4t“
     */
    private function generateRecoveryCodes(): array
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $part = fn () => implode('', array_map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, 5)));

        return array_map(fn () => $part().'-'.$part(), range(1, self::RECOVERY_CODES));
    }
}
