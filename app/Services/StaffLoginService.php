<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Anmeldung am Überwachungsscreen abschließen – direkt nach dem Passwort oder,
 * bei eingerichteter Zwei-Faktor-Anmeldung, erst nach dem Code aus der App.
 */
class StaffLoginService
{
    /** Session-Schlüssel für die halb abgeschlossene Anmeldung (Passwort ok, Code fehlt). */
    private const PENDING = 'two_factor.pending';

    /** So lange darf man sich mit der Eingabe des Codes Zeit lassen. */
    public const PENDING_SECONDS = 300;

    public function complete(Request $request, User $user, string $department, bool $sound, bool $twoFactor): void
    {
        Auth::login($user);
        $request->session()->regenerate();

        $request->session()->put([
            'department' => $department,
            'department_label' => config('cardiopulse.departments')[$department],
            'sound_enabled' => $sound,
            'screen_locked' => false,
        ]);

        AuditLog::record('auth.login', null, ['department' => $department, 'sound' => $sound, 'two_factor' => $twoFactor]);
    }

    /**
     * Passwort war richtig – noch nicht anmelden, sondern auf den Code warten.
     */
    public function startTwoFactor(Request $request, User $user, string $department, bool $sound): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::PENDING, [
            'user_id' => $user->id,
            'department' => $department,
            'sound' => $sound,
            'expires_at' => now()->addSeconds(self::PENDING_SECONDS)->getTimestamp(),
        ]);
    }

    /**
     * @return array{user: User, department: string, sound: bool}|null
     */
    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING);
        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            $this->forgetPending($request);

            return null;
        }

        $user = User::query()->where('role', UserRole::Staff)->find($pending['user_id'] ?? null);
        if ($user === null || ! $user->hasTwoFactor()) {
            $this->forgetPending($request);

            return null;
        }

        return ['user' => $user, 'department' => (string) $pending['department'], 'sound' => (bool) $pending['sound']];
    }

    public function forgetPending(Request $request): void
    {
        $request->session()->forget(self::PENDING);
    }
}
