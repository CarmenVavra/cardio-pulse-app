<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Eigenes Konto: Passwort und Privacy-Lock-PIN ändern, Passwort per E-Mail-Link zurücksetzen.
 */
class AccountService
{
    /**
     * Neues Passwort setzen und alle anderen Sitzungen des Benutzers beenden
     * (z. B. ein vergessenes Login auf einem Stationsrechner).
     */
    public function changePassword(User $user, string $password, ?string $currentSessionId): void
    {
        $this->storePassword($user, $password);
        $this->endOtherSessions($user, $currentSessionId);

        AuditLog::record('account.password_changed', $user);
    }

    /**
     * Passwort über den Link aus der E-Mail neu setzen – alle Sitzungen enden,
     * auch eine, die jemand mit dem alten Passwort geöffnet hat.
     */
    public function resetPassword(User $user, string $password): void
    {
        $this->storePassword($user, $password);
        $this->endOtherSessions($user, null);

        AuditLog::record('account.password_reset', $user, [], $user);
    }

    public function changePin(User $user, string $pin): void
    {
        $user->forceFill(['pin' => $pin])->save();

        AuditLog::record('account.pin_changed', $user);
    }

    private function storePassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();
    }

    private function endOtherSessions(User $user, ?string $currentSessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($currentSessionId !== null, fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();
    }
}
