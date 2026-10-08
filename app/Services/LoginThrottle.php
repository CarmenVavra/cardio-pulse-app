<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sperrt Anmeldeversuche für ein Konto nach wiederholten Fehlversuchen kurzzeitig (Schutz vor Passwort-Raten).
 * Der Schlüssel enthält die IP-Adresse, damit Dritte ein Konto nicht von außen für alle Rechner sperren können.
 */
class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 300;

    /**
     * @throws ValidationException
     */
    public function ensureNotLocked(string $identifier, string $field): void
    {
        $key = $this->key($identifier);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

        AuditLog::record('auth.locked', null, ['identifier' => $identifier]);

        throw ValidationException::withMessages([
            $field => 'Zu viele Fehlversuche. Das Konto ist für '.$minutes.' '.($minutes === 1 ? 'Minute' : 'Minuten').' gesperrt.',
        ]);
    }

    public function failed(string $identifier): void
    {
        RateLimiter::hit($this->key($identifier), self::DECAY_SECONDS);
    }

    public function succeeded(string $identifier): void
    {
        RateLimiter::clear($this->key($identifier));
    }

    private function key(string $identifier): string
    {
        return 'login:'.Str::lower(trim($identifier)).'|'.request()->ip();
    }
}
