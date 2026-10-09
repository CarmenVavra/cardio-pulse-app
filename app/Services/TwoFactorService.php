<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
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

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
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
     * Einrichtung abschließen, wenn der Code aus der App zum neuen Schlüssel passt.
     *
     * @return list<string>|null die neuen Wiederherstellungscodes oder null bei falschem Code
     */
    public function enable(User $user, string $secret, string $code): ?array
    {
        $step = $this->google2fa->verifyKeyNewer($secret, (string) preg_replace('/\D/', '', $code), 0);
        if ($step === false) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used' => (int) $step,
        ])->save();

        AuditLog::record('account.two_factor_enabled', $user, [], $user);

        return $codes;
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

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        AuditLog::record('account.recovery_codes_regenerated', $user, [], $user);

        return $codes;
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
