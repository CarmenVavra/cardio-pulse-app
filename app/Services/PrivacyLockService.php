<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * D6 – Entsperren des Privacy-Locks per PIN. Nach zu vielen falschen PINs wird
 * die Sitzung beendet, damit an einem unbeaufsichtigten Bildschirm nicht geraten werden kann.
 */
class PrivacyLockService
{
    public const MAX_PIN_ATTEMPTS = 5;

    public function attemptUnlock(User $user, string $pin): PinCheck
    {
        $key = 'pin:'.$user->id;

        if ($user->pin !== null && Hash::check($pin, $user->pin)) {
            RateLimiter::clear($key);
            AuditLog::record('screen.unlocked');

            return PinCheck::Valid;
        }

        RateLimiter::hit($key, 900);
        AuditLog::record('screen.unlock_failed');

        if (RateLimiter::attempts($key) >= self::MAX_PIN_ATTEMPTS) {
            RateLimiter::clear($key);
            AuditLog::record('screen.unlock_locked_out');

            return PinCheck::LockedOut;
        }

        return PinCheck::Invalid;
    }

    public function remainingAttempts(User $user): int
    {
        return max(0, self::MAX_PIN_ATTEMPTS - RateLimiter::attempts('pin:'.$user->id));
    }
}
